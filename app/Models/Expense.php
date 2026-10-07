<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends TenantModel
{
    use Auditable, HasTags, SoftDeletes;

    protected function casts(): array
    {
        return ['expense_date' => \App\Casts\DateOnly::class, 'is_recurring' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function auditLabel(): string
    {
        return trim(($this->vendor ?: 'Expense').' '.$this->expense_date?->toDateString());
    }
}
