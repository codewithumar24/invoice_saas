<?php

namespace App\Services;

use App\Models\UsageRecord;
use App\Support\TenantContext;

/** Usage metering per workspace and month (invoices created, emails sent, storage, API calls...). */
class UsageService
{
    public function __construct(private TenantContext $context) {}

    public function increment(string $metric, int $quantity = 1): void
    {
        if (! $this->context->check()) {
            return;
        }

        $record = UsageRecord::firstOrCreate(['metric' => $metric, 'period' => now()->format('Y-m')]);
        $record->increment('quantity', $quantity);
    }

    public function current(string $metric): int
    {
        return (int) UsageRecord::where('metric', $metric)->where('period', now()->format('Y-m'))->value('quantity');
    }

    public function total(string $metric): int
    {
        return (int) UsageRecord::where('metric', $metric)->sum('quantity');
    }
}
