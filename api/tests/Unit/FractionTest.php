<?php

use App\Support\Fraction;

it('normalizes and adds exactly', function () {
    $sum = (new Fraction(1, 3))->add(new Fraction(1, 6));

    expect([$sum->numerator, $sum->denominator])->toBe([1, 2]);
});

it('rounds half away from zero', function (int $num, int $den, int $expected) {
    expect((new Fraction($num, $den))->round())->toBe($expected);
})->with([
    [1, 2, 1],
    [3, 2, 2],
    [5, 2, 3],
    [1, 3, 0],
    [2, 3, 1],
    [-1, 2, -1],
    [-1, 3, 0],
    [1199, 1, 1199],
]);

it('keeps sums exact until the final rounding', function () {
    // three yearly subscriptions of 1,00 € → 3 × 8,333… ct per month = 25 ct (not 3 × 8 = 24)
    $parts = array_fill(0, 3, new Fraction(100, 12));

    expect(Fraction::sum($parts)->round())->toBe(25)
        ->and(array_sum(array_map(fn (Fraction $f) => $f->round(), $parts)))->toBe(24);
});

it('rejects a zero denominator', function () {
    new Fraction(1, 0);
})->throws(InvalidArgumentException::class);
