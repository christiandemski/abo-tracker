<?php

namespace App\Services\Import;

/**
 * One implementation per bank export format (Sparkasse CSV-CAMT V2 first; ING, DKB … later).
 */
interface BankStatementParser
{
    /** Stable key sent by the app, e.g. "sparkasse_camt_v2" */
    public function key(): string;

    /** Label for the format picker */
    public function label(): string;

    /** Short how-to shown in the app before uploading */
    public function hint(): string;

    /**
     * @throws InvalidStatementException
     */
    public function parse(string $contents): ParsedStatement;
}
