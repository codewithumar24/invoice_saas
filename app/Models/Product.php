<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasCustomFields;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends TenantModel
{
    use Auditable, HasCustomFields, HasTags, SoftDeletes;

    protected function casts(): array
    {
        return ['track_inventory' => 'boolean'];
    }

    public static function customFieldEntity(): string
    {
        return 'product';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stock(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->latest();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('type', 'product')->where('track_inventory', true)->where('status', 'active')
            ->whereColumn('stock_quantity', '<=', 'minimum_stock');
    }

    public function isService(): bool
    {
        return $this->type === 'service';
    }

    public function isLowStock(): bool
    {
        return $this->track_inventory && (float) $this->stock_quantity <= (float) $this->minimum_stock;
    }
}
