<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;

/** Global search. Tenant scoping comes from the models; permissions are checked per result group. */
class SearchService
{
    public function search(User $user, string $term, int $perGroup = 5): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
        $groups = [];

        $add = function (string $label, string $permission, callable $query, callable $map) use (&$groups, $user, $perGroup) {
            if (! $user->hasPermission($permission)) {
                return;
            }

            $results = $query()->limit($perGroup)->get()->map($map)->all();

            if ($results) {
                $groups[] = ['label' => $label, 'results' => $results];
            }
        };

        $add(__('Customers'), 'customers.view',
            fn () => Customer::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('company', 'like', $like)),
            fn ($c) => ['title' => $c->name, 'subtitle' => $c->email ?: $c->company, 'url' => route('customers.show', $c)]);

        $add(__('Invoices'), 'invoices.view',
            fn () => Invoice::with('customer')->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('reference', 'like', $like))->latest('id'),
            fn ($i) => ['title' => $i->number, 'subtitle' => $i->customer?->name.' — '.money($i->total, $i->currency), 'url' => route('invoices.show', $i)]);

        $add(__('Quotes'), 'quotes.view',
            fn () => Quote::with('customer')->where('number', 'like', $like)->latest('id'),
            fn ($q) => ['title' => $q->number, 'subtitle' => $q->customer?->name, 'url' => route('quotes.show', $q)]);

        $add(__('Products & services'), 'products.view',
            fn () => Product::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('barcode', 'like', $like)),
            fn ($p) => ['title' => $p->name, 'subtitle' => $p->sku ?: __(ucfirst($p->type)), 'url' => route('products.show', $p)]);

        $add(__('Payments'), 'payments.view',
            fn () => Payment::with('customer')->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('transaction_id', 'like', $like))->latest('id'),
            fn ($p) => ['title' => $p->number, 'subtitle' => $p->customer?->name.' — '.money($p->amount, $p->currency), 'url' => route('payments.show', $p)]);

        $add(__('Expenses'), 'expenses.view',
            fn () => Expense::where(fn ($q) => $q->where('vendor', 'like', $like)->orWhere('reference', 'like', $like))->latest('id'),
            fn ($e) => ['title' => $e->vendor ?: __('Expense'), 'subtitle' => $e->expense_date->format('d M Y').' — '.money($e->amount, $e->currency), 'url' => route('expenses.edit', $e)]);

        return $groups;
    }
}
