<?php

namespace App\Support;

/**
 * Money helpers. Storage is always integer cents + explicit currency;
 * these helpers are the only place dollars<->cents conversion happens.
 */
final class Money
{
    /** "9500.00" / "9,500" / 9500 → integer cents; blank/invalid → null. */
    public static function toCents(string|int|float|null $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace([',', ' ', '$'], '', (string) $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) round(((float) $value) * 100);
    }

    /** 950000 → "9,500" (whole units; cents shown only when non-zero). */
    public static function format(int $cents): string
    {
        $units = $cents / 100;

        return $cents % 100 === 0
            ? number_format($units, 0)
            : number_format($units, 2);
    }

    /** Convert between TTD and USD using the admin-set TTD-per-USD rate. */
    public static function convert(int $cents, string $from, string $to, float $usdToTtd): ?int
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $cents;
        }

        if ($usdToTtd <= 0) {
            return null;
        }

        return match ([$from, $to]) {
            ['USD', 'TTD'] => (int) round($cents * $usdToTtd),
            ['TTD', 'USD'] => (int) round($cents / $usdToTtd),
            default => null,
        };
    }
}
