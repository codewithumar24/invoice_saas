<?php

namespace App\Services;

use App\Models\TaxRate;
use Illuminate\Support\Collection;

/** Tax configuration of the workspace. Country-specific tax modules can extend this class later. */
class TaxService
{
    public function __construct(private SettingsService $settings) {}

    public function activeRates(): Collection
    {
        return TaxRate::where('is_active', true)->orderBy('name')->get();
    }

    public function defaultRate(): ?TaxRate
    {
        return TaxRate::where('is_active', true)->where('is_default', true)->first();
    }

    public function pricesIncludeTax(): bool
    {
        return $this->settings->get('tax_system') === 'inclusive';
    }

    public function isExempt(): bool
    {
        return (bool) $this->settings->get('tax_exempt') || $this->settings->get('tax_system') === 'none';
    }

    /** Fill tax_rate and tax_name on each line from its tax_rate_id. */
    public function resolveLines(array $items): array
    {
        $rates = TaxRate::whereIn('id', collect($items)->pluck('tax_rate_id')->filter()->unique())->get()->keyBy('id');

        return array_map(function ($item) use ($rates) {
            $rate = $rates->get($item['tax_rate_id'] ?? null);
            $item['tax_rate_id'] = $rate?->id;
            $item['tax_rate'] = $rate ? (string) $rate->rate : '0';
            $item['tax_name'] = $rate?->name;

            return $item;
        }, $items);
    }
}
