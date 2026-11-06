<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Subscription;
use App\Models\UsageCheck;
use App\Services\BillingCalculator;
use App\Support\Fraction;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Lohnt sich das noch?" – quarterly check. A round covers the last ROUND_DAYS days:
 * every active subscription is asked once (most expensive first). Rare/never answers
 * become cancellation candidates with their yearly savings.
 */
class UsageCheckController extends Controller
{
    public const ROUND_DAYS = 90;

    public function __construct(private readonly BillingCalculator $billing) {}

    public function next(): JsonResponse
    {
        return response()->json(['data' => $this->state()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subscription_id' => ['required', 'integer', Rule::exists('subscriptions', 'id')->where('user_id', $request->user()->id)->whereNull('deleted_at')],
            'answer' => ['required', Rule::in(UsageCheck::ANSWERS)],
        ]);

        UsageCheck::create($data + ['checked_at' => now()]);

        return response()->json(['data' => $this->state()], 201);
    }

    /** "Neu starten": forget the answers of the current round. */
    public function restart(): JsonResponse
    {
        UsageCheck::query()->where('checked_at', '>=', $this->roundStart())->delete();

        return response()->json(['data' => $this->state()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        $active = Subscription::query()->with('category')->where('status', 'active')->get()
            ->sortByDesc(function (Subscription $s) {
                $m = $this->billing->monthlyFor($s);

                return $m->numerator / $m->denominator;
            })
            ->values();

        $answers = UsageCheck::query()
            ->where('checked_at', '>=', $this->roundStart())
            ->orderBy('checked_at')
            ->orderBy('id')
            ->get()
            ->keyBy('subscription_id'); // latest answer per subscription wins

        $current = $active->first(fn (Subscription $s) => ! $answers->has($s->id));
        $answered = $active->filter(fn (Subscription $s) => $answers->has($s->id))->count();

        $candidates = $active
            ->filter(fn (Subscription $s) => in_array($answers->get($s->id)?->answer, ['rare', 'never'], true))
            ->map(fn (Subscription $s) => [
                'subscription_id' => $s->id,
                'name' => $s->name,
                'answer' => $answers->get($s->id)->answer,
                'yearly_cents' => $this->billing->yearly($s->amount_cents, $s->interval_unit, $s->interval_count)->round(),
                'has_term' => $s->hasTerm(),
            ])
            ->sortBy(fn ($c) => [$c['answer'] === 'never' ? 0 : 1, -$c['yearly_cents']])
            ->values();

        $savings = Fraction::sum($active
            ->filter(fn (Subscription $s) => in_array($answers->get($s->id)?->answer, ['rare', 'never'], true))
            ->map(fn (Subscription $s) => $this->billing->yearly($s->amount_cents, $s->interval_unit, $s->interval_count)));

        $lastCheck = UsageCheck::query()->max('checked_at');

        return [
            'current' => $current ? [
                'id' => $current->id,
                'name' => $current->name,
                'amount_cents' => $current->amount_cents,
                'rhythm_label' => $this->billing->rhythmLabel($current->interval_unit, $current->interval_count),
                'yearly_cents' => $this->billing->yearly($current->amount_cents, $current->interval_unit, $current->interval_count)->round(),
                'category' => $current->category ? CategoryResource::make($current->category)->resolve() : null,
            ] : null,
            'progress' => ['answered' => $answered, 'total' => $active->count()],
            'done' => $active->isNotEmpty() && $current === null,
            'candidates' => $candidates,
            'savings_yearly_cents' => $savings->round(),
            'last_checked_at' => $lastCheck ? CarbonImmutable::parse($lastCheck)->toIso8601String() : null,
            // Overview card: nothing answered for a full round
            'due' => $active->isNotEmpty() && ($lastCheck === null || CarbonImmutable::parse($lastCheck)->lessThan($this->roundStart())),
        ];
    }

    private function roundStart(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays(self::ROUND_DAYS);
    }
}
