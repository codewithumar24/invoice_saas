<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled_modules' => 'array',
            'onboarded_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('is_owner', 'status', 'branch_id')->withTimestamps();
    }

    public function owner(): ?User
    {
        return $this->users()->wherePivot('is_owner', true)->first();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->withoutGlobalScope('tenant')->latestOfMany();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->withoutGlobalScope('tenant');
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->enabled_modules ?? [], true);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->city, $this->state, $this->postal_code, $this->country])->filter()->implode(', ');
    }
}
