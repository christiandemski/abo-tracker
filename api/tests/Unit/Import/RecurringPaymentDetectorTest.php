<?php

use App\Enums\IntervalUnit;
use App\Services\Import\DetectedPayment;
use App\Services\Import\Parsers\SparkasseCamtV2Parser;
use App\Services\Import\PayeeNormalizer;
use App\Services\Import\RecurringPaymentDetector;
use App\Services\Import\Transaction;
use Carbon\CarbonImmutable;

function tx(string $date, string $payee, int $cents, ?string $cid = null, ?string $mref = null, string $purpose = ''): Transaction
{
    return new Transaction(CarbonImmutable::parse($date), $payee, $purpose, $cents, $cid, $mref);
}

function detected(array $debits): array
{
    $result = (new RecurringPaymentDetector(new PayeeNormalizer))->detect($debits);

    return collect($result)->mapWithKeys(fn (DetectedPayment $d) => [$d->payeeNormalized => [
        $d->intervalUnit?->value, $d->intervalCount, $d->occurrences(), $d->amountCents(), $d->confidence,
    ]])->all();
}

describe('payee normalization', function () {
    it('normalizes payees', function (string $payee, string $purpose, string $expected) {
        expect((new PayeeNormalizer)->normalize($payee, $purpose))->toBe($expected);
    })->with([
        ['Spotify AB', '', 'SPOTIFY'],
        ['FitX Deutschland GmbH', '', 'FITX'],
        ['PayPal (Europe) S.a r.l. et Cie, S.C.A.', '1047 PP.4711.PP . Netflix International B.V., Ihr Einkauf bei Netflix International B.V.', 'NETFLIX INTERNATIONAL'],
        ['SEPA-LASTSCHRIFT Telekom Deutschland GmbH', '', 'TELEKOM'],
        ['1&1 Telecom GmbH', '', '1&1 TELECOM'],
        ['Allianz Versicherungs-AG 4711-2026', '', 'ALLIANZ VERSICHERUNGS'],
        ['Müller', '', 'MUELLER'],
    ]);
});

describe('fixture export (Jan–Oct 2026)', function () {
    beforeEach(function () {
        $this->result = detected((new SparkasseCamtV2Parser)->parse(sparkasseFixture())->debits);
    });

    it('detects the subscriptions with rhythm and confidence', function () {
        expect($this->result)->toMatchArray([
            'SPOTIFY' => ['month', 1, 10, 1099, 'high'],
            'NETFLIX INTERNATIONAL' => ['month', 1, 10, 1399, 'high'],
            'DISNEY PLUS' => ['month', 1, 10, 999, 'high'],
            'RUNDFUNK ARD ZDF DEUTSCHLANDRADIO BEITRAGSSERVICE KOELN' => ['month', 3, 4, 5508, 'high'],
            // single direct debit → yearly guess, "Rhythmus prüfen"
            'ALLIANZ VERSICHERUNGS' => ['year', 1, 1, 8940, 'check'],
        ]);
    });

    it('keeps a contract together across a price change and reports the latest price', function () {
        expect($this->result['FITX'])->toBe(['month', 1, 10, 3290, 'high']);
    });

    it('ignores groceries and one-off PayPal purchases', function () {
        expect(array_keys($this->result))->not->toContain('REWE MARKT', 'ZALANDO')
            ->and($this->result)->toHaveCount(6);
    });
});

describe('rhythms', function () {
    it('uses the median gap', function () {
        $r = detected([
            tx('2026-01-31', 'Foo', 500), tx('2026-02-28', 'Foo', 500), tx('2026-03-31', 'Foo', 500), tx('2026-04-30', 'Foo', 500),
        ]);
        expect($r['FOO'])->toBe(['month', 1, 4, 500, 'high']);
    });

    it('marks two bookings as "check"', function () {
        $r = detected([tx('2026-01-10', 'Foo', 500), tx('2026-02-10', 'Foo', 500)]);
        expect($r['FOO'][4])->toBe('check');
    });

    it('detects half-yearly and yearly', function () {
        $r = detected([
            tx('2025-01-05', 'Half', 3000), tx('2025-07-05', 'Half', 3000), tx('2026-01-05', 'Half', 3000),
            tx('2024-03-01', 'Year', 6000), tx('2025-03-01', 'Year', 6000), tx('2026-03-02', 'Year', 6000),
        ]);
        expect($r['HALF'])->toBe(['month', 6, 3, 3000, 'high'])
            ->and($r['YEAR'])->toBe(['year', 1, 3, 6000, 'high']);
    });

    it('splits the same payee by amount (±10 %)', function () {
        $r = (new RecurringPaymentDetector(new PayeeNormalizer))->detect([
            tx('2026-01-01', 'Amazon', 899), tx('2026-02-01', 'Amazon', 899), tx('2026-03-01', 'Amazon', 899),
            tx('2026-01-15', 'Amazon', 299), tx('2026-02-15', 'Amazon', 299), tx('2026-03-15', 'Amazon', 299),
        ]);
        expect(collect($r)->map(fn (DetectedPayment $d) => $d->amountCents())->sort()->values()->all())->toBe([299, 899]);
    });

    it('drops irregular payments without a mandate but keeps mandates for review', function () {
        $r = detected([
            tx('2026-01-01', 'Bakery', 500), tx('2026-01-20', 'Bakery', 500), tx('2026-03-10', 'Bakery', 500),
            tx('2026-01-01', 'Verein', 2400, 'DE11ZZZ1', 'M-1'), tx('2026-05-01', 'Verein', 2400, 'DE11ZZZ1', 'M-1'),
        ]);
        expect($r)->not->toHaveKey('BAKERY')
            ->and($r['VEREIN'])->toBe([null, null, 2, 2400, 'check']);
    });

    it('ignores single bookings without a mandate', function () {
        expect(detected([tx('2026-01-01', 'Shop', 4999)]))->toBe([]);
    });

    it('returns the interval as enum', function () {
        $d = (new RecurringPaymentDetector(new PayeeNormalizer))->detect([
            tx('2026-01-10', 'Foo', 500), tx('2026-02-10', 'Foo', 500), tx('2026-03-10', 'Foo', 500),
        ])[0];
        expect($d->intervalUnit)->toBe(IntervalUnit::Month);
    });
});
