<?php

namespace App\Models;

class CustomField extends TenantModel
{
    protected function casts(): array
    {
        return ['options' => 'array', 'is_required' => 'boolean', 'show_on_pdf' => 'boolean'];
    }
}
