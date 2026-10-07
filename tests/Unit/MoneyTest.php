<?php

namespace Tests\Unit;

use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_converts_decimals_to_units_and_back_without_float_errors(): void
    {
        $this->assertSame(1000, Money::units('0.1'));
        $this->assertSame(3000, Money::units('0.1') + Money::units('0.2'));
        $this->assertSame('0.3000', Money::decimal(Money::units('0.1') + Money::units('0.2')));
        $this->assertSame('-12.5000', Money::decimal(Money::units('-12.5')));
        $this->assertSame(0, Money::units(null));
        $this->assertSame(0, Money::units('not a number'));
    }

    public function test_it_rounds_half_up(): void
    {
        $this->assertSame(1235, Money::units('0.12345'));
        $this->assertSame(10100, Money::roundTo(Money::units('1.005'), 2));
        $this->assertSame(Money::units('3'), Money::roundTo(Money::units('2.5'), 0));
    }

    public function test_percent_and_muldiv(): void
    {
        $this->assertSame(Money::units('17'), Money::percent(Money::units('200'), '8.5'));
        $this->assertSame(Money::units('33.3333'), Money::mulDiv(Money::units('100'), 1, 3));
    }

    public function test_it_formats_each_currency_with_its_own_rules(): void
    {
        $this->assertSame('$1,250.00', Money::format('1250', 'USD'));
        $this->assertSame('USD 1,250.00', Money::format('1250', 'USD', true));
        $this->assertSame('EUR 1.250,00', Money::format('1250', 'EUR', true));
        $this->assertSame('PKR 125,000', Money::format('125000', 'PKR', true));
        $this->assertSame('-$5.50', Money::format('-5.5', 'USD'));
    }
}
