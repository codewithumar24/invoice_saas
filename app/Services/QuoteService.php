<?php

namespace App\Services;

use App\Events\QuoteAccepted;
use App\Events\QuoteRejected;
use App\Events\QuoteSent;
use App\Jobs\SendQuoteEmailJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function __construct(
        private InvoiceCalculator $calculator,
        private NumberingService $numbering,
        private TaxService $tax,
        private CurrencyService $currency,
        private InvoiceService $invoices,
        private ActivityService $activity,
    ) {}

    public function create(array $data): Quote
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::findOrFail($data['customer_id']);
            $currency = strtoupper($data['currency'] ?? $customer->currency ?? tenant()->currency);

            $quote = new Quote($this->attributes($data));
            $quote->fill([
                'customer_id' => $customer->id,
                'number' => $this->numbering->next('quote'),
                'currency' => $currency,
                'exchange_rate' => $this->currency->rate($currency, $data['issue_date'] ?? null),
                'tax_inclusive' => $this->tax->pricesIncludeTax(),
                'status' => 'draft',
                'public_token' => Str::random(48),
                'created_by' => auth()->id(),
            ]);

            $this->applyItems($quote, $data['items'] ?? []);
            $this->activity->log('quote.created', __('Quote :number created', ['number' => $quote->number]), $quote, $quote->customer_id);

            return $quote;
        });
    }

    public function update(Quote $quote, array $data): Quote
    {
        if (in_array($quote->status, ['accepted', 'converted'], true)) {
            throw ValidationException::withMessages(['quote' => __('An accepted quote cannot be edited.')]);
        }

        return DB::transaction(function () use ($quote, $data) {
            $quote->fill($this->attributes($data));

            if (isset($data['customer_id'])) {
                $quote->customer_id = Customer::findOrFail($data['customer_id'])->id;
            }

            if (isset($data['currency'])) {
                $quote->currency = strtoupper($data['currency']);
            }

            $this->applyItems($quote, $data['items'] ?? []);

            return $quote;
        });
    }

    private function attributes(array $data): array
    {
        return collect($data)->only(['issue_date', 'expiry_date', 'discount_type', 'discount_value', 'notes', 'terms'])
            ->map(fn ($value, $key) => $key === 'discount_value' ? ($value ?? 0) : $value)->all();
    }

    private function applyItems(Quote $quote, array $items): void
    {
        $items = $this->tax->resolveLines(array_values($items));

        $totals = $this->calculator->calculate($items, [
            'discount_type' => $quote->discount_type, 'discount_value' => $quote->discount_value,
            'tax_inclusive' => $quote->tax_inclusive, 'tax_exempt' => $this->tax->isExempt(), 'currency' => $quote->currency,
        ]);

        $quote->fill(collect($totals)->only(['subtotal', 'discount_total', 'tax_total', 'total'])->all())->save();
        $quote->items()->delete();

        foreach ($items as $index => $item) {
            $quote->items()->create([
                'product_id' => $item['product_id'] ?? null, 'name' => $item['name'], 'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'], 'unit' => $item['unit'] ?? null, 'unit_price' => $item['unit_price'],
                'discount_percent' => $item['discount_percent'] ?? 0, 'tax_rate_id' => $item['tax_rate_id'], 'tax_rate' => $item['tax_rate'],
                'tax_amount' => $totals['lines'][$index]['tax_amount'], 'line_total' => $totals['lines'][$index]['line_total'], 'sort_order' => $index,
            ]);
        }
    }

    public function send(Quote $quote, bool $email = true): Quote
    {
        if ($email && ! $quote->customer->email) {
            throw ValidationException::withMessages(['quote' => __('This customer has no email address.')]);
        }

        if (in_array($quote->status, ['draft', 'expired', 'rejected'], true)) {
            $quote->forceFill(['status' => 'sent'])->save();
        }

        $quote->forceFill(['sent_at' => now()])->save();

        if ($email) {
            SendQuoteEmailJob::dispatch($quote->tenant_id, $quote->id);
        }

        $this->activity->log('quote.sent', __('Quote :number sent', ['number' => $quote->number]), $quote, $quote->customer_id);
        QuoteSent::dispatch($quote);

        return $quote;
    }

    public function markViewed(Quote $quote): void
    {
        if ($quote->viewed_at === null) {
            $quote->forceFill(['viewed_at' => now(), 'status' => $quote->status === 'sent' ? 'viewed' : $quote->status])->saveQuietly();
            $this->activity->log('quote.viewed', __('Quote :number viewed by customer', ['number' => $quote->number]), $quote, $quote->customer_id);
        }
    }

    public function accept(Quote $quote): Quote
    {
        $this->guardResponse($quote);
        $quote->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();
        $this->activity->log('quote.accepted', __('Quote :number accepted', ['number' => $quote->number]), $quote, $quote->customer_id);
        QuoteAccepted::dispatch($quote);

        return $quote;
    }

    public function reject(Quote $quote): Quote
    {
        $this->guardResponse($quote);
        $quote->forceFill(['status' => 'rejected', 'rejected_at' => now()])->save();
        $this->activity->log('quote.rejected', __('Quote :number rejected', ['number' => $quote->number]), $quote, $quote->customer_id);
        QuoteRejected::dispatch($quote);

        return $quote;
    }

    private function guardResponse(Quote $quote): void
    {
        if (! in_array($quote->status, ['draft', 'sent', 'viewed'], true) || $quote->isExpired()) {
            throw ValidationException::withMessages(['quote' => __('This quote can no longer be accepted or rejected.')]);
        }
    }

    /** One-click quote to invoice. */
    public function convert(Quote $quote): Invoice
    {
        if ($quote->status === 'converted' && $quote->converted_invoice_id) {
            throw ValidationException::withMessages(['quote' => __('This quote was already converted to an invoice.')]);
        }

        return DB::transaction(function () use ($quote) {
            $invoice = $this->invoices->createFromQuote($quote->load('items'));
            $quote->forceFill(['status' => 'converted', 'converted_invoice_id' => $invoice->id, 'accepted_at' => $quote->accepted_at ?? now()])->save();
            $this->activity->log('quote.converted', __('Quote :quote converted to invoice :invoice', ['quote' => $quote->number, 'invoice' => $invoice->number]), $quote, $quote->customer_id);

            return $invoice;
        });
    }

    /** Daily job: flag quotes whose expiry date passed. */
    public function markExpired(): int
    {
        return Quote::whereIn('status', ['sent', 'viewed'])->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today_local()->toDateString())->update(['status' => 'expired']);
    }
}
