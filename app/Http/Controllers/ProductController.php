<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\CustomField;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxRate;
use App\Services\FeatureService;
use App\Services\FileStorageService;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Products and services share one catalogue; "type" tells them apart. */
class ProductController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Product::class);

        $query = Product::with('category')
            ->where('status', $request->query('status') === 'archived' ? 'archived' : 'active')
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('category'), fn ($q, $category) => $q->where('category_id', $category))
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q->where('name', 'like', $this->like($term))
                ->orWhere('sku', 'like', $this->like($term))->orWhere('barcode', 'like', $this->like($term))));

        return view('products.index', [
            'products' => $this->sorted($query, $request, ['name', 'selling_price', 'stock_quantity', 'created_at'], 'name', 'asc')->paginate($this->perPage)->withQueryString(),
            'categories' => ProductCategory::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Product::class);

        return view('products.form', ['product' => new Product(['type' => $request->query('type') === 'service' ? 'service' : 'product', 'status' => 'active'])] + $this->formData());
    }

    public function store(StoreProductRequest $request, FileStorageService $files, InventoryService $inventory, FeatureService $features): RedirectResponse
    {
        Gate::authorize('create', Product::class);
        $features->enforceLimit('max_products', Product::count(), __('products'));
        $data = $request->validated();

        $product = DB::transaction(function () use ($data, $request, $files, $inventory) {
            $product = Product::create($this->attributes($data) + [
                'image_path' => $request->hasFile('image') ? $files->storePublicImage($request->file('image'), 'products') : null,
            ]);

            $this->syncExtras($product, $data);

            // Opening stock is recorded as a movement so the stock history is complete from day one
            if ($product->track_inventory && (float) ($data['opening_stock'] ?? 0) > 0) {
                $inventory->move($product, $inventory->defaultLocation()->id, 'adjustment', (string) $data['opening_stock'], ['notes' => __('Opening stock')]);
            }

            return $product;
        });

        return redirect()->route('products.show', $product)->with('success', $product->isService() ? __('Service created.') : __('Product created.'));
    }

    public function show(Product $product)
    {
        Gate::authorize('view', $product);
        $product->load(['category', 'taxRate', 'variants', 'stock.location', 'tags', 'customFieldValues']);

        return view('products.show', [
            'product' => $product,
            'movements' => $product->movements()->with(['location', 'user'])->limit(15)->get(),
            'customFields' => $product->customFieldsForDisplay(),
        ]);
    }

    public function edit(Product $product)
    {
        Gate::authorize('update', $product);
        $product->load(['variants', 'tags', 'customFieldValues']);

        return view('products.form', ['product' => $product] + $this->formData());
    }

    public function update(StoreProductRequest $request, Product $product, FileStorageService $files): RedirectResponse
    {
        Gate::authorize('update', $product);
        $data = $request->validated();

        DB::transaction(function () use ($product, $data, $request, $files) {
            $product->fill($this->attributes($data));

            if ($request->hasFile('image')) {
                $files->delete('public', $product->image_path);
                $product->image_path = $files->storePublicImage($request->file('image'), 'products');
            }

            $product->save();
            $this->syncExtras($product, $data);
        });

        return redirect()->route('products.show', $product)->with('success', __('Saved.'));
    }

    /** Items used on invoices are archived, not deleted, so old invoices keep their meaning. */
    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        if (DB::table('invoice_items')->where('product_id', $product->id)->exists()) {
            $product->update(['status' => 'archived']);

            return redirect()->route('products.index')->with('success', __('This item is used on invoices, so it was archived instead of deleted.'));
        }

        $product->delete();

        return redirect()->route('products.index')->with('success', __('Deleted.'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:activate,archive,export'],
            'ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer'],
        ]);

        if ($data['action'] === 'export') {
            Gate::authorize('reports.export');

            return redirect()->route('exports.download-now', ['type' => 'products', 'format' => 'csv', 'ids' => $data['ids']]);
        }

        Gate::authorize('products.update');
        $products = Product::whereIn('id', $data['ids'])->get();
        $products->each->update(['status' => $data['action'] === 'archive' ? 'archived' : 'active']);

        return back()->with('success', trans_choice(':count item updated.|:count items updated.', $products->count(), ['count' => $products->count()]));
    }

    public function storeCategory(Request $request)
    {
        Gate::authorize('products.create');
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $category = ProductCategory::firstOrCreate(['name' => trim($data['name'])]);

        return $request->expectsJson() ? $this->ok($category->only(['id', 'name']), __('Category added.'), 201) : back()->with('success', __('Category added.'));
    }

    private function attributes(array $data): array
    {
        $attributes = collect($data)->only(['type', 'name', 'sku', 'barcode', 'description', 'category_id', 'purchase_price', 'selling_price',
            'tax_rate_id', 'unit', 'track_inventory', 'minimum_stock', 'duration_minutes', 'status'])->all();

        $attributes['purchase_price'] ??= 0;
        $attributes['minimum_stock'] ??= 0;
        $attributes['status'] ??= 'active';

        // Services never carry stock
        if ($attributes['type'] === 'service') {
            $attributes['track_inventory'] = false;
        }

        return $attributes;
    }

    private function syncExtras(Product $product, array $data): void
    {
        if (array_key_exists('tags', $data)) {
            $product->syncTagNames(explode(',', (string) $data['tags']));
        }

        $product->saveCustomFields($data['custom_fields'] ?? null);

        if (array_key_exists('variants', $data)) {
            $product->variants()->delete();

            foreach ($data['variants'] ?? [] as $variant) {
                $product->variants()->create(['name' => $variant['name'], 'sku' => $variant['sku'] ?? null, 'price' => $variant['price'] ?? $product->selling_price]);
            }
        }
    }

    private function formData(): array
    {
        return [
            'categories' => ProductCategory::orderBy('name')->get(),
            'taxRates' => TaxRate::where('is_active', true)->orderBy('name')->get(),
            'customFields' => CustomField::where('entity', 'product')->orderBy('sort_order')->get(),
        ];
    }
}
