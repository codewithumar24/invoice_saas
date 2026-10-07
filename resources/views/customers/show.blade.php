@extends('layouts.app')
@section('title', $customer->name)

@php $address = $customer->billingAddress(); @endphp

@section('content')
    <x-page-header :title="$customer->name" :subtitle="$customer->company" :back="route('customers.index')">
        @if ($customer->status === 'archived')<x-badge tone="neutral">{{ __('Archived') }}</x-badge>@endif
        @if (feature('quotes'))@can('quotes.create')<a href="{{ route('quotes.create', ['customer_id' => $customer->id]) }}" class="btn btn-secondary">{{ __('New quote') }}</a>@endcan @endif
        @can('invoices.create')<a href="{{ route('invoices.create', ['customer_id' => $customer->id]) }}" class="btn btn-primary">{{ __('New invoice') }}</a>@endcan
        @can('customers.update')
            <x-dropdown :label="__('More')">
                <a href="{{ route('customers.edit', $customer) }}" class="menu-item" role="menuitem">{{ __('Edit') }}</a>
                @if (feature('projects'))@can('projects.manage')<a href="{{ route('projects.create', ['customer_id' => $customer->id]) }}" class="menu-item" role="menuitem">{{ __('New project') }}</a>@endcan @endif
                <form method="POST" action="{{ route('customers.archive', $customer) }}">@csrf<button class="menu-item" role="menuitem">{{ $customer->status === 'archived' ? __('Restore') : __('Archive') }}</button></form>
                @can('customers.delete')
                    <form method="POST" action="{{ route('customers.destroy', $customer) }}" data-confirm="{{ __('Delete :name? Customers with invoices are archived instead, so their history is kept.', ['name' => $customer->name]) }}" data-confirm-label="{{ __('Delete') }}">
                        @csrf @method('DELETE')<button class="menu-item menu-item-danger" role="menuitem">{{ __('Delete') }}</button>
                    </form>
                @endcan
            </x-dropdown>
        @endcan
    </x-page-header>

    {{-- Overview --}}
    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat :label="__('Total revenue')" :value="money($stats['revenue'])" />
        <x-stat :label="__('Paid')" :value="money($stats['paid'])" />
        <x-stat :label="__('Outstanding')" :value="money($stats['outstanding'])" />
        <x-stat :label="__('Overdue')" :value="money($stats['overdue'])" :tone="$stats['overdue'] > 0 ? 'danger' : null" />
        <x-stat :label="__('Last transaction')" :value="$stats['last_transaction'] ? money($stats['last_transaction']->amount, $stats['last_transaction']->currency) : '—'" :note="$stats['last_transaction'] ? tdate($stats['last_transaction']->paid_at) : __('No payments yet')" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-card :title="__('Contact')">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-muted">{{ __('Email') }}</dt><dd>@if ($customer->email)<a class="link" href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>@else — @endif</dd></div>
                    <div><dt class="text-muted">{{ __('Phone') }}</dt><dd>{{ $customer->phone ?: '—' }}</dd></div>
                    <div><dt class="text-muted">{{ __('Address') }}</dt><dd>{{ $address?->oneLine() ?: '—' }}</dd></div>
                    <div><dt class="text-muted">{{ __('Tax number') }}</dt><dd>{{ $customer->tax_number ?: '—' }}</dd></div>
                    @foreach ($customer->customFieldsForDisplay() as $label => $value)
                        <div><dt class="text-muted">{{ $label }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($customer->tags->count())
                    <div class="mt-4 flex flex-wrap gap-1">
                        @foreach ($customer->tags as $tag)<span class="badge badge-neutral"><span class="h-1.5 w-1.5 rounded-full" style="background: {{ $tag->color }}"></span>{{ $tag->name }}</span>@endforeach
                    </div>
                @endif
            </x-card>

            {{-- Payment behaviour: more useful than "paid / unpaid" --}}
            <x-card :title="__('Payment pattern')">
                <p class="text-lg font-semibold">{{ $behavior['pattern'] }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Average days to pay') }}</dt><dd class="num">{{ $behavior['average_payment_days'] ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Invoices paid late or overdue') }}</dt><dd class="num">{{ $behavior['overdue_frequency'] }}%</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Lifetime value') }}</dt><dd class="num">{{ money($behavior['lifetime_value']) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Last payment') }}</dt><dd>{{ $behavior['last_payment'] ? tdate($behavior['last_payment']->paid_at) : '—' }}</dd></div>
                </dl>
            </x-card>

            @if (feature('customer_portal') && setting('portal_enabled'))
                <x-card :title="__('Customer portal')">
                    @if ($customer->portal_enabled)
                        <p class="text-sm">{{ $customer->portal_password ? __('This customer can sign in to view and pay invoices.') : __('Invitation sent. The customer has not set a password yet.') }}</p>
                        <p class="mt-2 break-all text-sm"><a class="link" href="{{ route('portal.login', tenant()->slug) }}" target="_blank" rel="noopener">{{ route('portal.login', tenant()->slug) }}</a></p>
                        @can('customers.update')
                            <div class="mt-4 flex flex-wrap gap-2">
                                <x-action :action="route('customers.portal.invite', $customer)">{{ __('Send invitation again') }}</x-action>
                                <x-action :action="route('customers.portal.disable', $customer)" method="DELETE" class="btn btn-danger btn-sm" :confirm="__('Remove portal access for this customer?')">{{ __('Remove access') }}</x-action>
                            </div>
                        @endcan
                    @else
                        <p class="text-sm text-muted">{{ __('Give this customer a login to view invoices, pay online, answer quotes and download documents.') }}</p>
                        @can('customers.update')<div class="mt-4"><x-action :action="route('customers.portal.invite', $customer)" class="btn btn-secondary btn-sm">{{ __('Invite to portal') }}</x-action></div>@endcan
                    @endif
                </x-card>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-card :title="__('Invoices')" :padded="false">
                @if ($invoices->count())
                    <table class="table table-stack">
                        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Due') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($invoices as $invoice)
                                <tr>
                                    <td data-label="{{ __('Number') }}"><a href="{{ route('invoices.show', $invoice) }}" class="font-medium hover:text-brand">{{ $invoice->number }}</a></td>
                                    <td data-label="{{ __('Date') }}">{{ tdate($invoice->issue_date) }}</td>
                                    <td data-label="{{ __('Due') }}">{{ tdate($invoice->due_date) }}</td>
                                    <td data-label="{{ __('Total') }}" class="num text-end">{{ money($invoice->total, $invoice->currency) }}</td>
                                    <td data-label="{{ __('Status') }}"><x-badge :status="$invoice->displayStatus()" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="border-t border-line px-4 py-2 text-sm sm:px-6"><a class="link" href="{{ route('invoices.index', ['customer_id' => $customer->id]) }}">{{ __('All invoices for this customer') }}</a></p>
                @else
                    <x-empty :title="__('No invoices yet')" :text="__('Create an invoice for this customer to start their history.')" />
                @endif
            </x-card>

            @if (feature('quotes') && $quotes->count())
                <x-card :title="__('Quotes')" :padded="false">
                    <table class="table table-stack">
                        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($quotes as $quote)
                                <tr>
                                    <td data-label="{{ __('Number') }}"><a href="{{ route('quotes.show', $quote) }}" class="font-medium hover:text-brand">{{ $quote->number }}</a></td>
                                    <td data-label="{{ __('Date') }}">{{ tdate($quote->issue_date) }}</td>
                                    <td data-label="{{ __('Total') }}" class="num text-end">{{ money($quote->total, $quote->currency) }}</td>
                                    <td data-label="{{ __('Status') }}"><x-badge :status="$quote->displayStatus()" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif

            @if ($payments->count())
                <x-card :title="__('Payments')" :padded="false">
                    <table class="table table-stack">
                        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td data-label="{{ __('Number') }}"><a href="{{ route('payments.show', $payment) }}" class="font-medium hover:text-brand">{{ $payment->number }}</a></td>
                                    <td data-label="{{ __('Date') }}">{{ tdate($payment->paid_at) }}</td>
                                    <td data-label="{{ __('Method') }}">{{ status_label($payment->method) }}</td>
                                    <td data-label="{{ __('Amount') }}" class="num text-end">{{ money($payment->amount, $payment->currency) }}</td>
                                    <td data-label="{{ __('Status') }}"><x-badge :status="$payment->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif

            <div class="grid gap-6 md:grid-cols-2">
                <x-card :title="__('Timeline')">
                    @include('partials.timeline', ['items' => $timeline, 'empty' => __('Invoices, payments, quotes, notes and documents for this customer appear here.')])
                </x-card>

                <div class="space-y-6">
                    <x-card :title="__('Notes')">
                        @can('customers.update')
                            <form method="POST" action="{{ route('customers.notes.store', $customer) }}" class="mb-4 space-y-2">
                                @csrf
                                <x-textarea name="body" :label="__('Add a note')" rows="2" required />
                                <div class="flex justify-end"><button type="submit" class="btn btn-secondary btn-sm">{{ __('Add note') }}</button></div>
                            </form>
                        @endcan
                        <ul class="space-y-3">
                            @forelse ($customer->customerNotes as $note)
                                <li class="text-sm"><p class="whitespace-pre-line">{{ $note->body }}</p><p class="caption">{{ $note->user?->name }}, {{ $note->created_at->diffForHumans() }}</p></li>
                            @empty
                                <li class="text-sm text-muted">{{ __('No notes yet.') }}</li>
                            @endforelse
                        </ul>
                    </x-card>

                    <x-card :title="__('Documents')">
                        <ul class="mb-4 space-y-2">
                            @forelse ($customer->attachments as $document)
                                <li class="flex items-center justify-between gap-3 text-sm">
                                    <a class="link truncate" href="{{ route('documents.download', $document) }}">{{ $document->original_name }}</a>
                                    @if ($document->visible_to_customer)<span class="badge badge-info">{{ __('Shared') }}</span>@endif
                                </li>
                            @empty
                                <li class="text-sm text-muted">{{ __('No documents yet.') }}</li>
                            @endforelse
                        </ul>
                        @can('customers.update')
                            <form method="POST" action="{{ route('customers.documents.store', $customer) }}" enctype="multipart/form-data" class="space-y-3 border-t border-line pt-4">
                                @csrf
                                <label for="customer-file" class="label">{{ __('Upload a document') }}</label>
                                <input type="file" name="file" id="customer-file" class="input" required>
                                @if (feature('customer_portal'))<x-checkbox name="visible_to_customer" :label="__('Show in the customer portal')" />@endif
                                <div class="flex justify-end"><button type="submit" class="btn btn-secondary btn-sm">{{ __('Upload') }}</button></div>
                            </form>
                        @endcan
                    </x-card>
                </div>
            </div>

            @if (feature('customer_portal') && $customer->portal_enabled)
                <x-card :title="__('Messages')">
                    <ul class="mb-4 space-y-3">
                        @forelse ($messages as $message)
                            <li class="text-sm {{ $message->from_customer ? '' : 'ps-6' }}">
                                <p class="whitespace-pre-line rounded-md px-3 py-2 {{ $message->from_customer ? 'bg-raised' : 'bg-brand-soft' }}">{{ $message->body }}</p>
                                <p class="caption mt-1">{{ $message->from_customer ? $customer->name : ($message->user?->name ?? __('Team')) }}, {{ $message->created_at->diffForHumans() }}</p>
                            </li>
                        @empty
                            <li class="text-sm text-muted">{{ __('No messages yet. Messages you send appear in the customer portal.') }}</li>
                        @endforelse
                    </ul>
                    @can('customers.update')
                        <form method="POST" action="{{ route('customers.messages.store', $customer) }}" class="space-y-2">
                            @csrf
                            <x-textarea name="body" :label="__('Message to the customer')" rows="2" required id="message-body" />
                            <div class="flex justify-end"><button type="submit" class="btn btn-secondary btn-sm">{{ __('Send message') }}</button></div>
                        </form>
                    @endcan
                </x-card>
            @endif
        </div>
    </div>
@endsection
