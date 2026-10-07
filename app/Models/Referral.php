<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $guarded = ['id'];

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'referred_tenant_id');
    }
}
