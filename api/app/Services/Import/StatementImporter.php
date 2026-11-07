<?php

namespace App\Services\Import;

use App\Enums\PriceChangeStatus;
use App\Jobs\SendPushReminders;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportCandidate;
use App\Models\ImportRule;
use App\Models\PriceChange;
use App\Models\PushToken;
use App\Models\ReminderSetting;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\User;
use App\Services\PriceChanges;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Upload → parse → detect → match → store candidates (no raw data).
 *
 * Matching (SPEC step 6): an import rule or an existing subscription (name + amount ±10 %)
 * makes a candidate a duplicate. If the booked amount differs from the stored one by more than
 * 1 % or 0,10 €, a pending price change is created instead. For installments and savings the
 * new amount is accepted right away (no call to action, so nothing orange).
 */
class StatementImporter
{
    public const PRICE_CHANGE_MIN_CENTS = 10;

    public const PRICE_CHANGE_MIN_RATIO = 0.01;

    public const MATCH_TOLERANCE = 0.10;

    public function __construct(
        private readonly RecurringPaymentDetector $detector,
        private readonly PayeeNormalizer $normalizer,
        private readonly PriceChanges $priceChanges,
    ) {}

    public function import(User $user, BankStatementParser $parser, string $contents, ?string $fileName = null): ImportBatch
    {
        $statement = $parser->parse($contents);
        $detected = $this->detector->detect($statement->debits);

        $dates = collect($statement->debits)->map(fn (Transaction $t) => $t->date);

        return DB::transaction(function () use ($user, $parser, $statement, $detected, $dates, $fileName) {
            $batch = new ImportBatch([
                'bank_format' => $parser->key(),
                'file_name' => $fileName ? mb_substr(basename($fileName), 0, 120) : null,
                'period_from' => $dates->min(),
                'period_to' => $dates->max(),
                'rows_total' => $statement->rowsTotal,
                'debits_total' => count($statement->debits),
                'status' => 'review',
            ]);
            $batch->user_id = $user->id;
            $batch->save();

            $subscriptions = Subscription::query()->where('user_id', $user->id)->get();
            $rules = ImportRule::query()->where('user_id', $user->id)->get();
            $templates = Template::query()->get();
            $categoryIds = Category::query()->whereNull('user_id')->pluck('id', 'key');
            $newPriceChanges = [];

            foreach ($detected as $payment) {
                $match = $this->match($payment, $rules, $subscriptions);
                $template = $this->template($payment->payeeNormalized, $templates);

                $status = ImportCandidate::STATUS_NEW;
                $amountChanged = false;
                if ($match) {
                    $amountChanged = $this->isPriceChange($match->amount_cents, $payment->amountCents())
                        && ! $this->priceChangedSince($match, $payment->lastDate());
                    // Installments and savings: a new amount is no call to action, it is taken over directly.
                    $status = $amountChanged && $match->isSubscription()
                        ? ImportCandidate::STATUS_PRICE_CHANGE
                        : ImportCandidate::STATUS_DUPLICATE;
                }

                $candidate = new ImportCandidate([
                    'batch_id' => $batch->id,
                    'payee_normalized' => $payment->payeeNormalized,
                    'display_name' => $template?->name ?? $this->normalizer->displayName($payment->payeeNormalized),
                    'creditor_id' => $payment->creditorId(),
                    'mandate_ref' => $payment->mandateRef(),
                    'amount_cents' => $payment->amountCents(),
                    'interval_unit' => $payment->intervalUnit ?? $template?->interval_unit,
                    'interval_count' => $payment->intervalCount ?? $template?->interval_count,
                    'occurrences' => $payment->occurrences(),
                    'first_date' => $payment->first()->date,
                    'last_date' => $payment->lastDate(),
                    'confidence' => $payment->confidence,
                    'template_key' => $template?->key,
                    'category_id' => $match?->category_id ?? ($template ? ($categoryIds[$template->category_key] ?? null) : null),
                    'subscription_id' => $match?->id,
                    'status' => $status,
                ]);
                $candidate->user_id = $user->id;
                $candidate->save();

                if ($amountChanged && ($pc = $this->recordPriceChange($match, $payment))) {
                    if ($match->isSubscription()) {
                        $newPriceChanges[] = $pc;
                    } else {
                        $this->priceChanges->accept($pc);
                    }
                }
            }

            if ($newPriceChanges !== []) {
                $this->notifyPriceChanges($user, $newPriceChanges);
            }

            return $batch;
        });
    }

