<?php

namespace App\Services;

use App\Events\StockLow;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private FeatureService $features) {}

    public function defaultLocation(): InventoryLocation
    {
        return InventoryLocation::where('is_default', true)->first()
            ?? InventoryLocation::first()
            ?? InventoryLocation::create(['name' => __('Main warehouse'), 'type' => 'warehouse', 'is_default' => true]);
    }

    /**
     * Record one stock movement. $quantity is signed: positive adds stock, negative removes it.
     * The stock row is locked so two simultaneous sales cannot both read the same quantity.
     */
    public function move(Product $product, int $locationId, string $type, string $quantity, array $options = []): InventoryMovement
    {
        $wasLow = $product->isLowStock();

        $movement = DB::transaction(function () use ($product, $locationId, $type, $quantity, $options) {
            $this->changeStock($product, $locationId, Money::units($quantity));

            $movement = InventoryMovement::create([
                'product_id' => $product->id,
                'location_id' => $locationId,
                'type' => $type,
                'quantity' => Money::decimal(Money::units($quantity)),
                'unit_cost' => $options['unit_cost'] ?? $product->purchase_price,
                'reference_type' => isset($options['reference']) ? $options['reference']->getMorphClass() : null,
                'reference_id' => isset($options['reference']) ? $options['reference']->getKey() : null,
                'notes' => $options['notes'] ?? null,
                'user_id' => auth()->id(),
            ]);

            $this->syncProductTotal($product);

            return $movement;
        });

        if (! $wasLow && $product->refresh()->isLowStock()) {
            StockLow::dispatch($product);
        }

        return $movement;
    }

    public function transfer(Product $product, int $fromLocationId, int $toLocationId, string $quantity, ?string $notes = null): InventoryMovement
    {
        $units = abs(Money::units($quantity));

        if ($fromLocationId === $toLocationId) {
            throw ValidationException::withMessages(['to_location_id' => __('Choose a different destination location.')]);
        }

        return DB::transaction(function () use ($product, $fromLocationId, $toLocationId, $units, $notes) {
            $available = Money::units(InventoryStock::where('product_id', $product->id)->where('location_id', $fromLocationId)->value('quantity'));

            if ($available < $units) {
                throw ValidationException::withMessages(['quantity' => __('Only :quantity in stock at the source location.', ['quantity' => (float) Money::decimal($available)])]);
            }

            $this->changeStock($product, $fromLocationId, -$units);
            $this->changeStock($product, $toLocationId, $units);

            return InventoryMovement::create([
                'product_id' => $product->id, 'location_id' => $fromLocationId, 'to_location_id' => $toLocationId,
                'type' => 'transfer', 'quantity' => Money::decimal($units), 'unit_cost' => $product->purchase_price,
                'notes' => $notes, 'user_id' => auth()->id(),
            ]);
        });
    }

    /** Make stock match what a (non-draft, non-cancelled) invoice has sold. Safe to call repeatedly. */
    public function applyInvoice(Invoice $invoice): void
    {
        $this->reconcileInvoice($invoice, false);
    }

    /** Put the stock of an invoice back (cancelled invoice). */
    public function reverseInvoice(Invoice $invoice): void
    {
        $this->reconcileInvoice($invoice, true);
    }

    private function reconcileInvoice(Invoice $invoice, bool $reverse): void
    {
        if (! $this->features->enabled('inventory')) {
            return;
        }

        $wanted = [];

        if (! $reverse) {
            foreach ($invoice->items()->whereNotNull('product_id')->get() as $item) {
                $wanted[$item->product_id] = ($wanted[$item->product_id] ?? 0) - Money::units($item->quantity);
            }
        }

        $current = InventoryMovement::where('reference_type', $invoice->getMorphClass())->where('reference_id', $invoice->id)
            ->get()->groupBy('product_id')->map(fn ($rows) => $rows->sum(fn ($row) => Money::units($row->quantity)));

        $productIds = array_unique(array_merge(array_keys($wanted), $current->keys()->all()));
        $products = Product::withTrashed()->whereIn('id', $productIds)->where('track_inventory', true)->get();
        $locationId = null;

        foreach ($products as $product) {
            $delta = ($wanted[$product->id] ?? 0) - ($current[$product->id] ?? 0);

            if ($delta === 0) {
                continue;
            }

            $locationId ??= $this->defaultLocation()->id;
            $this->move($product, $locationId, $delta < 0 ? 'sale' : 'return', Money::decimal($delta), [
                'reference' => $invoice, 'unit_cost' => $product->purchase_price,
                'notes' => __('Invoice :number', ['number' => $invoice->number]),
            ]);
        }
    }

    private function changeStock(Product $product, int $locationId, int $units): void
    {
        DB::table('inventory_stock')->insertOrIgnore([
            'tenant_id' => $product->tenant_id, 'product_id' => $product->id, 'location_id' => $locationId,
            'quantity' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $stock = InventoryStock::where('product_id', $product->id)->where('location_id', $locationId)->lockForUpdate()->first();
        $stock->update(['quantity' => Money::decimal(Money::units($stock->quantity) + $units)]);
    }

    private function syncProductTotal(Product $product): void
    {
        $total = InventoryStock::where('product_id', $product->id)->get()->sum(fn ($row) => Money::units($row->quantity));
        $product->forceFill(['stock_quantity' => Money::decimal($total)])->saveQuietly();
    }

    /** Stock value at purchase price, in base currency. */
    public function valuation(): float
    {
        return (float) Product::where('type', 'product')->where('track_inventory', true)
            ->selectRaw('COALESCE(SUM(stock_quantity * purchase_price), 0) as value')->value('value');
    }
}
