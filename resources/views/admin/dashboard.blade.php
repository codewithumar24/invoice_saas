@extends('layouts.admin')
@section('title', __('Dashboard'))

@php $currency = platform_setting('default_currency', 'USD'); @endphp

@section('content')
    <x-page-header :title="__('Platform dashboard')" />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <x-stat :label="__('Total tenants')" :value="number_format($metrics['total_tenants'])" :note="__(':count active', ['count' => $metrics['active_tenants']])" :href="route('admin.tenants.index')" />
        <x-stat :label="__('New registrations')" :value="number_format($metrics['new_registrations'])" :note="__('Last 30 days')" />
        <x-stat :label="__('MRR')" :value="\App\Support\Money::format($metrics['mrr'], $currency)" :note="__('Monthly recurring revenue')" />
        <x-stat :label="__('ARR')" :value="\App\Support\Money::format($metrics['arr'], $currency)" :note="__('Annual run rate')" />
        <x-stat :label="__('Churn')" :value="$metrics['churn'].'%'" :note="__('Last 30 days')" :tone="$metrics['churn'] > 5 ? 'danger' : null" />
        <x-stat :label="__('Trial users')" :value="number_format($metrics['trial_users'])" :href="route('admin.subscriptions', ['status' => 'trial'])" />
        <x-stat :label="__('Paid users')" :value="number_format($metrics['paid_users'])" :href="route('admin.subscriptions', ['status' => 'active'])" />
        <x-stat :label="__('Failed payments')" :value="number_format($metrics['failed_payments'])" :tone="$metrics['failed_payments'] ? 'danger' : null" :href="route('admin.subscriptions', ['status' => 'past_due'])" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        @foreach ([['registrations', __('Registration trend'), 'bar', false], ['revenue', __('Revenue trend'), 'line', true], ['subscriptions', __('New paid subscriptions'), 'bar', false], ['churn', __('Cancellations'), 'line', false]] as [$key, $label, $type, $money])
            <x-card :title="$label">
                <div class="relative h-56" data-chart-box>
                    <div class="skeleton absolute inset-0"></div>
                    <canvas data-chart="{{ json_encode(['type' => $type, 'money' => $money, 'currency' => $currency, 'labels' => $charts['labels'], 'datasets' => [['label' => $label, 'data' => $charts[$key]]]]) }}" role="img" aria-label="{{ $label }}"></canvas>
                </div>
            </x-card>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Newest tenants')" class="lg:col-span-2" :padded="false">
            <table class="table table-stack">
                <thead><tr><th>{{ __('Business') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Country') }}</th><th>{{ __('Registered') }}</th></tr></thead>
                <tbody>
                    @foreach ($recentTenants as $tenant)
                        <tr>
                            <td data-label="{{ __('Business') }}"><a class="font-medium hover:text-brand" href="{{ route('admin.tenants.show', $tenant) }}">{{ $tenant->name }}</a></td>
                            <td data-label="{{ __('Plan') }}">{{ $tenant->plan?->name ?? '—' }}</td>
                            <td data-label="{{ __('Country') }}">{{ $tenant->country ?: '—' }}</td>
                            <td data-label="{{ __('Registered') }}">{{ $tenant->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>

        <x-card :title="__('System health')">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Queue driver') }}</dt><dd>{{ $health['queue_driver'] }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Cache driver') }}</dt><dd>{{ $health['cache_driver'] }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Jobs waiting') }}</dt><dd class="num">{{ $health['pending_jobs'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Failed jobs') }}</dt><dd class="num {{ $health['failed_jobs'] ? 'text-danger' : '' }}">{{ $health['failed_jobs'] ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Scheduler last ran') }}</dt><dd class="{{ $health['scheduler'] && $health['scheduler']->gt(now()->subMinutes(5)) ? '' : 'text-danger' }}">{{ $health['scheduler']?->diffForHumans() ?? __('Never') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Last backup') }}</dt><dd class="{{ $health['last_backup']?->status === 'failed' ? 'text-danger' : '' }}">{{ $health['last_backup'] ? $health['last_backup']->created_at->diffForHumans().' ('.$health['last_backup']->status.')' : __('None') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Debug mode') }}</dt><dd class="{{ $health['debug'] ? 'text-danger' : '' }}">{{ $health['debug'] ? __('On: switch off in production') : __('Off') }}</dd></div>
            </dl>
            <div class="mt-4 flex flex-wrap gap-2"><a href="{{ route('admin.queue') }}" class="btn btn-secondary btn-sm">{{ __('Queue monitor') }}</a><a href="{{ route('admin.logs') }}" class="btn btn-secondary btn-sm">{{ __('Logs') }}</a></div>
        </x-card>
    </div>
@endsection
