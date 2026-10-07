<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    protected $guarded = ['id'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
