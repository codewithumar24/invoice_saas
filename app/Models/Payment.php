<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends TenantModel
{
    use Auditable;

    public const STATUSES = ['pending', 'processing', 'paid', 'failed', 'refunded', 'partially_refunded'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_payments')->withPivot('amount')->withTimestamps();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function refundable(): string
    {
        return \App\Support\Money::decimal(\App\Support\Money::units($this->amount) - \App\Support\Money::units($this->refunded_amount));
    }
}
