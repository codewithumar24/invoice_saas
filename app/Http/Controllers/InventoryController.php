<?php

namespace App\Http\Controllers;

use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\FeatureService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public const TYPES = ['purchase' => 'Purchase (stock in)', 'return' => 'Customer return (stock in)', 'adjustment' => 'Adjustment (set difference)', 'damage' => 'Damage (stock out)', 'sale' => 'Sale without invoice (stock out)'];

    public function __construct(private InventoryService $inventory) {}

    public function index(Request $request)
    {
        $query = Product::with('stock.location')->where('type', 'product')->where('track_inventory', true)->where('status', 'active')
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', $this->like($term))->orWhere('sku', 'like', $this->like($term))))
            ->when($request->boolean('low'), fn ($q) => $q->whereColumn('stock_quantity', '<=', 'minimum_stock'))
            ->when($request->query('location'), fn ($q, $location) => $q->whereHas('stock', fn ($q) => $q->where('location_id', $location)->where('quantity', '>', 0)));

        return view('inventory.index', [
            'products' => $this->sorted($query, $request, ['name', 'stock_quantity'], 'name', 'asc')->paginate($this->perPage)->withQueryString(),
            'locations' => InventoryLocation::orderBy('name')->get(),
            'allProducts' => Product::where('type', 'product')->where('track_inventory', true)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'sku']),
            'valuation' => $this->inventory->valuation(),
            'lowCount' => Product::lowStock()->count(),
            'types' => self::TYPES,
        ]);
    }

    public function movements(Request $request)
    {
        $query = InventoryMovement::with(['product', 'location', 'toLocation', 'user'])
            ->when($request->query('product_id'), fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('location'), fn ($q, $location) => $q->where(fn ($q) => $q->where('location_id', $location)->orWhere('to_location_id', $location)))
            ->when($request->query('from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date));

        return view('inventory.movements', [
            'movements' => $query->latest()->paginate(25)->withQueryString(),
            'locations' => InventoryLocation::orderBy('name')->get(),
            'products' => Product::where('track_inventory', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Stock in, stock out and adjustments. */
    public function adjust(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', tenant_exists('products')],
            'location_id' => ['required', 'integer', tenant_exists('inventory_locations')],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'quantity' => ['required', 'numeric', 'not_in:0', 'min:-99999999', 'max:99999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $quantity = abs((float) $data['quantity']);

        // Direction comes from the movement type; an adjustment keeps the sign the user typed
        $signed = match ($data['type']) {
            'purchase', 'return' => $quantity,
            'damage', 'sale' => -$quantity,
            default => (float) $data['quantity'],
        };

        $this->inventory->move($product, (int) $data['location_id'], $data['type'], (string) $signed, [
            'unit_cost' => $data['unit_cost'] ?? null, 'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', __('Stock updated for :name.', ['name' => $product->name]));
    }

    public function transfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', tenant_exists('products')],
            'from_location_id' => ['required', 'integer', tenant_exists('inventory_locations')],
            'to_location_id' => ['required', 'integer', 'different:from_location_id', tenant_exists('inventory_locations')],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->inventory->transfer(Product::findOrFail($data['product_id']), (int) $data['from_location_id'], (int) $data['to_location_id'], (string) $data['quantity'], $data['notes'] ?? null);

        return back()->with('success', __('Stock transferred.'));
    }

    public function storeLocation(Request $request, FeatureService $features): RedirectResponse
    {
        $features->enforceLimit('max_locations', InventoryLocation::count(), __('locations'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['warehouse', 'store', 'office', 'branch'])],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        InventoryLocation::create($data + ['is_default' => ! InventoryLocation::exists()]);

        return back()->with('success', __('Location added.'));
    }

    public function destroyLocation(InventoryLocation $location): RedirectResponse
    {
        if ($location->stock()->where('quantity', '!=', 0)->exists()) {
            return back()->with('error', __('Move the stock out of this location before deleting it.'));
        }

        if ($location->is_default) {
            return back()->with('error', __('The default location cannot be deleted.'));
        }

        $location->delete();

        return back()->with('success', __('Location deleted.'));
    }
}
