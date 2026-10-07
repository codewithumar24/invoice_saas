@extends('layouts.app')
@section('title', __('Webhook deliveries'))

@section('content')
    @component('settings._layout')
        <div>
            <p class="mb-1 text-sm"><a class="link" href="{{ route('settings.webhooks') }}">&larr; {{ __('All webhooks') }}</a></p>
            <h2 class="h2 break-all">{{ $webhook->url }}</h2>
            <p class="mb-4 text-sm text-muted">{{ __('Delivery log: each event sent to this endpoint, the response and the number of attempts.') }}</p>

            @if ($deliveries->count())
                <x-table>
                    <thead><tr><th>{{ __('When') }}</th><th>{{ __('Event') }}</th><th>{{ __('Response') }}</th><th class="text-end">{{ __('Attempts') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($deliveries as $delivery)
                            <tr>
                                <td data-label="{{ __('When') }}">{{ tdate($delivery->created_at, 'd M Y, H:i') }}</td>
                                <td data-label="{{ __('Event') }}"><code>{{ $delivery->event }}</code></td>
                                <td data-label="{{ __('Response') }}" class="max-w-xs"><span class="num">{{ $delivery->response_status ?? '—' }}</span> <span class="text-muted">{{ \Illuminate\Support\Str::limit((string) $delivery->response_body, 80) }}</span></td>
                                <td data-label="{{ __('Attempts') }}" class="num text-end">{{ $delivery->attempts }}</td>
                                <td data-label="{{ __('Status') }}"><x-badge :status="$delivery->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
                {{ $deliveries->links() }}
            @else
                <div class="card"><x-empty :title="__('Nothing delivered yet')" :text="__('Deliveries appear here as soon as one of the selected events happens.')" /></div>
            @endif
        </div>
    @endcomponent
@endsection