    /**
     * Creates subscriptions for the selected candidates and remembers the mapping as import rules.
     * Afterwards all candidates of the batch are deleted (privacy).
     *
     * @param  list<int>  $candidateIds
     * @return list<Subscription>
     */
    public function apply(ImportBatch $batch, array $candidateIds): array
    {
        return DB::transaction(function () use ($batch, $candidateIds) {
            $candidates = $batch->candidates()
                ->whereIn('id', $candidateIds)
                ->where('status', ImportCandidate::STATUS_NEW)
                ->get();

            $created = [];
            foreach ($candidates as $c) {
                $subscription = new Subscription([
                    'name' => $c->display_name,
                    'amount_cents' => $c->amount_cents,
                    'interval_unit' => $c->interval_unit?->value ?? 'month',
                    'interval_count' => $c->interval_count ?? 1,
                    // The last booking is a known charge; the next one is computed from it.
                    'anchor_date' => $c->last_date->toDateString(),
                    'category_id' => $c->category_id,
                    'template_key' => $c->template_key,
                    'payment_method' => $c->mandate_ref ? 'Lastschrift' : null,
                ]);
                $subscription->user_id = $batch->user_id;
                $subscription->source = 'import';
                $subscription->save();

                $rule = new ImportRule([
                    'creditor_id' => $c->creditor_id,
                    'mandate_ref' => $c->mandate_ref,
                    'pattern' => $c->payee_normalized,
                    'subscription_id' => $subscription->id,
                ]);
                $rule->user_id = $batch->user_id;
                $rule->save();

                $created[] = $subscription;
            }

            // Duplicates and price changes: remember the mapping too, so the next import is exact.
            $batch->candidates()
                ->whereIn('status', [ImportCandidate::STATUS_DUPLICATE, ImportCandidate::STATUS_PRICE_CHANGE])
                ->whereNotNull('subscription_id')
                ->get()
                ->each(function (ImportCandidate $c) use ($batch) {
                    $exists = ImportRule::query()->where('user_id', $batch->user_id)
                        ->where('subscription_id', $c->subscription_id)
                        ->where('pattern', $c->payee_normalized)
                        ->exists();
                    if (! $exists) {
                        $rule = new ImportRule([
                            'creditor_id' => $c->creditor_id,
                            'mandate_ref' => $c->mandate_ref,
                            'pattern' => $c->payee_normalized,
                            'subscription_id' => $c->subscription_id,
                        ]);
                        $rule->user_id = $batch->user_id;
                        $rule->save();
                    }
                });

            $batch->candidates()->delete();
            $batch->forceFill(['status' => 'applied', 'applied_at' => now()])->save();

            return $created;
        });
    }

    /**
     * @param  Collection<int, ImportRule>  $rules
     * @param  Collection<int, Subscription>  $subscriptions
     */
    private function match(DetectedPayment $payment, Collection $rules, Collection $subscriptions): ?Subscription
    {
        $creditor = $payment->creditorId();
        $mandate = $payment->mandateRef();

        $rule = $rules->first(fn (ImportRule $r) => $creditor !== null && $r->creditor_id === $creditor
                && ($r->mandate_ref === null || $r->mandate_ref === $mandate))
            ?? $rules->first(fn (ImportRule $r) => $r->pattern === $payment->payeeNormalized);

        if ($rule && ($sub = $subscriptions->firstWhere('id', $rule->subscription_id))) {
            return $sub;
        }

        // Existing subscription with a similar name and amount
        return $subscriptions
            ->filter(function (Subscription $s) use ($payment) {
                $name = $this->normalizer->normalize($s->name);
                if (mb_strlen($name) < 3) {
                    return false;
                }
                $similarName = str_contains($payment->payeeNormalized, $name) || str_contains($name, $payment->payeeNormalized)
                    || $this->firstWord($name) === $this->firstWord($payment->payeeNormalized);

                return $similarName && abs($s->amount_cents - $payment->amountCents()) <= $s->amount_cents * self::MATCH_TOLERANCE + self::PRICE_CHANGE_MIN_CENTS;
            })
            ->sortBy(fn (Subscription $s) => abs($s->amount_cents - $payment->amountCents()))
            ->first();
    }

