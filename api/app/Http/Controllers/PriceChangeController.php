<?php

namespace App\Http\Controllers;

use App\Enums\PriceChangeStatus;
use App\Http\Resources\CategoryResource;
use App\Models\PriceChange;
use App\Models\Subscription;
use App\Services\BillingCalculator;
use App\Services\PriceChanges;
use App\Support\Fraction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PriceChangeController extends Controller
{
    public function __construct(
        private readonly BillingCalculator $billing,
        private readonly PriceChanges $priceChanges,
    ) {}

    /**
     * "Neu": pending changes (to decide) and accepted ones that are not in effect yet (announced).
     * Plus the monthly/yearly effect of all increases and the trend since the contract start.
     */
    public function index(): JsonResponse
    {
        $today = CarbonImmutable::today();

        $open = PriceChange::query()
            ->with('subscription.category')
            ->whereHas('subscription')
            ->where(fn ($q) => $q->where('status', PriceChangeStatus::Pending->value)
                ->orWhere(fn ($q) => $q->where('status', PriceChangeStatus::Accepted->value)
                    ->whereNull('applied_at')
                    ->whereDate('effective_date', '>', $today->toDateString())))
            ->orderBy('effective_date')
            ->get();

        $monthlyIncrease = Fraction::zero();
        foreach ($open as $pc) {
            $s = $pc->subscription;
            $delta = $this->billing->monthly(max(0, $pc->new_cents - $pc->old_cents), $s->interval_unit, $s->interval_count);
            $monthlyIncrease = $monthlyIncrease->add($delta);
        }

        $subscriptions = Subscription::query()->with('priceChanges')->where('status', 'active')->orderBy('name')->get();
        $trends = $subscriptions->map(function (Subscription $s) {
            $applied = $s->priceChanges->filter(fn (PriceChange $p) => $p->status === PriceChangeStatus::Accepted && $p->applied_at !== null);
            $first = $applied->first()?->old_cents;

            return [
                'subscription_id' => $s->id,
                'name' => $s->name,
                'percent' => $first ? (new Fraction(($s->amount_cents - $first) * 1000, $first))->round() / 10 : null,
            ];
        })->sortByDesc(fn ($t) => $t['percent'] ?? -INF)->values();

        return response()->json([
            'data' => $open->map(fn (PriceChange $pc) => $this->present($pc, $today))->values(),
            'meta' => [
                'pending_count' => $open->where('status', PriceChangeStatus::Pending)->count(),
                'increase_count' => $open->filter(fn (PriceChange $pc) => $pc->new_cents > $pc->old_cents)->count(),
                'monthly_increase_cents' => $monthlyIncrease->round(),
                'yearly_increase_cents' => $monthlyIncrease->multiply(12)->round(),
                'trends' => $trends,
            ],
        ]);
    }

    public function accept(PriceChange $priceChange): JsonResponse
    {
        Gate::authorize('update', $priceChange);
        abort_unless($priceChange->status === PriceChangeStatus::Pending, 422, 'Diese Preisänderung ist schon entschieden.');

        return response()->json(['data' => $this->present($this->priceChanges->accept($priceChange)->load('subscription.category'), CarbonImmutable::today())]);
    }

    public function dismiss(PriceChange $priceChange): JsonResponse
    {
        Gate::authorize('update', $priceChange);
        abort_unless($priceChange->status === PriceChangeStatus::Pending, 422, 'Diese Preisänderung ist schon entschieden.');

        return response()->json(['data' => $this->present($this->priceChanges->dismiss($priceChange)->load('subscription.category'), CarbonImmutable::today())]);
    }

    public function store(Request $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('update', $subscription);

        $data = $request->validate([
            'new_cents' => ['required', 'integer', 'min:1', 'max:100000000', 'not_in:'.$subscription->amount_cents],
            'effective_date' => ['required', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
        ], [
            'new_cents.not_in' => 'Das ist der aktuelle Preis.',
        ], [
            'new_cents' => 'neuer Preis',
            'effective_date' => 'gültig ab',
        ]);

        $change = $this->priceChanges->announce($subscription, $data['new_cents'], CarbonImmutable::parse($data['effective_date']));

        return response()->json(['data' => $this->present($change->load('subscription.category'), CarbonImmutable::today())], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PriceChange $pc, CarbonImmutable $today): array
    {
        $s = $pc->subscription;

        return [
            'id' => $pc->id,
            'status' => $pc->status->value,
            'source' => $pc->source,
            'old_cents' => $pc->old_cents,
            'new_cents' => $pc->new_cents,
            'diff_cents' => $pc->new_cents - $pc->old_cents,
            'percent' => $pc->old_cents > 0 ? (new Fraction(($pc->new_cents - $pc->old_cents) * 1000, $pc->old_cents))->round() / 10 : null,
            'monthly_diff_cents' => $this->billing->monthly(abs($pc->new_cents - $pc->old_cents), $s->interval_unit, $s->interval_count)->round()
                * ($pc->new_cents >= $pc->old_cents ? 1 : -1),
            'effective_date' => $pc->effective_date->toDateString(),
            'in_future' => $pc->effective_date->greaterThan($today),
            'applied' => $pc->applied_at !== null,
            'subscription' => [
                'id' => $s->id,
                'name' => $s->name,
                'rhythm_label' => $this->billing->rhythmLabel($s->interval_unit, $s->interval_count),
                'category' => $s->category ? CategoryResource::make($s->category)->resolve() : null,
            ],
        ];
    }
}
