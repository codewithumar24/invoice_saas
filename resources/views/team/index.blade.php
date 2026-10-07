@extends('layouts.app')
@section('title', __('Team'))

@php $canManage = auth()->user()->hasPermission('team.manage'); @endphp

@section('content')
    <x-page-header :title="__('Team')">
        @can('roles.manage')<a href="{{ route('team.roles') }}" class="btn btn-secondary">{{ __('Roles & permissions') }}</a>@endcan
        @if ($canManage)<button type="button" class="btn btn-primary" data-open="invite-member"><x-icon name="plus" size="16" />{{ __('Invite member') }}</button>@endif
    </x-page-header>

    <x-table>
        <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Role') }}</th><th>{{ __('Two-factor') }}</th><th>{{ __('Last sign-in') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($members as $member)
                @php $role = $memberRoles[$member->id] ?? null; $isOwner = (bool) $member->pivot->is_owner; $self = $member->id === auth()->id(); @endphp
                <tr>
                    <td data-label="{{ __('Member') }}"><span class="font-medium">{{ $member->name }}</span>@if ($self) <span class="text-muted">({{ __('you') }})</span>@endif<span class="caption block">{{ $member->email }}</span></td>
                    <td data-label="{{ __('Role') }}">
                        @if ($isOwner)
                            <x-badge tone="brand">{{ __('Owner') }}</x-badge>
                        @elseif ($canManage && ! $self)
                            <form method="POST" action="{{ route('team.update', $member) }}" data-no-loading>
                                @csrf @method('PUT')
                                <label for="role-{{ $member->id }}" class="sr-only">{{ __('Role of :name', ['name' => $member->name]) }}</label>
                                <select name="role_id" id="role-{{ $member->id }}" class="input w-auto py-1.5" data-autosubmit>
                                    @foreach ($roles as $option)<option value="{{ $option->id }}" @selected($role?->id === $option->id)>{{ $option->name }}</option>@endforeach
                                </select>
                            </form>
                        @else
                            {{ $role?->name ?? '—' }}
                        @endif
                    </td>
                    <td data-label="{{ __('Two-factor') }}">@if ($member->hasTwoFactor())<x-badge tone="ok">{{ __('On') }}</x-badge>@else<x-badge tone="neutral">{{ __('Off') }}</x-badge>@endif</td>
                    <td data-label="{{ __('Last sign-in') }}" class="text-muted">{{ $member->last_login_at?->diffForHumans() ?? __('Never') }}</td>
                    <td data-label="{{ __('Status') }}"><x-badge :status="$member->pivot->status" /></td>
                    <td class="text-end">
                        <x-dropdown class="btn btn-ghost btn-icon btn-sm">
                            <x-slot:trigger><x-icon name="more" /><span class="sr-only">{{ __('Actions for :name', ['name' => $member->name]) }}</span></x-slot:trigger>
                            <a class="menu-item" role="menuitem" href="{{ route('team.activity', $member) }}">{{ __('View activity') }}</a>
                            @if ($canManage && ! $isOwner && ! $self)
                                <form method="POST" action="{{ route('team.toggle', $member) }}">@csrf<button class="menu-item" role="menuitem">{{ $member->pivot->status === 'active' ? __('Suspend') : __('Reactivate') }}</button></form>
                                <form method="POST" action="{{ route('team.reset-access', $member) }}" data-confirm="{{ __('Sign :name out everywhere and email a password reset link?', ['name' => $member->name]) }}" data-confirm-label="{{ __('Reset access') }}">@csrf<button class="menu-item" role="menuitem">{{ __('Reset access') }}</button></form>
                                <form method="POST" action="{{ route('team.remove', $member) }}" data-confirm="{{ __('Remove :name from this workspace? Their account stays, but they lose access here.', ['name' => $member->name]) }}" data-confirm-label="{{ __('Remove member') }}">@csrf @method('DELETE')<button class="menu-item menu-item-danger" role="menuitem">{{ __('Remove from workspace') }}</button></form>
                            @endif
                        </x-dropdown>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-table>

    @if ($invitations->count())
        <h2 class="h2 mt-8 mb-3">{{ __('Pending invitations') }}</h2>
        <x-table>
            <thead><tr><th>{{ __('Email') }}</th><th>{{ __('Role') }}</th><th>{{ __('Invited by') }}</th><th>{{ __('Expires') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($invitations as $invitation)
                    <tr>
                        <td data-label="{{ __('Email') }}" class="font-medium">{{ $invitation->email }}</td>
                        <td data-label="{{ __('Role') }}">{{ $invitation->role?->name }}</td>
                        <td data-label="{{ __('Invited by') }}">{{ $invitation->inviter?->name }}</td>
                        <td data-label="{{ __('Expires') }}">{{ $invitation->expires_at->diffForHumans() }}</td>
                        <td class="text-end">@if ($canManage)<x-action :action="route('team.invitations.destroy', $invitation)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Cancel this invitation?')" :confirm-label="__('Cancel invitation')">{{ __('Cancel') }}</x-action>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
    @endif

    @if ($canManage)
        <x-modal id="invite-member" :title="__('Invite a team member')" :auto-open="$errors->hasAny(['email', 'role_id', 'limit'])">
            <form method="POST" action="{{ route('team.invite') }}" class="space-y-4">
                @csrf
                <x-input name="email" type="email" :label="__('Email')" required />
                <x-select name="role_id" :label="__('Role')" :options="$roles->pluck('name', 'id')->all()" required :hint="__('The role decides what this person can see and do.')" />
                @error('limit')<p class="field-error">{{ $message }}</p>@enderror
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Send invitation') }}</button>
                </div>
            </form>
        </x-modal>
    @endif
@endsection
