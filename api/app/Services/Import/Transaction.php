<?php

namespace App\Services\Import;

use Carbon\CarbonImmutable;

/**
 * One debit from a bank statement, held in memory only. Never persisted as such;
 * account numbers (IBAN/BIC) are not even read.
 */
final readonly class Transaction
{
    public function __construct(
        public CarbonImmutable $date,
        public string $payee,
        public string $purpose,
        /** positive cents (debits only) */
        public int $amountCents,
        public ?string $creditorId = null,
        public ?string $mandateRef = null,
        public string $bookingText = '',
    ) {}

    public function isDirectDebit(): bool
    {
        return $this->creditorId !== null && $this->mandateRef !== null;
    }
}
