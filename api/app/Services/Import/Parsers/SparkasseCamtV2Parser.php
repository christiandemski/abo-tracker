<?php

namespace App\Services\Import\Parsers;

use App\Services\Import\BankStatementParser;
use App\Services\Import\InvalidStatementException;
use App\Services\Import\ParsedStatement;
use App\Services\Import\Transaction;
use Carbon\CarbonImmutable;

/**
 * Sparkasse online banking: Umsätze → Export "CSV-CAMT V2".
 *
 * Columns are located by their header names, not by position, so small changes in the
 * export (extra or reordered columns) do not break the import. Typical header:
 * "Auftragskonto";"Buchungstag";"Valutadatum";"Buchungstext";"Verwendungszweck";"Glaeubiger ID";
 * "Mandatsreferenz";…;"Beguenstigter/Zahlungspflichtiger";"Kontonummer/IBAN";"BIC (SWIFT-Code)";
 * "Betrag";"Waehrung";"Info"
 *
 * NOTE: built against a synthetic fixture; to be verified with a real anonymized export (SPEC).
 */
class SparkasseCamtV2Parser implements BankStatementParser
{
    /** normalized header → field; first match wins */
    private const COLUMNS = [
        'date' => ['buchungstag'],
        'booking_text' => ['buchungstext'],
        'purpose' => ['verwendungszweck'],
        'creditor_id' => ['glaeubigerid', 'glaubigerid'],
        'mandate_ref' => ['mandatsreferenz'],
        'payee' => ['beguenstigterzahlungspflichtiger', 'begunstigterzahlungspflichtiger', 'empfaenger'],
        'amount' => ['betrag'],
        'currency' => ['waehrung', 'wahrung'],
    ];

    private const REQUIRED = ['date', 'payee', 'amount'];

    /** Booking texts that are never subscriptions */
    private const SKIP_BOOKING_TEXTS = ['BARGELD', 'GELDAUTOMAT', 'AUSZAHLUNG', 'ABSCHLUSS', 'ZINSEN', 'ENTGELT'];

    public function key(): string
    {
        return 'sparkasse_camt_v2';
    }

    public function label(): string
    {
        return 'Sparkasse (CSV-CAMT V2)';
    }

    public function hint(): string
    {
        return 'Im Online-Banking unter „Umsätze“ den Zeitraum wählen (am besten ab Januar) und als „CSV-CAMT V2“ exportieren.';
    }

    public function parse(string $contents): ParsedStatement
    {
        $contents = $this->toUtf8($contents);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle, null, ';', '"', '');
        $map = $header ? $this->mapHeader($header) : [];

        foreach (self::REQUIRED as $field) {
            if (! isset($map[$field])) {
                fclose($handle);
                throw new InvalidStatementException(
                    'Die Datei sieht nicht wie ein CSV-CAMT-V2-Export der Sparkasse aus. Prüf bitte das Exportformat.'
                );
            }
        }

        $rows = 0;
        $debits = [];

        while (($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
            if ($row === [null] || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $rows++;

            $amount = $this->parseAmount($row[$map['amount']] ?? '');
            $date = $this->parseDate($row[$map['date']] ?? '');
            if ($amount === null || $date === null || $amount >= 0) {
                continue; // only debits
            }

            $currency = strtoupper(trim($this->cell($row, $map, 'currency')));
            if ($currency !== '' && $currency !== 'EUR') {
                continue;
            }

            $bookingText = strtoupper(trim($this->cell($row, $map, 'booking_text')));
            if ($this->isSkipped($bookingText)) {
                continue;
            }

            $debits[] = new Transaction(
                date: $date,
                payee: trim($row[$map['payee']] ?? ''),
                purpose: trim($this->cell($row, $map, 'purpose')),
                amountCents: -$amount,
                creditorId: $this->nullable($this->cell($row, $map, 'creditor_id')),
                mandateRef: $this->nullable($this->cell($row, $map, 'mandate_ref')),
                bookingText: $bookingText,
            );
        }

        fclose($handle);

        if ($rows === 0) {
            throw new InvalidStatementException('Die Datei enthält keine Umsätze.');
        }

        return new ParsedStatement($debits, $rows);
    }

    /**
     * Sparkasse exports are usually ISO-8859-1 / Windows-1252.
     */
    private function toUtf8(string $contents): string
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        return str_replace(["\r\n", "\r"], "\n", $contents);
    }

    /**
     * @param  list<?string>  $header
     * @return array<string, int>
     */
    private function mapHeader(array $header): array
    {
        $normalized = array_map(fn ($h) => $this->normalizeHeader((string) $h), $header);
        $map = [];

        foreach (self::COLUMNS as $field => $names) {
            foreach ($normalized as $index => $name) {
                if (in_array($name, $names, true)) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    private function normalizeHeader(string $header): string
    {
        $h = mb_strtolower(trim($header));
        $h = strtr($h, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        return preg_replace('/[^a-z]/', '', $h) ?? '';
    }

    /** "-16,99" / "-1.234,56" → cents */
    private function parseAmount(string $value): ?int
    {
        $v = str_replace([' ', "\u{00A0}", '€'], '', trim($value));
        if (! preg_match('/^([+-]?)(\d{1,3}(?:\.\d{3})*|\d+)(?:,(\d{1,2}))?$/', $v, $m)) {
            return null;
        }
        $cents = (int) str_replace('.', '', $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$cents : $cents;
    }

    /** "05.11.26" or "05.11.2026" */
    private function parseDate(string $value): ?CarbonImmutable
    {
        $v = trim($value);
        if (! preg_match('/^(\d{2})\.(\d{2})\.(\d{2}|\d{4})$/', $v, $m)) {
            return null;
        }
        $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
        if (! checkdate((int) $m[2], (int) $m[1], $year)) {
            return null;
        }

        return CarbonImmutable::create($year, (int) $m[2], (int) $m[1])->startOfDay();
    }

    private function isSkipped(string $bookingText): bool
    {
        foreach (self::SKIP_BOOKING_TEXTS as $skip) {
            if (str_contains($bookingText, $skip)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<?string>  $row
     * @param  array<string, int>  $map
     */
    private function cell(array $row, array $map, string $field): string
    {
        return isset($map[$field]) ? (string) ($row[$map[$field]] ?? '') : '';
    }

    private function nullable(?string $value): ?string
    {
        $v = trim((string) $value);

        return $v === '' ? null : mb_substr($v, 0, 35);
    }
}
