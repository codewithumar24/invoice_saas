<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Import;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

/** CSV import: upload -> map columns -> preview -> validate -> import -> report errors. */
class ImportService
{
    /** Importable fields per type: field => [label, validation rules] */
    public const FIELDS = [
        'customers' => [
            'name' => ['Name', 'required|string|max:255'], 'company' => ['Company', 'nullable|string|max:255'],
            'email' => ['Email', 'nullable|email|max:255'], 'phone' => ['Phone', 'nullable|string|max:50'],
            'tax_number' => ['Tax number', 'nullable|string|max:100'], 'address' => ['Address', 'nullable|string|max:255'],
            'city' => ['City', 'nullable|string|max:100'], 'country' => ['Country code', 'nullable|string|size:2'],
        ],
        'products' => [
            'name' => ['Name', 'required|string|max:255'], 'type' => ['Type (product/service)', 'nullable|in:product,service'],
            'sku' => ['SKU', 'nullable|string|max:100'], 'barcode' => ['Barcode', 'nullable|string|max:100'],
            'unit' => ['Unit', 'nullable|string|max:50'], 'purchase_price' => ['Purchase price', 'nullable|numeric|min:0'],
            'selling_price' => ['Selling price', 'required|numeric|min:0'], 'minimum_stock' => ['Minimum stock', 'nullable|numeric|min:0'],
        ],
        'expenses' => [
            'expense_date' => ['Date', 'required|date'], 'amount' => ['Amount', 'required|numeric|min:0.01'],
            'vendor' => ['Vendor', 'nullable|string|max:255'], 'category' => ['Category', 'nullable|string|max:255'],
            'reference' => ['Reference', 'nullable|string|max:255'], 'notes' => ['Notes', 'nullable|string|max:2000'],
        ],
    ];

    public function __construct(private CustomerService $customers, private ExpenseService $expenses) {}

    /** Header row and the first data rows of the uploaded file. */
    public function preview(Import $import, int $limit = 5): array
    {
        $handle = $this->open($import);
        $header = $this->readRow($handle) ?: [];
        $rows = [];

        while (count($rows) < $limit && ($row = $this->readRow($handle)) !== null) {
            $rows[] = $row;
        }

        fclose($handle);

        return ['header' => $header, 'rows' => $rows];
    }

    /** Guess the mapping from header names: [field => column index]. */
    public function guessMapping(string $type, array $header): array
    {
        $mapping = [];
        $normalise = fn ($value) => preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $value));

        foreach (self::FIELDS[$type] as $field => [$label]) {
            foreach ($header as $index => $column) {
                if (in_array($normalise($column), [$normalise($field), $normalise($label)], true)) {
                    $mapping[$field] = $index;
                    break;
                }
            }
        }

        return $mapping;
    }

    /** Import every row. Invalid rows are skipped and reported; valid rows are still imported. */
    public function process(Import $import): void
    {
        $import->update(['status' => 'processing']);
        $handle = $this->open($import);
        $this->readRow($handle); // skip header

        $rules = array_map(fn ($field) => $field[1], self::FIELDS[$import->type]);
        $mapping = $import->mapping ?? [];
        $line = 1;
        $imported = 0;
        $errors = [];

        while (($row = $this->readRow($handle)) !== null) {
            $line++;

            if (count(array_filter($row, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach ($mapping as $field => $index) {
                if ($index !== null && $index !== '' && isset($row[(int) $index])) {
                    $data[$field] = trim((string) $row[(int) $index]);
                }
            }

            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                $errors[] = ['line' => $line, 'message' => implode(' ', $validator->errors()->all())];

                continue;
            }

            try {
                $this->importRow($import->type, array_filter($data, fn ($value) => $value !== ''));
                $imported++;
            } catch (Throwable $exception) {
                $errors[] = ['line' => $line, 'message' => mb_substr($exception->getMessage(), 0, 200)];
            }
        }

        fclose($handle);

        $import->update([
            'status' => 'completed', 'total_rows' => $line - 1, 'imported_rows' => $imported,
            'failed_rows' => count($errors), 'errors' => array_slice($errors, 0, 200),
        ]);
    }

    private function importRow(string $type, array $data): void
    {
        match ($type) {
            'customers' => $this->customers->create(collect($data)->except(['address', 'city', 'country'])->all() + [
                'address' => ['line1' => $data['address'] ?? null, 'city' => $data['city'] ?? null, 'country' => isset($data['country']) ? strtoupper($data['country']) : null],
            ]),
            'products' => Product::create(['type' => $data['type'] ?? 'product', 'purchase_price' => $data['purchase_price'] ?? 0, 'minimum_stock' => $data['minimum_stock'] ?? 0] + $data),
            'expenses' => $this->expenses->create(collect($data)->except('category')->all() + [
                'category_id' => isset($data['category']) ? ExpenseCategory::firstOrCreate(['name' => $data['category']])->id : null,
            ]),
        };
    }

    /** @return resource */
    private function open(Import $import)
    {
        $handle = Storage::disk($import->disk)->readStream($import->path);
        // Skip a UTF-8 byte order mark if present
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        return $handle;
    }

    private function readRow($handle): ?array
    {
        $row = fgetcsv($handle, null, ',', '"', '');

        return $row === false ? null : $row;
    }
}
