<?php

namespace App\Services;

use App\Events\CustomerCreated;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(private FeatureService $features) {}

    public function create(array $data): Customer
    {
        $this->features->enforceLimit('max_customers', Customer::count(), __('customers'));

        return DB::transaction(function () use ($data) {
            $customer = Customer::create($this->attributes($data) + ['created_by' => auth()->id()]);
            $this->syncRelated($customer, $data);
            CustomerCreated::dispatch($customer);

            return $customer;
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            $customer->update($this->attributes($data));
            $this->syncRelated($customer, $data);

            return $customer;
        });
    }

    private function attributes(array $data): array
    {
        return collect($data)->only(['name', 'company', 'email', 'phone', 'website', 'tax_number', 'currency', 'payment_terms_days', 'notes'])->all();
    }

    private function syncRelated(Customer $customer, array $data): void
    {
        if (array_key_exists('address', $data)) {
            $address = array_filter($data['address'] ?? []);

            if ($address) {
                $customer->addresses()->updateOrCreate(['type' => 'billing'], $data['address']);
            }
        }

        if (array_key_exists('tags', $data)) {
            $customer->syncTagNames(is_array($data['tags']) ? $data['tags'] : explode(',', (string) $data['tags']));
        }

        $customer->saveCustomFields($data['custom_fields'] ?? null);
    }

    /** Outstanding balance in the workspace base currency. */
    public function refreshBalance(Customer $customer): void
    {
        $units = 0;

        Invoice::where('customer_id', $customer->id)->open()->get(['balance_due', 'exchange_rate'])
            ->each(function ($invoice) use (&$units) {
                $units += Money::mulDiv(Money::units($invoice->balance_due), (int) round(((float) $invoice->exchange_rate) * 100000000), 100000000);
            });

        $customer->forceFill(['balance' => Money::decimal($units), 'last_activity_at' => now()])->saveQuietly();
    }

    /** Payment behaviour statistics shown on the customer profile. */
    public function paymentBehavior(Customer $customer): array
    {
        $paid = Invoice::where('customer_id', $customer->id)->where('status', 'paid')->whereNotNull('paid_at')->get(['due_date', 'issue_date', 'paid_at', 'total', 'exchange_rate']);
        $all = Invoice::where('customer_id', $customer->id)->whereNotIn('status', ['draft', 'cancelled'])->get(['status', 'due_date', 'total', 'exchange_rate', 'balance_due', 'paid_at']);

        $lateDays = $paid->map(fn ($invoice) => (int) round($invoice->due_date->diffInDays($invoice->paid_at->startOfDay(), false)));
        $averageLate = $lateDays->count() ? (int) round($lateDays->avg()) : null;
        $lateCount = $lateDays->filter(fn ($days) => $days > 0)->count() + $all->filter(fn ($invoice) => $invoice->isOverdue())->count();

        $pattern = match (true) {
            $averageLate === null => __('No payment history yet'),
            $averageLate > 0 => trans_choice('Usually pays :count day late|Usually pays :count days late', $averageLate, ['count' => $averageLate]),
            $averageLate < 0 => trans_choice('Usually pays :count day early|Usually pays :count days early', abs($averageLate), ['count' => abs($averageLate)]),
            default => __('Usually pays on the due date'),
        };

        return [
            'pattern' => $pattern,
            'average_payment_days' => $paid->count() ? (int) round($paid->map(fn ($i) => $i->issue_date->diffInDays($i->paid_at->startOfDay()))->avg()) : null,
            'average_late_days' => $averageLate,
            'overdue_frequency' => $all->count() ? (int) round($lateCount / $all->count() * 100) : 0,
            'lifetime_value' => $all->sum(fn ($i) => (float) $i->total * (float) $i->exchange_rate),
            'outstanding' => (float) $customer->balance,
            'last_payment' => $customer->payments()->where('status', 'paid')->latest('paid_at')->first(),
            'invoice_count' => $all->count(),
        ];
    }
}
