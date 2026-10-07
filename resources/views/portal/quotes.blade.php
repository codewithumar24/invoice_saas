@extends('layouts.customer')
@section('title', __('Quotes'))

@section('content')
    <h1 class="h1 mb-6">{{ __('Quotes') }}</h1>
    @if ($quotes->count())
        <x-table>
            <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Valid until') }}</th><th class="text-end">{{ __('Total') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($quotes as $quote)
                    <tr>
                        <td data-label="{{ __('Number') }}" class="num font-medium">{{ $quote->number }}</td>
                        <td data-label="{{ __('Date') }}">{{ $quote->issue_date->format('d M Y') }}</td>
                        <td data-label="{{ __('Valid until') }}">{{ $quote->expiry_date?->format('d M Y') ?? '—' }}</td>
                        <td data-label="{{ __('Total') }}" class="num text-end">{{ money($quote->total, $quote->currency) }}</td>
                        <td data-label="{{ __('Status') }}"><x-badge :status="$quote->displayStatus()" /></td>
                        <td class="text-end">
                            <div class="flex flex-wrap justify-end gap-1">
                                <a href="{{ $quote->publicUrl() }}" class="btn btn-secondary btn-sm">{{ __('View') }}</a>
                                @if ($quote->canRespond())
                                    <form method="POST" action="{{ route('portal.quotes.respond', [$portalTenant->slug, $quote->id]) }}">@csrf<input type="hidden" name="response" value="accept"><button class="btn btn-primary btn-sm">{{ __('Accept') }}</button></form>
                                    <form method="POST" action="{{ route('portal.quotes.respond', [$portalTenant->slug, $quote->id]) }}" data-confirm="{{ __('Decline this quote?') }}" data-confirm-label="{{ __('Decline quote') }}">@csrf<input type="hidden" name="response" value="reject"><button class="btn btn-ghost btn-sm">{{ __('Decline') }}</button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $quotes->links() }}
    @else
        <div class="card"><x-empty :title="__('No quotes yet')" /></div>
    @endif
@endsection
