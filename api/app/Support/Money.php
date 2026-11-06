<?php

namespace App\Support;

/** German display format for cents: 123456 → "1.234,56 €". */
final class Money
{
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.number_format(intdiv($cents, 100), 0, ',', '.').','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT).' €';
    }
}
