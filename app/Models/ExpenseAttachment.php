<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseAttachment extends TenantModel
{
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
