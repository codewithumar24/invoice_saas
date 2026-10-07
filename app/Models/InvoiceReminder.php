<?php

namespace App\Models;

class InvoiceReminder extends TenantModel
{
    protected function casts(): array
    {
        return ['sent_on' => \App\Casts\DateOnly::class];
    }
}
