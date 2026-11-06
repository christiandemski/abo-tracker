<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact rational number for money conversions (cents). Sums stay exact;
 * rounding happens once, when a value is displayed.
 */
final readonly class Fraction
{
    public int $numerator;

    public int $denominator;

    public function __construct(int $numerator, int $denominator = 1)
    {
        if ($denominator === 0) {
            throw new InvalidArgumentException('Denominator must not be zero.');
        }
        if ($denominator < 0) {
            $numerator = -$numerator;
            $denominator = -$denominator;
        }
        $gcd = self::gcd(abs($numerator), $denominator);
        $this->numerator = intdiv($numerator, $gcd);
        $this->denominator = intdiv($denominator, $gcd);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        return new self(
            $this->numerator * $other->denominator + $other->numerator * $this->denominator,
            $this->denominator * $other->denominator,
        );
    }

    public function multiply(int $numerator, int $denominator = 1): self
    {
        return new self($this->numerator * $numerator, $this->denominator * $denominator);
    }

    /**
     * Commercial rounding (half away from zero) to whole cents.
     */
    public function round(): int
    {
        $doubled = 2 * abs($this->numerator) + $this->denominator;
        $rounded = intdiv($doubled, 2 * $this->denominator);

        return $this->numerator < 0 ? -$rounded : $rounded;
    }

    public function subtract(self $other): self
    {
        return $this->add($other->multiply(-1));
    }

    /** Smallest integer ≥ the value. */
    public function ceil(): int
    {
        $quotient = intdiv($this->numerator, $this->denominator);

        return $this->numerator > 0 && $this->numerator % $this->denominator !== 0 ? $quotient + 1 : $quotient;
    }

    public function compare(self $other): int
    {
        return ($this->numerator * $other->denominator) <=> ($other->numerator * $this->denominator);
    }

    public function isNegative(): bool
    {
        return $this->numerator < 0;
    }

    public function isZero(): bool
    {
        return $this->numerator === 0;
    }

    /**
     * @param  iterable<self>  $fractions
     */
    public static function sum(iterable $fractions): self
    {
        $total = self::zero();
        foreach ($fractions as $fraction) {
            $total = $total->add($fraction);
        }

        return $total;
    }

    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return max($a, 1);
    }
}
