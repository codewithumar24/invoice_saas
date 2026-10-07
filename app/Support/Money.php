<?php

namespace App\Support;

/**
 * Money maths without floating point.
 * Every amount is converted to an integer number of 1/10000 units ("units"),
 * which matches the DECIMAL(19,4) columns used in the database.
 */
class Money
{
    public const SCALE = 10000;

    /** Convert "12.5", 12.5 or 12 to integer units (125000). */
    public static function units(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        $value = is_float($amount) ? number_format($amount, 6, '.', '') : trim((string) $amount);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        if (! preg_match('/^\d*(\.\d*)?$/', $value)) {
            return 0;
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = str_pad($fraction, 5, '0');
        $units = ((int) $whole) * self::SCALE + (int) substr($fraction, 0, 4);

        // Round half up on the fifth decimal
        if ((int) $fraction[4] >= 5) {
            $units++;
        }

        return $negative ? -$units : $units;
    }

    /** Convert integer units back to a decimal string with 4 places. */
    public static function decimal(int $units): string
    {
        $sign = $units < 0 ? '-' : '';
        $units = abs($units);

        return $sign.intdiv($units, self::SCALE).'.'.str_pad((string) ($units % self::SCALE), 4, '0', STR_PAD_LEFT);
    }

    /** a * b / divisor with half-up rounding, safe against integer overflow. */
    public static function mulDiv(int $a, int $b, int $divisor): int
    {
        $product = $a * $b;

        if (is_float($product)) {
            // Too large for 64-bit integers: use bcmath when available
            if (function_exists('bcmul')) {
                $scaled = bcdiv(bcmul((string) $a, (string) $b), (string) $divisor, 1);

                return (int) round((float) $scaled);
            }

            return (int) round($product / $divisor);
        }

        $negative = $product < 0;
        $product = abs($product);
        $result = intdiv($product, $divisor);

        if (($product % $divisor) * 2 >= $divisor) {
            $result++;
        }

        return $negative ? -$result : $result;
    }

    /** Round units to the number of decimals a currency uses (e.g. 2 for USD, 0 for JPY). */
    public static function roundTo(int $units, int $decimals): int
    {
        $step = (int) (self::SCALE / (10 ** min($decimals, 4)));

        return self::mulDiv($units, 1, $step) * $step;
    }

    public static function percent(int $units, string|int|float $percent): int
    {
        return self::mulDiv($units, self::units($percent), 100 * self::SCALE);
    }

    public static function currencyConfig(string $currency): array
    {
        return config('currencies.'.strtoupper($currency), [
            'name' => strtoupper($currency), 'symbol' => strtoupper($currency).' ',
            'decimals' => 2, 'thousands' => ',', 'decimal' => '.',
        ]);
    }

    /** Format an amount for display using the currency's own configuration. */
    public static function format(string|int|float|null $amount, ?string $currency = null, bool $withCode = false): string
    {
        $currency = strtoupper($currency ?: app(TenantContext::class)->currency());
        $config = self::currencyConfig($currency);
        $units = self::roundTo(self::units($amount), $config['decimals']);
        $negative = $units < 0;
        $units = abs($units);

        $whole = number_format(intdiv($units, self::SCALE), 0, '', $config['thousands']);
        $fraction = substr(str_pad((string) ($units % self::SCALE), 4, '0', STR_PAD_LEFT), 0, $config['decimals']);
        $number = $config['decimals'] > 0 ? $whole.$config['decimal'].$fraction : $whole;

        $formatted = $withCode ? $currency.' '.$number : $config['symbol'].$number;

        return $negative ? '-'.$formatted : $formatted;
    }
}
