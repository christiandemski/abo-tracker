<?php

namespace App\Services\Import;

final readonly class ParsedStatement
{
    /**
     * @param  list<Transaction>  $debits
     */
    public function __construct(
        public array $debits,
        public int $rowsTotal,
    ) {}
}
