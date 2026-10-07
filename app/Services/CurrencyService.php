<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Support\TenantContext;

class CurrencyService
{
    public function __construct(private TenantContext $context) {}

    /**
     * Rate that converts 1 unit of $currency into the workspace base currency.
     * The rate is copied onto each document when it is created and never recalculated afterwards.
     */
    public function rate(string $currency, ?string $date = null): string
    {
        $currency = strtoupper($currency);

        if ($currency === strtoupper($this->context->currency())) {
            return '1';
        }

        $rate = ExchangeRate::where('currency', $currency)
            ->whereDate('effective_date', '<=', $date ?? now()->toDateString())
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->value('rate');

        return $rate !== null ? (string) $rate : '1';
    }

    /** [code => name] of all configured currencies. */
    public function options(): array
    {
        return collect(config('currencies'))->map(fn ($config, $code) => $code.' — '.$config['name'])->all();
    }
}
