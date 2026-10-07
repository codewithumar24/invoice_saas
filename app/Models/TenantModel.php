<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for every tenant-owned record.
 * tenant_id is guarded, so it can never be set through request input.
 */
abstract class TenantModel extends Model
{
    use BelongsToTenant;

    protected $guarded = ['id', 'tenant_id'];
}
