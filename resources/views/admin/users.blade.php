@extends('layouts.admin')
@section('title', __('Users'))

@section('content')
    <x-page-header :title="__('Users')" />
    <x-filters :placeholder="__('Name or email')" />

    <x-table>
        <thead><tr><th>{{ __('User') }}</th><th class="text-end">{{ __('Workspaces') }}</th><th>{{ __('Two-factor') }}</th><th>{{ __('Last sign-in') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td data-label="{{ __('User') }}"><span class="font-medium">{{ $user->name }}</span>@if ($user->is_super_admin) <span class="badge badge-warn">{{ __('Platform admin') }}</span>@endif<span class="caption block">{{ $user->email }}@unless ($user->email_verified_at) ({{ __('not verified') }})@endunless</span></td>
                    <td data-label="{{ __('Workspaces') }}" class="num text-end">{{ $user->tenants_count }}</td>
                    <td data-label="{{ __('Two-factor') }}">@if ($user->hasTwoFactor())<x-badge tone="ok">{{ __('On') }}</x-badge>@else<x-badge tone="neutral">{{ __('Off') }}</x-badge>@endif</td>
                    <td data-label="{{ __('Last sign-in') }}">{{ $user->last_login_at?->diffForHumans() ?? __('Never') }}</td>
                    <td data-label="{{ __('Status') }}"><x-badge :status="$user->status" /></td>
                    <td class="text-end">
                        @if ($user->id !== auth()->id())
                            <x-action :action="route('admin.users.toggle', $user)" class="btn btn-ghost btn-sm" :confirm="$user->status === 'active' ? __('Suspend :name and sign them out?', ['name' => $user->name]) : __('Reactivate :name?', ['name' => $user->name])" :confirm-label="$user->status === 'active' ? __('Suspend') : __('Reactivate')">{{ $user->status === 'active' ? __('Suspend') : __('Reactivate') }}</x-action>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-table>
    {{ $users->links() }}
@endsection
