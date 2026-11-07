<?php

namespace App\Http\Controllers;

use App\Enums\PriceChangeStatus;
use App\Enums\SubscriptionKind;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Http\Requests\SubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\BillingCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function index(Request $request, BillingCalculator $billing): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Subscription::class);

        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['active', 'cancelled', 'paused', 'finished', 'ended', 'all'])],
            'kind' => ['sometimes', Rule::enum(SubscriptionKind::class)],
            'category' => ['sometimes', 'integer'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sort' => ['sometimes', Rule::in(['name', 'price', 'next_charge'])],
        ]);

        $query = Subscription::query()->with('category');

        $status = $filters['status'] ?? 'all';
        if ($status === 'ended') {
            // Cancelled subscriptions and savings, paid-off installments
            $query->whereIn('status', [SubscriptionStatus::Cancelled->value, SubscriptionStatus::Finished->value]);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }
        if (isset($filters['kind'])) {
            $query->where('kind', $filters['kind']);
        }
        if (isset($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }
        if (! empty($filters['q'])) {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('notes', 'like', $term));
        }

        $subscriptions = $query->get();

        // Sorting by price uses the monthly equivalent, which is computed, not stored.
        $sorted = match ($filters['sort'] ?? 'next_charge') {
            'name' => $subscriptions->sortBy(fn (Subscription $s) => mb_strtolower($s->name)),
            'price' => $subscriptions->sortByDesc(fn (Subscription $s) => $billing->monthlyFor($s)->numerator / $billing->monthlyFor($s)->denominator),
            'next_charge' => $subscriptions->sortBy(fn (Subscription $s) => [
                $s->isActive() ? 0 : 1,
                $s->next_charge_date?->toDateString() ?? '9999-12-31',
                mb_strtolower($s->name),
            ]),
        };

        return SubscriptionResource::collection($sorted->values());
    }

    public function store(SubscriptionRequest $request): JsonResponse
    {
        Gate::authorize('create', Subscription::class);

        $subscription = new Subscription($request->validated());
        $this->checkLastInstallment($subscription);
        $subscription->source = $subscription->template_key ? SubscriptionSource::Template : SubscriptionSource::Manual;
        $subscription->save();

        return SubscriptionResource::make($subscription->load('category', 'priceChanges'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Subscription $subscription): SubscriptionResource
    {
        Gate::authorize('view', $subscription);

        return SubscriptionResource::make($subscription->load('category', 'priceChanges'));
    }

    public function update(SubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        Gate::authorize('update', $subscription);

        DB::transaction(function () use ($request, $subscription) {
            $oldAmount = $subscription->amount_cents;
            $subscription->fill($request->validated());

            // For installments ends_at is the last payment; otherwise only cancelling sets it.
            if ($subscription->status !== SubscriptionStatus::Cancelled && ! $subscription->isInstallment()) {
                $subscription->ends_at = null;
            }
            $this->checkLastInstallment($subscription);

            // Manual price edits are recorded, so the detail page shows a price history.
            if ($subscription->isDirty('amount_cents')) {
                $subscription->priceChanges()->create([
                    'old_cents' => $oldAmount,
                    'new_cents' => $subscription->amount_cents,
                    'effective_date' => CarbonImmutable::today(),
                    'source' => 'manual',
                    'status' => PriceChangeStatus::Accepted,
                ])->forceFill(['applied_at' => now()])->save();
            }

            $subscription->save();
        });

        return SubscriptionResource::make($subscription->load('category', 'priceChanges'));
    }

    public function destroy(Subscription $subscription): Response
    {
        Gate::authorize('delete', $subscription);

        $subscription->delete();

        return response()->noContent();
    }

    /**
     * Marks the subscription as cancelled. Without ends_at it runs until the end of the
     * current term (with minimum term) or until the day before the next charge.
     */
    public function cancel(Request $request, Subscription $subscription): SubscriptionResource
    {
        Gate::authorize('update', $subscription);
        abort_if($subscription->isInstallment(), 422, 'Eine Rate lässt sich nicht kündigen. Ändere stattdessen die letzte Rate.');

        $data = $request->validate([
            'ends_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'cancelled_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'cancel_method' => ['sometimes', 'nullable', Rule::in(['online', 'mail', 'pdf', 'other'])],
        ]);

        $endsAt = isset($data['ends_at'])
            ? CarbonImmutable::parse($data['ends_at'])
            : ($subscription->hasTerm() && $subscription->term_end_date
                ? $subscription->term_end_date->subDay()
                : ($subscription->next_charge_date?->subDay() ?? CarbonImmutable::today()));

        $subscription->forceFill([
            'status' => SubscriptionStatus::Cancelled,
            'ends_at' => $endsAt,
            'cancelled_at' => $data['cancelled_at'] ?? CarbonImmutable::today()->toDateString(),
            'cancel_method' => $data['cancel_method'] ?? null,
            'kept_until' => null,
        ])->save();

        return SubscriptionResource::make($subscription->load('category', 'priceChanges'));
    }

    public function reactivate(Subscription $subscription): SubscriptionResource
    {
        Gate::authorize('update', $subscription);
        abort_if($subscription->isInstallment(), 422, 'Eine Rate lässt sich nicht reaktivieren. Ändere stattdessen die letzte Rate.');

        $subscription->forceFill(['status' => SubscriptionStatus::Active, 'ends_at' => null, 'cancelled_at' => null, 'cancel_method' => null])->save();

        return SubscriptionResource::make($subscription->load('category', 'priceChanges'));
    }

    /**
     * Installments need their last payment, and it cannot lie before the first one.
     *
     * @throws ValidationException
     */
    private function checkLastInstallment(Subscription $subscription): void
    {
        if (! $subscription->isInstallment()) {
            return;
        }
        if ($subscription->ends_at === null) {
            throw ValidationException::withMessages(['ends_at' => 'Gib an, wann die letzte Rate fällig ist.']);
        }
        if ($subscription->ends_at->lessThan($subscription->anchor_date)) {
            throw ValidationException::withMessages(['ends_at' => 'Die letzte Rate kann nicht vor der ersten liegen.']);
        }
    }
}