    public function isPriceChange(int $storedCents, int $bookedCents): bool
    {
        $diff = abs($bookedCents - $storedCents);

        return $diff > self::PRICE_CHANGE_MIN_CENTS && $diff > $storedCents * self::PRICE_CHANGE_MIN_RATIO;
    }

    /**
     * The price was changed (and accepted) after the last booking in the statement,
     * e.g. entered by hand after a price cut. The statement is older than that
     * price, so its amount must not be suggested as a new price.
     */
    private function priceChangedSince(Subscription $subscription, CarbonImmutable $lastBooking): bool
    {
        return PriceChange::query()
            ->where('user_id', $subscription->user_id)
            ->where('subscription_id', $subscription->id)
            ->where('status', PriceChangeStatus::Accepted->value)
            ->whereDate('effective_date', '>', $lastBooking->toDateString())
            ->exists();
    }

    private function recordPriceChange(Subscription $subscription, DetectedPayment $payment): ?PriceChange
    {
        $exists = PriceChange::query()
            ->where('user_id', $subscription->user_id)
            ->where('subscription_id', $subscription->id)
            ->where('new_cents', $payment->amountCents())
            ->whereIn('status', [PriceChangeStatus::Pending->value, PriceChangeStatus::Dismissed->value])
            ->exists();
        if ($exists) {
            return null; // already reported (or dismissed) – don't nag
        }

        // First booking with the new amount = effective date
        $effective = collect($payment->transactions)
            ->first(fn (Transaction $t) => $t->amountCents === $payment->amountCents())?->date ?? $payment->lastDate();

        $pc = new PriceChange([
            'subscription_id' => $subscription->id,
            'old_cents' => $subscription->amount_cents,
            'new_cents' => $payment->amountCents(),
            'effective_date' => $effective->toDateString(),
            'source' => 'import',
            'status' => PriceChangeStatus::Pending,
        ]);
        $pc->user_id = $subscription->user_id;
        $pc->save();

        return $pc;
    }

    /**
     * Push right after the import (SPEC: "neue Preisänderung sofort nach Import"). No amounts.
     *
     * @param  list<PriceChange>  $priceChanges
     */
    private function notifyPriceChanges(User $user, array $priceChanges): void
    {
        $settings = ReminderSetting::forUser($user);
        $hasDevice = PushToken::query()->where('user_id', $user->id)->exists();
        if (! $settings->push || ! $hasDevice) {
            return;
        }

        $ids = [];
        $names = [];
        foreach ($priceChanges as $pc) {
            $inserted = DB::table('sent_reminders')->insertOrIgnore([
                'user_id' => $user->id,
                'subscription_id' => $pc->subscription_id,
                'type' => 'price',
                'due_date' => $pc->effective_date->toDateString(),
                'offset_days' => 0,
                'channel' => 'push',
                'created_at' => now(),
            ]);
            if ($inserted === 1) {
                $ids[] = (int) DB::table('sent_reminders')->where('subscription_id', $pc->subscription_id)
                    ->where('type', 'price')->where('due_date', $pc->effective_date->toDateString())
                    ->where('channel', 'push')->value('id');
                $names[] = Subscription::withoutGlobalScopes()->find($pc->subscription_id)?->name;
            }
        }

        if ($ids === []) {
            return;
        }

        $names = array_values(array_filter($names));
        $message = count($names) === 1
            ? ['title' => 'Preisänderung erkannt', 'body' => "Bei {$names[0]} hat sich der Preis geändert.", 'data' => ['url' => '/price-changes']]
            : ['title' => count($names).' Preisänderungen erkannt', 'body' => implode(', ', array_slice($names, 0, 3)).'.', 'data' => ['url' => '/price-changes']];

        SendPushReminders::dispatch($user->id, [$message], $ids);
    }

    /**
     * @param  Collection<int, Template>  $templates
     */
    private function template(string $payee, Collection $templates): ?Template
    {
        foreach ($templates as $t) {
            $terms = array_filter(array_merge([$this->normalizer->normalize($t->name)], explode(' ', mb_strtoupper((string) $t->aliases))));
            foreach ($terms as $term) {
                $term = strtr($term, ['Ä' => 'AE', 'Ö' => 'OE', 'Ü' => 'UE']);
                if (mb_strlen($term) >= 3 && preg_match('/\b'.preg_quote($term, '/').'\b/u', $payee)) {
                    return $t;
                }
            }
        }

        return null;
    }

    private function firstWord(string $value): string
    {
        return explode(' ', $value)[0] ?? '';
    }
}
