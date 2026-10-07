<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Payment::class);

        $query = Payment::with(['customer', 'invoices', 'paymentMethod'])
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q->where('number', 'like', $this->like($term))
                ->orWhere('transaction_id', 'like', $this->like($term))->orWhereHas('customer', fn ($q) => $q->where('name', 'like', $this->like($term)))))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('customer_id'), fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->query('method'), fn ($q, $method) => $q->where('method', $method))
            ->when($request->query('from'), fn ($q, $date) => $q->whereDate('paid_at', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->whereDate('paid_at', '<=', $date));

        return view('payments.index', [
            'payments' => $this->sorted($query, $request, ['number', 'paid_at', 'amount'], 'paid_at')->paginate($this->perPage)->withQueryString(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'methods' => Payment::distinct()->pluck('method'),
        ]);
    }

    public function show(Payment $payment)
    {
        Gate::authorize('view', $payment);
        $payment->load(['customer', 'allocations.invoice', 'transactions', 'paymentMethod']);

        return view('payments.show', ['payment' => $payment]);
    }

    public function refund(Request $request, Payment $payment, PaymentService $payments): RedirectResponse
    {
        Gate::authorize('refund', $payment);
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0'], 'reason' => ['nullable', 'string', 'max:255']]);
        $payments->refund($payment, (string) $data['amount'], $data['reason'] ?? null);

        return back()->with('success', __('Refund recorded.'));
    }
}
