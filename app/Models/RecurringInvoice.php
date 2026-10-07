<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class RecurringInvoice extends TenantModel
{
    public const FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'yearly', 'custom'];

    protected function casts(): array
    {
        return [
            'start_date' => \App\Casts\DateOnly::class, 'end_date' => \App\Casts\DateOnly::class, 'next_run_date' => \App\Casts\DateOnly::class, 'last_run_date' => \App\Casts\DateOnly::class,
            'auto_send' => 'boolean', 'items' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function nextDateAfter(Carbon $date): Carbon
    {
        return match ($this->frequency) {
            'weekly' => $date->copy()->addWeek(),
            'quarterly' => $date->copy()->addMonthsNoOverflow(3),
            'yearly' => $date->copy()->addYearNoOverflow(),
            'custom' => $date->copy()->addDays(max(1, (int) $this->interval_days)),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }
}
