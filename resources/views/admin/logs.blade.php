@extends('layouts.admin')
@section('title', __('System logs'))

@section('content')
    <x-page-header :title="__('System logs')" />
    <nav class="mb-4 flex gap-6 border-b border-line" aria-label="{{ __('Log') }}">
        <a href="{{ route('admin.logs') }}" class="tab" @if (request('tab') !== 'system') aria-current="page" @endif>{{ __('Audit log') }}</a>
        <a href="{{ route('admin.logs', ['tab' => 'system']) }}" class="tab" @if (request('tab') === 'system') aria-current="page" @endif>{{ __('Application log') }}</a>
    </nav>

    @if (request('tab') === 'system')
        @if (count($lines))
            <pre class="card max-h-[70dvh] overflow-auto p-4 text-xs leading-5 whitespace-pre-wrap" tabindex="0">@foreach ($lines as $line){{ $line }}
@endforeach</pre>
            <p class="caption mt-2">{{ __('The last lines of storage/logs/laravel.log. Sensitive values are never written to the log.') }}</p>
        @else
            <div class="card"><x-empty :title="__('The application log is empty')" /></div>
        @endif
    @else
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3" data-no-loading>
            <div class="w-full sm:w-64"><label for="f-action" class="sr-only">{{ __('Action') }}</label><input type="search" name="action" id="f-action" value="{{ request('action') }}" class="input" placeholder="{{ __('Action, for example impersonation') }}"></div>
            <div class="w-32"><label for="f-tenant" class="sr-only">{{ __('Tenant id') }}</label><input type="number" name="tenant_id" id="f-tenant" value="{{ request('tenant_id') }}" class="input" placeholder="{{ __('Tenant id') }}"></div>
            <button type="submit" class="btn btn-secondary">{{ __('Filter') }}</button>
        </form>
        <x-table>
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('Tenant') }}</th><th>{{ __('Who') }}</th><th>{{ __('Action') }}</th><th>{{ __('Record') }}</th><th>{{ __('IP address') }}</th></tr></thead>
            <tbody>
                @forelse ($audit as $log)
                    <tr>
                        <td data-label="{{ __('When') }}" class="whitespace-nowrap">{{ $log->created_at?->format('d M Y H:i') }}</td>
                        <td data-label="{{ __('Tenant') }}">{{ $log->tenant?->name ?? '—' }}</td>
                        <td data-label="{{ __('Who') }}">{{ $log->user?->email ?? __('System') }}@if ($log->impersonator_id) <span class="badge badge-warn">{{ __('impersonated') }}</span>@endif</td>
                        <td data-label="{{ __('Action') }}"><code>{{ $log->action }}</code></td>
                        <td data-label="{{ __('Record') }}">{{ $log->entity_type }} {{ $log->entity_label }}</td>
                        <td data-label="{{ __('IP address') }}" class="num text-muted">{{ $log->ip ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">{{ __('No entries.') }}</td></tr>
                @endforelse
            </tbody>
        </x-table>
        {{ $audit->links() }}
    @endif
@endsection
