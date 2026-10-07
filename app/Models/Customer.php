<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends TenantModel
{
    use Auditable, HasCustomFields, HasTags, SoftDeletes;

    protected $hidden = ['portal_password', 'portal_invite_token'];

    protected function casts(): array
    {
        return [
            'portal_enabled' => 'boolean',
            'portal_password' => 'hashed',
            'last_activity_at' => 'datetime',
        ];
    }

    public static function customFieldEntity(): string
    {
        return 'customer';
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function customerNotes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CustomerMessage::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function billingAddress(): ?CustomerAddress
    {
        return $this->addresses->firstWhere('type', 'billing') ?? $this->addresses->first();
    }

    public function displayName(): string
    {
        return $this->company ? "{$this->name} ({$this->company})" : $this->name;
    }
}
