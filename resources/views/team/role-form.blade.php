@extends('layouts.app')
@section('title', $role->exists ? __('Edit role') : __('New role'))

@php $chosen = old('permissions', $selected); @endphp

@section('content')
    <x-page-header :title="$role->exists ? __('Edit role: :name', ['name' => $role->name]) : __('New role')" :back="route('team.roles')" />

    <form method="POST" action="{{ $role->exists ? route('team.roles.update', $role) : route('team.roles.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($role->exists) @method('PUT') @endif

        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" :label="__('Role name')" :value="$role->name" required />
                <x-input name="description" :label="__('Description')" :value="$role->description" />
            </div>
        </x-card>

        <x-card :title="__('Permissions')">
            <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($groups as $group => $permissions)
                    <fieldset>
                        <legend class="mb-2 text-sm font-semibold">{{ __(ucfirst($group)) }}</legend>
                        <div class="space-y-1.5">
                            @foreach ($permissions as $permission)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" class="check" name="permissions[]" value="{{ $permission->id }}" @checked(in_array($permission->id, $chosen))>
                                    {{ __(ucfirst(str_replace('_', ' ', \Illuminate\Support\Str::after($permission->name, '.')))) }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <a href="{{ route('team.roles') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ $role->exists ? __('Save role') : __('Create role') }}</button>
        </div>
    </form>
@endsection
