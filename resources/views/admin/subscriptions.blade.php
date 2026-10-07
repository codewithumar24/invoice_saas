@extends('layouts.admin')
@section('title', __('Subscriptions'))

@section('content')
    <x-page-header :title="__('Subscriptions')" />
    <x-filters :search="false">
        <div>
            <label for="f-status" class="sr-only">{{ __('Status') }}</label>
            <select name="status" id="f-status" class="input w-auto"><option value="">{{ __('All statuses') }}</option>@foreach (\App\Models\Subscription::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ status_label($status) }}</option>@endforeach</select>
        </div>
    </x-filters>

    <x-table>
        <thead><tr><th>{{ __('Tenant') }}</th><th>{{ __('Plan') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Cycle') }}</th><th>{{ __('Period ends') }}</th><th>{{ __('Gateway') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @forelse ($subscriptions as $subscription)
                <tr>
                    <td data-label="{{ __('Tenant') }}">@if ($subscription->tenant)<a class="font-medium hover:text-brand" href="{{ route('admin.tenants.show', $subscription->tenant) }}">{{ $subscription->tenant->name }}</a>@else — @endif</td>
                    <td data-label="{{ __('Plan') }}">{{ $subscription->plan?->name }}</td>
                    <td data-label="{{ __('Amount') }}" class="num text-end">{{ \App\Support\Money::format($subscription->amount, $subscription->currency) }}</td>
                    <td data-label="{{ __('Cycle') }}">{{ status_label($subscription->billing_cycle) }}</td>
                    <td data-label="{{ __('Period ends') }}">{{ $subscription->current_period_end?->format('d M Y') ?? '—' }}</td>
                    <td data-label="{{ __('Gateway') }}">{{ $subscription->gateway ?: __('Manual') }}</td>
                    <td data-label="{{ __('Status') }}"><x-badge :status="$subscription->status" /></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">{{ __('No subscriptions match.') }}</td></tr>
            @endforelse
        </tbody>
    </x-table>
    {{ $subscriptions->links() }}
@endsection
