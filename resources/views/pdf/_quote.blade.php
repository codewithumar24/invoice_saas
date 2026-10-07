{{-- The quote document: used by the PDF and the public quote page. --}}
@php
    $customer = $quote->customer;
    $address = $customer?->billingAddress();
    $currency = $quote->currency;
    $status = $quote->displayStatus();
@endphp
<div class="doc">
    <table class="head">
        <tr>
            <td style="width: 55%;">
                @if ($logo)<img src="{{ $logo }}" alt="{{ $tenant->name }}" class="logo"><br>@endif
                <span class="business-name">{{ $tenant->name }}</span><br>
                <span class="muted">
                    @if ($tenant->fullAddress()){{ $tenant->fullAddress() }}<br>@endif
                    @if ($tenant->email){{ $tenant->email }}@endif @if ($tenant->phone) &nbsp; {{ $tenant->phone }}@endif
                </span>
            </td>
            <td class="right">
                <h1>{{ __('QUOTE') }}</h1>
                <span class="muted">{{ $quote->number }}</span><br><br>
                <span class="status status-{{ in_array($status, ['rejected', 'expired']) ? 'cancelled' : ($status === 'accepted' || $status === 'converted' ? 'paid' : $status) }}">{{ mb_strtoupper(status_label($status)) }}</span>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td style="width: 55%;">
                <h2>{{ __('Prepared for') }}</h2>
                <strong>{{ $customer?->name }}</strong><br>
                @if ($customer?->company){{ $customer->company }}<br>@endif
                <span class="muted">@if ($address?->oneLine()){{ $address->oneLine() }}<br>@endif {{ $customer?->email }}</span>
            </td>
            <td>
                <table class="meta">
                    <tr><td class="right muted">{{ __('Date') }}</td><td class="right">{{ $quote->issue_date->format('d M Y') }}</td></tr>
                    @if ($quote->expiry_date)<tr><td class="right muted">{{ __('Valid until') }}</td><td class="right"><strong>{{ $quote->expiry_date->format('d M Y') }}</strong></td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr><th>{{ __('Item') }}</th><th class="right">{{ __('Qty') }}</th><th class="right">{{ __('Price') }}</th><th class="right">{{ __('Total') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $item)
                <tr>
                    <td>{{ $item->name }}@if ($item->description)<br><span class="desc pre">{{ $item->description }}</span>@endif</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }} {{ $item->unit }}</td>
                    <td class="right">{{ money($item->unit_price, $currency) }}@if ((float) $item->discount_percent > 0)<br><span class="desc">−{{ (float) $item->discount_percent }}%</span>@endif</td>
                    <td class="right">{{ money($item->line_total, $currency) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table>
        <tr>
            <td style="width: 52%; padding-right: 24px;">
                @if ($quote->notes)<h2>{{ __('Notes') }}</h2><p class="pre" style="margin: 0;">{{ $quote->notes }}</p>@endif
            </td>
            <td>
                <table class="totals">
                    <tr><td class="muted">{{ __('Subtotal') }}</td><td class="right">{{ money($quote->subtotal, $currency) }}</td></tr>
                    @if ((float) $quote->discount_total > 0)<tr><td class="muted">{{ __('Discount') }}</td><td class="right">−{{ money($quote->discount_total, $currency) }}</td></tr>@endif
                    @if ((float) $quote->tax_total > 0)<tr><td class="muted">{{ $style['tax_label'] }}@if ($quote->tax_inclusive) <span class="small">{{ __('included') }}</span>@endif</td><td class="right">{{ money($quote->tax_total, $currency) }}</td></tr>@endif
                    <tr class="grand"><td>{{ __('Total') }}</td><td class="right">{{ money($quote->total, $currency, true) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($quote->terms)
        <div style="margin-top: 16px;"><h2>{{ __('Terms') }}</h2><p class="pre small muted" style="margin: 0;">{{ $quote->terms }}</p></div>
    @endif
</div>
