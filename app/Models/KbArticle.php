<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbArticle extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }
}
