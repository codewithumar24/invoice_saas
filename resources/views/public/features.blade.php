@extends('layouts.guest')
@section('title', __('Features'))
@section('description', __('Invoicing, payments, customers, expenses, inventory, projects, automation, reports and Business Health in one workspace.'))

@php
    $groups = [
        __('Sell and get paid') => [
            [__('Invoices'), __('Line items from your catalogue, tax, discounts, shipping and deposits. Five templates, your colours and logo, custom fields. PDF, print and a secure public link.')],
            [__('Quotes'), __('Send an estimate the customer can accept or decline online. An accepted quote becomes an invoice in one click.')],
            [__('Recurring invoices'), __('Weekly, monthly, quarterly, yearly or custom schedules that create and send invoices for you.')],
            [__('Payments'), __('Stripe, PayPal, local gateways, bank transfer and cash. Partial payments, refunds and receipts. Online payments are confirmed by the gateway, not by the browser.')],
            [__('Reminders'), __('Automatic reminders before the due date, on it and after it, in your own wording.')],
        ],
        __('Know your customers') => [
            [__('Customer profiles'), __('Revenue, paid, outstanding and overdue, with contacts, notes, documents and a timeline of everything that happened.')],
            [__('Payment pattern'), __('See how many days a customer usually takes to pay and how often they are late.')],
            [__('Customer portal'), __('Customers sign in to view and pay invoices, answer quotes, download documents and message you, under your branding.')],
        ],
        __('Control costs and stock') => [
            [__('Expenses'), __('Categories, vendors, receipts as image or PDF, recurring bills and tax paid.')],
            [__('Products and services'), __('One catalogue with SKU, barcode, prices, tax and variants. Invoices can mix products and services.')],
            [__('Inventory'), __('Stock per warehouse, store or branch. Purchases, sales, returns, damage and transfers, with a full movement history and low-stock alerts.')],
        ],
        __('Run the work') => [
            [__('Projects and tasks'), __('Budget, deadline, tasks and assignees per customer project.')],
            [__('Time tracking'), __('Start, pause and stop a timer, mark time billable, and turn it into invoice lines.')],
            [__('Team and roles'), __('Invite members and decide exactly what each role can see and do. Every sensitive change is in the audit log.')],
            [__('Automation'), __('When an invoice becomes overdue, if the amount is over 1,000, then send a reminder and notify the manager. Build your own rules.')],
        ],
        __('Understand the business') => [
            [__('Dashboard'), __('Revenue, expenses, profit, outstanding, overdue and cash flow for any period, arranged the way you like.')],
            [__('Business Health'), __('A score you can inspect, signals for risks and opportunities, and an action list that goes straight to the fix.')],
            [__('Cash-flow forecast'), __('Open invoices plus recurring revenue minus recurring and upcoming expenses, 30, 60 and 90 days ahead.')],
            [__('Reports and exports'), __('Financial, sales, customer, expense and inventory reports. Export to PDF, CSV or Excel; import customers, products and expenses from CSV.')],
        ],
        __('Built for many countries') => [
            [__('Multi-currency'), __('Invoice in any currency. Each invoice keeps the exchange rate of its own date.')],
            [__('Tax'), __('Tax on top of prices or included in them, several rates, per line.')],
            [__('Languages'), __('Translation-ready, including right-to-left languages such as Arabic and Urdu.')],
            [__('API and webhooks'), __('Scoped API tokens and signed webhooks to connect your own systems.')],
        ],
    ];
@endphp

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <h1 class="max-w-3xl font-serif text-4xl font-semibold tracking-tight">{{ __('One workspace to invoice customers, manage operations, track money and understand what is happening.') }}</h1>
        <p class="mt-4 max-w-2xl text-lg text-muted">{{ __('Start with invoicing and switch on the rest when you need it.') }}</p>

        @foreach ($groups as $group => $items)
            <section class="mt-16 grid gap-8 border-t border-line pt-8 lg:grid-cols-[16rem_1fr]">
                <h2 class="font-serif text-2xl font-semibold tracking-tight">{{ $group }}</h2>
                <dl class="grid gap-x-10 gap-y-6 sm:grid-cols-2">
                    @foreach ($items as [$name, $text])
                        <div><dt class="font-semibold">{{ $name }}</dt><dd class="mt-1 text-muted">{{ $text }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endforeach

        <div class="mt-16 flex flex-wrap gap-3">
            <a href="{{ route('register') }}" class="btn btn-primary px-6 py-3 text-base">{{ __('Start free') }}</a>
            <a href="{{ route('pricing') }}" class="btn btn-secondary px-6 py-3 text-base">{{ __('See pricing') }}</a>
        </div>
    </div>
@endsection
