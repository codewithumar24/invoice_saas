@extends('layouts.admin')
@section('title', __('Feature flags'))

@section('content')
    <x-page-header :title="__('Feature flags')" :subtitle="__('Roll a feature out gradually: switch it off for everyone, or on and off for individual workspaces by their id.')" />

    <div class="space-y-3">
        @foreach ($flags as $flag)
            @php
                $allow = collect($flag->tenant_overrides ?? [])->filter(fn ($value) => $value === true)->keys()->implode(', ');
                $deny = collect($flag->tenant_overrides ?? [])->filter(fn ($value) => $value === false)->keys()->implode(', ');
            @endphp
            <form method="POST" action="{{ route('admin.flags.update', $flag) }}" class="card card-pad flex flex-wrap items-end gap-4">
                @csrf @method('PUT')
                <div class="min-w-48 flex-1">
                    <p class="font-medium">{{ $flag->name }}</p>
                    <code class="caption">{{ $flag->key }}</code>
                </div>
                <x-switch name="enabled" :label="__('On for everyone')" :checked="$flag->enabled" />
                <div class="w-44"><x-input name="allow_tenants" :label="__('Always on for (ids)')" :value="$allow" id="allow-{{ $flag->id }}" placeholder="12, 40" /></div>
                <div class="w-44"><x-input name="deny_tenants" :label="__('Always off for (ids)')" :value="$deny" id="deny-{{ $flag->id }}" /></div>
                <button type="submit" class="btn btn-secondary">{{ __('Save') }}</button>
            </form>
        @endforeach
    </div>
@endsection
