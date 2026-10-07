{{--
    The invoice document itself. Used by: PDF (pdf.invoice), print view and public page.
    Shows only what the customer should see; nothing internal (no ids, notes for staff, costs).
--}}
@php
    $columns = $style['columns'] ?? [];
    $show = fn (string $column) => in_array($column, $columns, true);
    $customer = $invoice->customer;
    $address = $customer?->billingAddress();
    $currency = $invoice->currency;
    $status = $invoice->displayStatus();
    $hasDiscount = $show('discount') && $invoice->items->contains(fn ($item) => (float) $item->discount_percent > 0);
    $showTaxColumn = $show('tax') && $style['show_tax'] && (float) $invoice->tax_total > 0;
    $columnCount = 2 + ($show('quantity') ? 1 : 0) + ($show('unit') ? 1 : 0) + ($show('price') ? 1 : 0) + ($hasDiscount ? 1 : 0) + ($showTaxColumn ? 1 : 0);
@endphp
<div class="doc">
    <table class="head">
        <tr>
            <td style="width: 55%;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $tenant->name }}" class="logo"><br>
                @endif
                <span class="business-name">{{ $tenant->name }}</span><br>
                <span class="muted">
                    @if ($tenant->fullAddress()){{ $tenant->fullAddress() }}<br>@endif
                    @if ($tenant->email){{ $tenant->email }}@endif @if ($tenant->phone) &nbsp; {{ $tenant->phone }}@endif
                    @if ($tenant->tax_number)<br>{{ $style['tax_label'] }} {{ __('No.') }}: {{ $tenant->tax_number }}@endif
                </span>
            </td>
            <td class="right">
                <h1>{{ __('INVOICE') }}</h1>
                <span class="muted">{{ $invoice->number }}</span><br><br>
                <span class="status status-{{ $status }}">{{ mb_strtoupper(status_label($status)) }}</span>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td style="width: 55%;">
                <h2>{{ __('Bill to') }}</h2>
                <strong>{{ $customer?->name }}</strong><br>
                @if ($customer?->company){{ $customer->company }}<br>@endif
                <span class="muted">
                    @if ($address?->oneLine()){{ $address->oneLine() }}<br>@endif
                    @if ($customer?->email){{ $customer->email }}<br>@endif
                    @if ($customer?->tax_number){{ $style['tax_label'] }} {{ __('No.') }}: {{ $customer->tax_number }}@endif
                </span>
            </td>
            <td>
                <table class="meta">
                    <tr><td class="right muted">{{ __('Issue date') }}</td><td class="right">{{ $invoice->issue_date->format('d M Y') }}</td></tr>
                    <tr><td class="right muted">{{ __('Due date') }}</td><td class="right"><strong>{{ $invoice->due_date->format('d M Y') }}</strong></td></tr>
                    @if ($invoice->reference)<tr><td class="right muted">{{ __('Reference') }}</td><td class="right">{{ $invoice->reference }}</td></tr>@endif
                    @if ($invoice->payment_terms)<tr><td class="right muted">{{ __('Payment terms') }}</td><td class="right">{{ $invoice->payment_terms }}</td></tr>@endif
                    @foreach ($customFields as $label => $value)
                        <tr><td class="right muted">{{ $label }}</td><td class="right">{{ $value }}</td></tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('Item') }}</th>
                @if ($show('quantity'))<th class="right">{{ __('Qty') }}</th>@endif
                @if ($show('unit'))<th>{{ __('Unit') }}</th>@endif
                @if ($show('price'))<th class="right">{{ __('Price') }}</th>@endif
                @if ($hasDiscount)<th class="right">{{ __('Disc.') }}</th>@endif
                @if ($showTaxColumn)<th class="right">{{ $style['tax_label'] }}</th>@endif
                <th class="right">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>
                        {{ $item->name }}
                        @if ($show('description') && $item->description)<br><span class="desc pre">{{ $item->description }}</span>@endif
                    </td>
                    @if ($show('quantity'))<td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>@endif
                    @if ($show('unit'))<td>{{ $item->unit }}</td>@endif
                    @if ($show('price'))<td class="right">{{ money($item->unit_price, $currency) }}</td>@endif
                    @if ($hasDiscount)<td class="right">{{ (float) $item->discount_percent > 0 ? (float) $item->discount_percent.'%' : '' }}</td>@endif
                    @if ($showTaxColumn)<td class="right">{{ (float) $item->tax_rate > 0 ? (float) $item->tax_rate.'%' : '' }}</td>@endif
                    <td class="right">{{ money($item->line_total, $currency) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table>
        <tr>
            <td style="width: 52%; padding-right: 24px;">
                @if ($invoice->notes)
                    <h2>{{ __('Notes') }}</h2>
                    <p class="pre" style="margin: 0 0 10px 0;">{{ $invoice->notes }}</p>
                @endif

                @if ((float) $invoice->balance_due > 0 && $invoice->status !== 'cancelled')
                    @if ($style['payment_instructions'] || $style['bank_details'] || $invoice->paymentMethod?->instructions)
                        <div class="box">
                            <h2>{{ __('How to pay') }}</h2>
                            @if ($invoice->paymentMethod)<strong>{{ $invoice->paymentMethod->name }}</strong><br>@endif
                            @if ($invoice->paymentMethod?->instructions)<span class="pre">{{ $invoice->paymentMethod->instructions }}</span><br>@endif
                            @if ($style['payment_instructions'])<span class="pre">{{ $style['payment_instructions'] }}</span><br>@endif
                            @if ($style['bank_details'])<span class="pre">{{ $style['bank_details'] }}</span>@endif
                        </div>
                    @endif

                    @if ($style['show_payment_link'] && ($pdf ?? true))
                        <p class="small" style="margin: 0;">{{ __('View and pay online:') }}<br><a href="{{ $invoice->publicUrl() }}" style="color: #1a5a9e;">{{ $invoice->publicUrl() }}</a></p>
                    @endif
                @endif
            </td>
            <td>
                <table class="totals">
                    <tr><td class="muted">{{ __('Subtotal') }}</td><td class="right">{{ money($invoice->subtotal, $currency) }}</td></tr>
                    @if ((float) $invoice->discount_total > 0)
                        <tr><td class="muted">{{ __('Discount') }}@if ($invoice->discount_type === 'percent') ({{ (float) $invoice->discount_value }}%)@endif</td><td class="right">−{{ money($invoice->discount_total, $currency) }}</td></tr>
                    @endif
                    @if ($style['show_tax'])
                        @foreach ($invoice->taxes as $tax)
                            <tr><td class="muted">{{ $tax->name }} ({{ (float) $tax->rate }}%)@if ($invoice->tax_inclusive) <span class="small">{{ __('included') }}</span>@endif</td><td class="right">{{ money($tax->amount, $currency) }}</td></tr>
                        @endforeach
                    @endif
                    @if ((float) $invoice->shipping > 0)
                        <tr><td class="muted">{{ __('Shipping') }}</td><td class="right">{{ money($invoice->shipping, $currency) }}</td></tr>
                    @endif
                    @if ((float) $invoice->adjustment != 0)
                        <tr><td class="muted">{{ __('Adjustment') }}</td><td class="right">{{ money($invoice->adjustment, $currency) }}</td></tr>
                    @endif
                    <tr class="grand"><td>{{ __('Total') }}</td><td class="right">{{ money($invoice->total, $currency, true) }}</td></tr>
                    @if ((float) $invoice->amount_paid > 0)
                        <tr><td class="muted">{{ __('Paid') }}</td><td class="right">−{{ money($invoice->amount_paid, $currency) }}</td></tr>
                        <tr class="due"><td>{{ __('Balance due') }}</td><td class="right">{{ money($invoice->balance_due, $currency) }}</td></tr>
                    @endif
                    @if ((float) $invoice->deposit_amount > 0 && (float) $invoice->amount_paid == 0)
                        <tr><td class="muted">{{ __('Deposit requested') }}</td><td class="right">{{ money($invoice->deposit_amount, $currency) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    @if ($invoice->terms)
        <div style="margin-top: 16px;">
            <h2>{{ __('Terms') }}</h2>
            <p class="pre small muted" style="margin: 0;">{{ $invoice->terms }}</p>
        </div>
    @endif

    @if ($invoice->footer)
        <div class="foot">{{ $invoice->footer }}</div>
    @endif
</div>
