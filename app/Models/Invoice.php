<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends TenantModel
{
    use Auditable, HasCustomFields, HasTags, SoftDeletes;

    public const STATUSES = ['draft', 'sent', 'viewed', 'partially_paid', 'paid', 'overdue', 'cancelled'];

    /** Statuses in which money is still expected from the customer. */
    public const OPEN = ['sent', 'viewed', 'partially_paid', 'overdue'];

    public const TEMPLATES = ['classic', 'modern', 'minimal', 'professional', 'compact'];

    protected $hidden = ['public_token'];

    protected function casts(): array
    {
        return [
            'issue_date' => \App\Casts\DateOnly::class,
            'due_date' => \App\Casts\DateOnly::class,
            'tax_inclusive' => 'boolean',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function customFieldEntity(): string
    {
        return 'invoice';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(InvoiceTax::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'invoice_payments')->withPivot('amount')->withTimestamps();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN)->whereDate('due_date', '<', today_local()->toDateString());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, self::OPEN, true) && $this->due_date->lt(today_local());
    }

    public function isEditable(): bool
    {
        return ! in_array($this->status, ['paid', 'cancelled'], true);
    }

    /** Status to show: an open invoice past its due date is always shown as overdue. */
    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(today_local()) : 0;
    }

    public function publicUrl(): string
    {
        return route('public.invoice.show', $this->public_token);
    }
}
