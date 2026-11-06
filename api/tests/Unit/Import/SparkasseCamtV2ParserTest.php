<?php

use App\Services\Import\InvalidStatementException;
use App\Services\Import\Parsers\SparkasseCamtV2Parser;
use App\Services\Import\Transaction;

beforeEach(function () {
    $this->parser = new SparkasseCamtV2Parser;
});

it('reads only debits and skips cash withdrawals and credits', function () {
    $statement = $this->parser->parse(sparkasseFixture());

    expect($statement->rowsTotal)->toBe(98)
        // 98 rows − 10 salaries − 1 cash withdrawal
        ->and($statement->debits)->toHaveCount(87)
        ->and(collect($statement->debits)->every(fn (Transaction $t) => $t->amountCents > 0))->toBeTrue()
        ->and(collect($statement->debits)->pluck('bookingText')->unique()->values()->all())
        ->not->toContain('BARGELDAUSZAHLUNG', 'GUTSCHRIFT');
});

it('converts ISO-8859-1 to UTF-8 and parses German dates and amounts', function () {
    $debits = collect($this->parser->parse(sparkasseFixture())->debits);

    $rundfunk = $debits->first(fn (Transaction $t) => str_contains($t->payee, 'Rundfunk'));
    $fitx = $debits->filter(fn (Transaction $t) => str_starts_with($t->payee, 'FitX'))->sortBy('date')->last();

    expect($rundfunk->payee)->toBe('Rundfunk ARD, ZDF, Deutschlandradio Beitragsservice Köln')
        ->and($rundfunk->amountCents)->toBe(5508)
        ->and($rundfunk->creditorId)->toBe('DE48ZZZ00000000015')
        ->and($rundfunk->mandateRef)->toBe('RBN-123456789')
        ->and($fitx->date->toDateString())->toBe('2026-10-01')
        ->and($fitx->amountCents)->toBe(3290);
});

it('never reads account numbers', function () {
    $debits = $this->parser->parse(sparkasseFixture())->debits;
    $serialized = serialize($debits);

    expect($serialized)->not->toContain('DE0000000000000000000')
        ->and($serialized)->not->toContain('DE00999999990000000000')
        ->and($serialized)->not->toContain('TESTDEFFXXX');
});

it('finds columns by header name, regardless of order', function () {
    $csv = "\"Betrag\";\"Beguenstigter/Zahlungspflichtiger\";\"Buchungstag\"\n\"-1.234,56\";\"Foo GmbH\";\"31.12.2026\"\n";
    $t = $this->parser->parse($csv)->debits[0];

    expect($t->amountCents)->toBe(123456)
        ->and($t->date->toDateString())->toBe('2026-12-31')
        ->and($t->payee)->toBe('Foo GmbH');
});

it('accepts UTF-8 with BOM', function () {
    $csv = "\xEF\xBB\xBF\"Buchungstag\";\"Beguenstigter/Zahlungspflichtiger\";\"Betrag\"\n\"01.02.26\";\"Müller\";\"-5,00\"\n";

    expect($this->parser->parse($csv)->debits[0]->payee)->toBe('Müller');
});

it('rejects files in another format', function (string $csv) {
    $this->parser->parse($csv);
})->with([
    'wrong columns' => ["Datum,Text,Wert\n01.01.2026,Foo,-1\n"],
    'empty' => ["\"Buchungstag\";\"Beguenstigter/Zahlungspflichtiger\";\"Betrag\"\n"],
    'binary' => ["\x00\x01\x02PK"],
])->throws(InvalidStatementException::class);

it('skips malformed rows instead of failing', function () {
    $csv = "\"Buchungstag\";\"Beguenstigter/Zahlungspflichtiger\";\"Betrag\"\n\"31.02.26\";\"A\";\"-5,00\"\n\"01.02.26\";\"B\";\"abc\"\n\"01.02.26\";\"C\";\"-5,00\"\n";

    expect(array_map(fn ($t) => $t->payee, $this->parser->parse($csv)->debits))->toBe(['C']);
});
