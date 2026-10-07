@extends('layouts.admin')
@section('title', __('Tenants'))

@section('content')
    <x-page-header :title="__('Tenants')" />

    <x-filters :placeholder="__('Name, email or slug')">
        <div>
            <label for="f-status" class="sr-only">{{ __('Status') }}</label>
            <select name="status" id="f-status" class="input w-auto"><option value="">{{ __('All statuses') }}</option><option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option><option value="suspended" @selected(request('status') === 'suspended')>{{ __('Suspended') }}</option></select>
        </div>
        <div>
            <label for="f-plan" class="sr-only">{{ __('Plan') }}</label>
            <select name="plan_id" id="f-plan" class="input w-auto"><option value="">{{ __('All plans') }}</option>@foreach ($plans as $plan)<option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>{{ $plan->name }}</option>@endforeach</select>
        </div>
    </x-filters>

    <x-table>
        <thead><tr><th>{{ __('Business') }}</th><th>{{ __('Plan') }}</th><th class="text-end">{{ __('Users') }}</th><th>{{ __('Country') }}</th><th>{{ __('Registered') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @forelse ($tenants as $tenant)
                <tr>
                    <td data-label="{{ __('Business') }}"><a class="font-medium hover:text-brand" href="{{ route('admin.tenants.show', $tenant) }}">{{ $tenant->name }}</a><span class="caption block">{{ $tenant->email }}</span></td>
                    <td data-label="{{ __('Plan') }}">{{ $tenant->plan?->name ?? '—' }}</td>
                    <td data-label="{{ __('Users') }}" class="num text-end">{{ $tenant->users_count }}</td>
                    <td data-label="{{ __('Country') }}">{{ $tenant->country ?: '—' }}</td>
                    <td data-label="{{ __('Registered') }}">{{ $tenant->created_at->format('d M Y') }}</td>
                    <td data-label="{{ __('Status') }}"><x-badge :status="$tenant->status" />@if ($tenant->deletion_requested_at) <x-badge tone="danger">{{ __('Deletion requested') }}</x-badge>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">{{ __('No tenants match.') }}</td></tr>
            @endforelse
        </tbody>
    </x-table>
    {{ $tenants->links() }}
@endsection
