<?php

namespace App\Services;

use App\Support\Money;

/**
 * The single source of truth for document totals (invoices, quotes, recurring invoices).
 * The browser only shows a live preview; these numbers are always recalculated on the server.
 */
class InvoiceCalculator
{
    /**
     * @param  array  $items  each: quantity, unit_price, discount_percent, tax_rate, tax_name
     * @param  array  $options  discount_type (percent|fixed), discount_value, shipping, adjustment, tax_inclusive, tax_exempt, currency
     */
    public function calculate(array $items, array $options = []): array
    {
        $decimals = Money::currencyConfig($options['currency'] ?? 'USD')['decimals'];
        $inclusive = (bool) ($options['tax_inclusive'] ?? false);
        $exempt = (bool) ($options['tax_exempt'] ?? false);

        // 1. Line totals after the line discount
        $lines = [];
        $subtotal = 0;

        foreach (array_values($items) as $index => $item) {
            $gross = Money::mulDiv(Money::units($item['quantity'] ?? 0), Money::units($item['unit_price'] ?? 0), Money::SCALE);
            $discount = Money::percent($gross, $item['discount_percent'] ?? 0);
            $net = Money::roundTo($gross - $discount, $decimals);
            $lines[$index] = ['net' => $net, 'rate' => $exempt ? '0' : (string) ($item['tax_rate'] ?? 0), 'name' => $item['tax_name'] ?? null];
            $subtotal += $net;
        }

        // 2. Invoice-level discount
        $discountValue = Money::units($options['discount_value'] ?? 0);
        $discountTotal = ($options['discount_type'] ?? 'percent') === 'fixed'
            ? min(max($discountValue, 0), max($subtotal, 0))
            : Money::roundTo(Money::percent($subtotal, Money::decimal($discountValue)), $decimals);

        // 3. Tax per line on the discounted base, grouped per tax rate
        $taxTotal = 0;
        $taxes = [];
        $remainingDiscount = $discountTotal;
        $lastIndex = count($lines) - 1;

        foreach ($lines as $index => &$line) {
            // Spread the invoice discount over the lines; the last line takes the rounding remainder
            $share = $subtotal > 0 ? ($index === $lastIndex ? $remainingDiscount : Money::mulDiv($discountTotal, $line['net'], $subtotal)) : 0;
            $remainingDiscount -= $share;
            $base = $line['net'] - $share;
            $rateUnits = Money::units($line['rate']);

            if ($rateUnits <= 0) {
                $tax = 0;
            } elseif ($inclusive) {
                // Price already contains tax: tax = base * rate / (100 + rate)
                $tax = Money::mulDiv($base, $rateUnits, 100 * Money::SCALE + $rateUnits);
            } else {
                $tax = Money::mulDiv($base, $rateUnits, 100 * Money::SCALE);
            }

            $tax = Money::roundTo($tax, $decimals);
            $line['tax'] = $tax;
            $taxTotal += $tax;

            if ($tax !== 0) {
                $key = ($line['name'] ?? 'Tax').'|'.$line['rate'];
                $taxes[$key] ??= ['name' => $line['name'] ?? 'Tax', 'rate' => Money::decimal($rateUnits), 'amount' => 0, 'tax_rate_id' => $items[$index]['tax_rate_id'] ?? null];
                $taxes[$key]['amount'] += $tax;
            }
        }
        unset($line);

        $shipping = Money::roundTo(Money::units($options['shipping'] ?? 0), $decimals);
        $adjustment = Money::roundTo(Money::units($options['adjustment'] ?? 0), $decimals);
        $total = $subtotal - $discountTotal + ($inclusive ? 0 : $taxTotal) + $shipping + $adjustment;

        return [
            'lines' => array_map(fn ($line) => ['line_total' => Money::decimal($line['net']), 'tax_amount' => Money::decimal($line['tax'])], $lines),
            'taxes' => array_values(array_map(fn ($tax) => ['amount' => Money::decimal($tax['amount'])] + $tax, $taxes)),
            'subtotal' => Money::decimal($subtotal),
            'discount_total' => Money::decimal($discountTotal),
            'tax_total' => Money::decimal($taxTotal),
            'shipping' => Money::decimal($shipping),
            'adjustment' => Money::decimal($adjustment),
            'total' => Money::decimal($total),
        ];
    }
}
