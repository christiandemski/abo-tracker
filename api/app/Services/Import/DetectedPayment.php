<?php

namespace App\Services\Import;

use App\Enums\IntervalUnit;
use Carbon\CarbonImmutable;

/**
 * A group of debits that looks like a subscription.
 */
final class DetectedPayment
{
    public const CONFIDENCE_HIGH = 'high';

    public const CONFIDENCE_CHECK = 'check';

    /**
     * @param  list<Transaction>  $transactions  sorted by date
     */
    public function __construct(
        public readonly string $payeeNormalized,
        public readonly array $transactions,
        public readonly ?IntervalUnit $intervalUnit,
        public readonly ?int $intervalCount,
        public readonly string $confidence,
    ) {}

    public function latest(): Transaction
    {
        return $this->transactions[count($this->transactions) - 1];
    }

    public function first(): Transaction
    {
        return $this->transactions[0];
    }

    public function occurrences(): int
    {
        return count($this->transactions);
    }

    /** Current price = most recent booking */
    public function amountCents(): int
    {
        return $this->latest()->amountCents;
    }

    public function lastDate(): CarbonImmutable
    {
        return $this->latest()->date;
    }

    public function creditorId(): ?string
    {
        return $this->latest()->creditorId;
    }

    public function mandateRef(): ?string
    {
        return $this->latest()->mandateRef;
    }
}
