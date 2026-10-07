@extends('layouts.app')
@section('title', __('Roles & permissions'))

@section('content')
    <x-page-header :title="__('Roles & permissions')" :subtitle="__('A role is a set of permissions. Give each team member the role that fits their job.')" :back="route('team.index')">
        <a href="{{ route('team.roles.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" />{{ __('New role') }}</a>
    </x-page-header>

    <x-table>
        <thead><tr><th>{{ __('Role') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Permissions') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($roles as $role)
                <tr>
                    <td data-label="{{ __('Role') }}" class="font-medium">{{ $role->name }}@if ($role->is_system) <span class="badge badge-neutral">{{ __('Default') }}</span>@endif</td>
                    <td data-label="{{ __('Description') }}" class="text-muted">{{ $role->description ?: '—' }}</td>
                    <td data-label="{{ __('Permissions') }}" class="num text-end">{{ $role->slug === 'owner' ? __('All') : $role->permissions_count }}</td>
                    <td class="text-end">
                        @if ($role->slug !== 'owner')
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('team.roles.edit', $role) }}" class="btn btn-secondary btn-sm">{{ __('Edit') }}</a>
                                @unless ($role->is_system)
                                    <x-action :action="route('team.roles.destroy', $role)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete the role :name?', ['name' => $role->name])" :confirm-label="__('Delete role')">{{ __('Delete') }}</x-action>
                                @endunless
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-table>
@endsection
