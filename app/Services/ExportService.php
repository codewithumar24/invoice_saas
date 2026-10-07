<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Support\SimpleXlsx;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/** Builds CSV, Excel and PDF exports for lists and reports. */
class ExportService
{
    public const TYPES = ['customers', 'invoices', 'payments', 'expenses', 'products'];

    public const FORMATS = ['csv' => 'text/csv', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'];

    /** Above this many rows an export is generated in the queue instead of during the request. */
    public const QUEUE_THRESHOLD = 2000;

    public function __construct(private ReportService $reports, private PdfService $pdf) {}

    public function count(string $type, array $filters = []): int
    {
        return str_starts_with($type, 'report:') ? 0 : $this->query($type, $filters)->count();
    }

    /** @return array{title:string, headings:array, rows:array} */
    public function dataset(string $type, array $filters = []): array
    {
        if (str_starts_with($type, 'report:')) {
            [$from, $to] = DashboardService::range($filters['period'] ?? '30d', $filters['from'] ?? null, $filters['to'] ?? null);
            $report = $this->reports->run(substr($type, 7), $from, $to, $filters);

            return [
                'title' => $report['title'].' ('.$from->toDateString().' - '.$to->toDateString().')',
                'headings' => array_column($report['columns'], 'label'),
                'rows' => array_map(fn ($row) => array_map(fn ($key) => $row[$key] ?? '', array_keys($report['columns'])), $report['rows']),
            ];
        }

        $rows = [];
        $map = $this->mapper($type);

        // Chunked so large exports do not load everything into memory at once
        $this->query($type, $filters)->chunk(500, function ($records) use (&$rows, $map) {
            foreach ($records as $record) {
                $rows[] = $map['row']($record);
            }
        });

        return ['title' => __(ucfirst($type)), 'headings' => $map['headings'], 'rows' => $rows];
    }

    private function query(string $type, array $filters)
    {
        $ids = array_filter((array) ($filters['ids'] ?? []));

        return match ($type) {
            'customers' => Customer::with('addresses')->when($ids, fn ($q) => $q->whereIn('id', $ids))->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->orderBy('id'),
            'invoices' => Invoice::with('customer')->when($ids, fn ($q) => $q->whereIn('id', $ids))->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('issue_date', '>=', $d))->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('issue_date', '<=', $d))->orderBy('id'),
            'payments' => Payment::with('customer')->when($ids, fn ($q) => $q->whereIn('id', $ids))->orderBy('id'),
            'expenses' => Expense::with('category')->when($ids, fn ($q) => $q->whereIn('id', $ids))
                ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '>=', $d))->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('expense_date', '<=', $d))->orderBy('id'),
            'products' => Product::with('category')->when($ids, fn ($q) => $q->whereIn('id', $ids))->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))->orderBy('id'),
            default => throw new InvalidArgumentException("Unknown export type [{$type}]."),
        };
    }

    private function mapper(string $type): array
    {
        return match ($type) {
            'customers' => ['headings' => ['Name', 'Company', 'Email', 'Phone', 'Tax number', 'Address', 'Status', 'Balance', 'Created'],
                'row' => fn ($c) => [$c->name, $c->company, $c->email, $c->phone, $c->tax_number, $c->billingAddress()?->oneLine(), $c->status, (float) $c->balance, $c->created_at->toDateString()]],
            'invoices' => ['headings' => ['Number', 'Customer', 'Status', 'Issue date', 'Due date', 'Currency', 'Subtotal', 'Discount', 'Tax', 'Total', 'Paid', 'Balance'],
                'row' => fn ($i) => [$i->number, $i->customer?->name, $i->displayStatus(), $i->issue_date->toDateString(), $i->due_date->toDateString(), $i->currency, (float) $i->subtotal, (float) $i->discount_total, (float) $i->tax_total, (float) $i->total, (float) $i->amount_paid, (float) $i->balance_due]],
            'payments' => ['headings' => ['Number', 'Customer', 'Date', 'Method', 'Transaction ID', 'Status', 'Currency', 'Amount', 'Refunded'],
                'row' => fn ($p) => [$p->number, $p->customer?->name, $p->paid_at?->toDateString(), $p->method, $p->transaction_id, $p->status, $p->currency, (float) $p->amount, (float) $p->refunded_amount]],
            'expenses' => ['headings' => ['Date', 'Vendor', 'Category', 'Payment method', 'Reference', 'Currency', 'Amount', 'Tax', 'Notes'],
                'row' => fn ($e) => [$e->expense_date->toDateString(), $e->vendor, $e->category?->name, $e->payment_method, $e->reference, $e->currency, (float) $e->amount, (float) $e->tax_amount, $e->notes]],
            'products' => ['headings' => ['Type', 'Name', 'SKU', 'Barcode', 'Category', 'Unit', 'Purchase price', 'Selling price', 'Stock', 'Minimum stock', 'Status'],
                'row' => fn ($p) => [$p->type, $p->name, $p->sku, $p->barcode, $p->category?->name, $p->unit, (float) $p->purchase_price, (float) $p->selling_price, (float) $p->stock_quantity, (float) $p->minimum_stock, $p->status]],
        };
    }

    /** Render a dataset to file contents in the requested format. */
    public function render(array $dataset, string $format): string
    {
        return match ($format) {
            'csv' => $this->csv($dataset),
            'xlsx' => SimpleXlsx::build($dataset['headings'], $dataset['rows'], $dataset['title']),
            'pdf' => $this->pdf->render('pdf.table', $dataset + ['tenant' => tenant()], count($dataset['headings']) > 6 ? 'landscape' : 'portrait'),
            default => throw new InvalidArgumentException("Unknown export format [{$format}]."),
        };
    }

    private function csv(array $dataset): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF"); // BOM so Excel opens UTF-8 correctly
        fputcsv($handle, $dataset['headings']);

        foreach ($dataset['rows'] as $row) {
            fputcsv($handle, array_map([self::class, 'safeCell'], $row));
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    /** Stop spreadsheet formula injection: text starting with = + - @ is prefixed with an apostrophe. */
    public static function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }

    public function filename(string $type, string $format): string
    {
        return str_replace(':', '-', $type).'-'.Carbon::now()->format('Ymd-His').'.'.$format;
    }
}
