<?php

namespace Tests\Unit;

use App\Services\InvoiceCalculator;
use Tests\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    private function calculate(array $items, array $options = []): array
    {
        return (new InvoiceCalculator)->calculate($items, $options + ['currency' => 'USD']);
    }

    public function test_subtotal_line_discount_and_exclusive_tax(): void
    {
        $totals = $this->calculate([
            ['quantity' => 2, 'unit_price' => 100, 'discount_percent' => 10, 'tax_rate' => 20, 'tax_name' => 'VAT'], // 180 + 36
            ['quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0],
        ]);

        $this->assertSame('230.0000', $totals['subtotal']);
        $this->assertSame('36.0000', $totals['tax_total']);
        $this->assertSame('266.0000', $totals['total']);
        $this->assertSame('180.0000', $totals['lines'][0]['line_total']);
        $this->assertCount(1, $totals['taxes']);
    }

    public function test_invoice_discount_is_applied_before_tax(): void
    {
        $totals = $this->calculate([['quantity' => 1, 'unit_price' => 200, 'tax_rate' => 10]], ['discount_type' => 'percent', 'discount_value' => 25]);

        $this->assertSame('50.0000', $totals['discount_total']);
        $this->assertSame('15.0000', $totals['tax_total']); // 10% of 150, not of 200
        $this->assertSame('165.0000', $totals['total']);
    }

    public function test_fixed_discount_cannot_exceed_the_subtotal(): void
    {
        $totals = $this->calculate([['quantity' => 1, 'unit_price' => 40]], ['discount_type' => 'fixed', 'discount_value' => 100]);

        $this->assertSame('40.0000', $totals['discount_total']);
        $this->assertSame('0.0000', $totals['total']);
    }

    public function test_tax_inclusive_prices_do_not_add_tax_on_top(): void
    {
        $totals = $this->calculate([['quantity' => 1, 'unit_price' => 120, 'tax_rate' => 20]], ['tax_inclusive' => true]);

        $this->assertSame('20.0000', $totals['tax_total']);
        $this->assertSame('120.0000', $totals['total']);
    }

    public function test_shipping_adjustment_and_tax_exemption(): void
    {
        $totals = $this->calculate([['quantity' => 3, 'unit_price' => '19.99', 'tax_rate' => 15]], ['shipping' => 10, 'adjustment' => -0.97, 'tax_exempt' => true]);

        $this->assertSame('59.9700', $totals['subtotal']);
        $this->assertSame('0.0000', $totals['tax_total']);
        $this->assertSame('69.0000', $totals['total']);
    }

    public function test_amounts_are_rounded_to_the_currency_decimals(): void
    {
        $totals = $this->calculate([['quantity' => 3, 'unit_price' => '33.333', 'tax_rate' => 7.5]], ['currency' => 'JPY']);

        $this->assertSame('100.0000', $totals['subtotal']);
        $this->assertSame('8.0000', $totals['tax_total']);
    }
}
