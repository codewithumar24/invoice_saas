@extends('layouts.admin')
@section('title', __('Queue monitor'))

@section('content')
    <x-page-header :title="__('Queue monitor')">
        @if ($horizon)<a href="{{ url('horizon') }}" class="btn btn-primary" target="_blank" rel="noopener">{{ __('Open Horizon') }} <x-icon name="external" size="14" /></a>@endif
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat :label="__('Queue driver')" :value="$driver" :note="$driver === 'redis' ? ($horizon ? __('Monitored by Horizon') : __('Install Laravel Horizon to monitor Redis queues')) : ($driver === 'sync' ? __('Jobs run immediately, no worker needed') : __('Run: php artisan queue:work'))" />
        <x-stat :label="__('Jobs waiting')" :value="$pending->sum('total')" />
        <x-stat :label="__('Scheduler last ran')" :value="$scheduler ? \Illuminate\Support\Carbon::parse($scheduler)->diffForHumans() : __('Never')" :tone="$scheduler && \Illuminate\Support\Carbon::parse($scheduler)->gt(now()->subMinutes(5)) ? null : 'danger'" :note="__('Needs the cron entry for schedule:run')" />
    </div>

    <h2 class="h2 mb-3">{{ __('Waiting by queue') }}</h2>
    <x-table>
        <thead><tr><th>{{ __('Queue') }}</th><th class="text-end">{{ __('Jobs') }}</th><th>{{ __('Oldest') }}</th></tr></thead>
        <tbody>
            @forelse ($pending as $row)
                <tr><td data-label="{{ __('Queue') }}" class="font-medium">{{ $row->queue }}</td><td data-label="{{ __('Jobs') }}" class="num text-end">{{ $row->total }}</td><td data-label="{{ __('Oldest') }}">{{ \Illuminate\Support\Carbon::createFromTimestamp($row->oldest)->diffForHumans() }}</td></tr>
            @empty
                <tr><td colspan="3" class="text-muted">{{ $driver === 'database' ? __('No jobs are waiting.') : __('Waiting jobs are listed here for the database queue driver.') }}</td></tr>
            @endforelse
        </tbody>
    </x-table>

    <h2 class="h2 mt-8 mb-3">{{ __('Failed jobs') }}</h2>
    <x-table>
        <thead><tr><th>{{ __('Failed') }}</th><th>{{ __('Job') }}</th><th>{{ __('Queue') }}</th><th>{{ __('Error') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($failed as $job)
                @php $payload = json_decode($job->payload, true); @endphp
                <tr>
                    <td data-label="{{ __('Failed') }}" class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($job->failed_at)->diffForHumans() }}</td>
                    <td data-label="{{ __('Job') }}" class="font-medium">{{ class_basename($payload['displayName'] ?? 'Job') }}</td>
                    <td data-label="{{ __('Queue') }}">{{ $job->queue }}</td>
                    <td data-label="{{ __('Error') }}" class="max-w-md text-xs text-muted">{{ \Illuminate\Support\Str::limit(strtok($job->exception, "\n"), 160) }}</td>
                    <td class="text-end">
                        <div class="flex justify-end gap-1">
                            <x-action :action="route('admin.queue.retry', $job->uuid)" class="btn btn-secondary btn-sm">{{ __('Retry') }}</x-action>
                            <x-action :action="route('admin.queue.forget', $job->uuid)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Remove this failed job? It will not be retried.')" :confirm-label="__('Remove')">{{ __('Remove') }}</x-action>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">{{ __('No failed jobs.') }}</td></tr>
            @endforelse
        </tbody>
    </x-table>
@endsection
