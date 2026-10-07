<?php

namespace App\Listeners;

use App\Events\CustomerCreated;
use App\Events\ExpenseCreated;
use App\Events\InvoiceCreated;
use App\Events\InvoiceOverdue;
use App\Events\InvoicePaid;
use App\Events\InvoiceSent;
use App\Events\InvoiceViewed;
use App\Events\PaymentReceived;
use App\Events\PaymentRefunded;
use App\Events\QuoteAccepted;
use App\Events\QuoteRejected;
use App\Events\StockLow;
use App\Events\SubscriptionChanged;
use App\Jobs\SendPaymentReceiptJob;
use App\Models\Invoice;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\AutomationService;
use App\Services\DashboardService;
use App\Services\NotificationService;
use App\Services\WebhookService;
use Illuminate\Events\Dispatcher;

/**
 * One place that reacts to business events: notifications, webhooks, automation rules and cache invalidation.
 * Methods are named on*() so Laravel's listener auto-discovery does not register them a second time.
 */
class BusinessEventSubscriber
{
    public function __construct(
        private NotificationService $notifications,
        private WebhookService $webhooks,
        private AutomationService $automation,
        private ActivityService $activity,
    ) {}

    public function subscribe(Dispatcher $events): array
    {
        return [
            InvoiceCreated::class => 'onInvoiceCreated',
            InvoiceSent::class => 'onInvoiceSent',
            InvoiceViewed::class => 'onInvoiceViewed',
            InvoicePaid::class => 'onInvoicePaid',
            InvoiceOverdue::class => 'onInvoiceOverdue',
            PaymentReceived::class => 'onPaymentReceived',
            PaymentRefunded::class => 'onPaymentRefunded',
            CustomerCreated::class => 'onCustomerCreated',
            QuoteAccepted::class => 'onQuoteAccepted',
            QuoteRejected::class => 'onQuoteRejected',
            ExpenseCreated::class => 'onExpenseCreated',
            StockLow::class => 'onStockLow',
            SubscriptionChanged::class => 'onSubscriptionChanged',
        ];
    }

    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id, 'number' => $invoice->number, 'status' => $invoice->status, 'customer_id' => $invoice->customer_id,
            'currency' => $invoice->currency, 'total' => (string) $invoice->total, 'balance_due' => (string) $invoice->balance_due,
            'issue_date' => $invoice->issue_date?->toDateString(), 'due_date' => $invoice->due_date?->toDateString(),
        ];
    }

    private function invoiceContext(Invoice $invoice): array
    {
        return [
            'amount' => (float) $invoice->total * (float) $invoice->exchange_rate,
            'currency' => $invoice->currency,
            'days_overdue' => $invoice->daysOverdue(),
            'customer_name' => $invoice->customer?->name,
            'invoice_number' => $invoice->number,
            'url' => route('invoices.show', $invoice),
        ];
    }

    public function onInvoiceCreated(InvoiceCreated $event): void
    {
        DashboardService::flush($event->invoice->tenant_id);
        $this->webhooks->dispatch('invoice.created', $this->invoicePayload($event->invoice));
        $this->automation->fire('invoice.created', $event->invoice, $this->invoiceContext($event->invoice), $event->invoice->customer);
    }

    public function onInvoiceSent(InvoiceSent $event): void
    {
        DashboardService::flush($event->invoice->tenant_id);
        $this->webhooks->dispatch('invoice.sent', $this->invoicePayload($event->invoice));
    }

    public function onInvoiceViewed(InvoiceViewed $event): void
    {
        $invoice = $event->invoice;
        $this->notifications->team('invoices', __(':customer viewed invoice :number', ['customer' => $invoice->customer?->name, 'number' => $invoice->number]),
            null, route('invoices.show', $invoice), 'invoices.view');
    }

    public function onInvoicePaid(InvoicePaid $event): void
    {
        DashboardService::flush($event->invoice->tenant_id);
        $this->webhooks->dispatch('invoice.paid', $this->invoicePayload($event->invoice));
        $this->automation->fire('invoice.paid', $event->invoice, $this->invoiceContext($event->invoice), $event->invoice->customer);
    }

    public function onInvoiceOverdue(InvoiceOverdue $event): void
    {
        $invoice = $event->invoice;
        DashboardService::flush($invoice->tenant_id);
        $this->notifications->team('invoices', __('Invoice :number became overdue', ['number' => $invoice->number]),
            __(':customer owes :amount.', ['customer' => $invoice->customer?->name, 'amount' => money($invoice->balance_due, $invoice->currency)]),
            route('invoices.show', $invoice), 'invoices.view');
        $this->webhooks->dispatch('invoice.overdue', $this->invoicePayload($invoice));
        $this->automation->fire('invoice.overdue', $invoice, $this->invoiceContext($invoice), $invoice->customer);
    }

    public function onPaymentReceived(PaymentReceived $event): void
    {
        $payment = $event->payment;
        DashboardService::flush($payment->tenant_id);
        SendPaymentReceiptJob::dispatch($payment->tenant_id, $payment->id);

        $this->notifications->team('payments', __('Payment of :amount received', ['amount' => money($payment->amount, $payment->currency)]),
            $payment->customer?->name, route('payments.show', $payment), 'payments.view');

        $this->webhooks->dispatch('payment.created', [
            'id' => $payment->id, 'number' => $payment->number, 'customer_id' => $payment->customer_id, 'amount' => (string) $payment->amount,
            'currency' => $payment->currency, 'method' => $payment->method, 'transaction_id' => $payment->transaction_id,
            'invoice_ids' => $payment->allocations()->pluck('invoice_id')->all(),
        ]);

        $this->automation->fire('payment.received', $payment->invoices()->first(), [
            'amount' => (float) $payment->amount * (float) $payment->exchange_rate, 'currency' => $payment->currency,
            'customer_name' => $payment->customer?->name, 'url' => route('payments.show', $payment),
        ], $payment->customer);
    }

    public function onPaymentRefunded(PaymentRefunded $event): void
    {
        DashboardService::flush($event->payment->tenant_id);
        $this->notifications->team('payments', __('Payment :number was refunded', ['number' => $event->payment->number]),
            null, route('payments.show', $event->payment), 'payments.view');
    }

    public function onCustomerCreated(CustomerCreated $event): void
    {
        $customer = $event->customer;
        DashboardService::flush($customer->tenant_id);
        $this->activity->log('customer.created', __('Customer :name created', ['name' => $customer->name]), $customer, $customer->id);
        $this->webhooks->dispatch('customer.created', $customer->only(['id', 'name', 'company', 'email', 'phone']));
        $this->automation->fire('customer.created', $customer, ['customer_name' => $customer->name, 'url' => route('customers.show', $customer)], $customer);
    }

    public function onQuoteAccepted(QuoteAccepted $event): void
    {
        $quote = $event->quote;
        $this->notifications->team('invoices', __(':customer accepted quote :number', ['customer' => $quote->customer?->name, 'number' => $quote->number]),
            __('You can convert it to an invoice now.'), route('quotes.show', $quote), 'quotes.view', true);
        $this->webhooks->dispatch('quote.accepted', $quote->only(['id', 'number', 'customer_id', 'currency']) + ['total' => (string) $quote->total]);
        $this->automation->fire('quote.accepted', $quote, [
            'amount' => (float) $quote->total * (float) $quote->exchange_rate, 'currency' => $quote->currency,
            'customer_name' => $quote->customer?->name, 'url' => route('quotes.show', $quote),
        ], $quote->customer);
    }

    public function onQuoteRejected(QuoteRejected $event): void
    {
        $quote = $event->quote;
        $this->notifications->team('invoices', __(':customer declined quote :number', ['customer' => $quote->customer?->name, 'number' => $quote->number]),
            null, route('quotes.show', $quote), 'quotes.view', true);
    }

    public function onExpenseCreated(ExpenseCreated $event): void
    {
        DashboardService::flush($event->expense->tenant_id);
    }

    public function onStockLow(StockLow $event): void
    {
        $product = $event->product;
        $this->notifications->team('inventory', __(':name is low on stock', ['name' => $product->name]),
            __(':quantity left, minimum is :minimum.', ['quantity' => (float) $product->stock_quantity, 'minimum' => (float) $product->minimum_stock]),
            route('products.show', $product), 'inventory.view', true);
        $this->automation->fire('stock.low', $product, [
            'stock' => (float) $product->stock_quantity, 'product_name' => $product->name, 'url' => route('products.show', $product),
        ]);
    }

    public function onSubscriptionChanged(SubscriptionChanged $event): void
    {
        $subscription = $event->subscription;
        app(AuditService::class)->log('subscription.changed', $subscription->plan?->name, ['status' => $subscription->status, 'plan_id' => $subscription->plan_id], $subscription->tenant_id);

        if (tenant()?->id === $subscription->tenant_id) {
            $this->webhooks->dispatch('subscription.updated', ['plan' => $subscription->plan?->slug, 'status' => $subscription->status]);
        }
    }
}
