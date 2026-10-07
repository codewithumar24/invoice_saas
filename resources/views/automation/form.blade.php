@extends('layouts.app')
@section('title', $rule->exists ? __('Edit rule') : __('New rule'))

@php
    $conditions = old('conditions', $rule->conditions ?? []);
    $actions = old('actions', $rule->actions ?? []);
    if (! $rule->exists && ! $actions) {
        $actions = [['type' => 'notify_team', 'message' => '']];
    }
@endphp

@section('content')
    <x-page-header :title="$rule->exists ? __('Edit rule') : __('New automation rule')" :back="route('automation.index')" />

    <form method="POST" action="{{ $rule->exists ? route('automation.update', $rule) : route('automation.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($rule->exists) @method('PUT') @endif

        <x-card>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input name="name" :label="__('Rule name')" :value="$rule->name" required autofocus />
                <div class="pt-7"><x-switch name="is_active" :label="__('Rule is on')" :checked="$rule->is_active" /></div>
            </div>
        </x-card>

        {{-- WHEN --}}
        <x-card :title="__('When')">
            <x-select name="trigger" :label="__('This happens')" :options="collect($triggers)->map(fn ($label) => __($label))->all()" :value="$rule->trigger" required />
        </x-card>

        {{-- IF --}}
        <x-card :title="__('If')">
            <p class="mb-3 text-sm text-muted">{{ __('Optional. The rule only runs when every condition is true. Without conditions it always runs.') }}</p>
            <div data-builder="conditions">
                <div data-builder-rows class="space-y-2">
                    @foreach ($conditions as $condition)
                        <div data-builder-row class="grid gap-2 sm:grid-cols-[1fr_1fr_1fr_2.25rem]">
                            <select class="input" data-name="field" aria-label="{{ __('Field') }}">@foreach ($fields as $value => $label)<option value="{{ $value }}" @selected(($condition['field'] ?? '') === $value)>{{ __($label) }}</option>@endforeach</select>
                            <select class="input" data-name="operator" aria-label="{{ __('Comparison') }}">@foreach ($operators as $value => $label)<option value="{{ $value }}" @selected(($condition['operator'] ?? '') === $value)>{{ __($label) }}</option>@endforeach</select>
                            <input type="text" class="input" data-name="value" value="{{ $condition['value'] ?? '' }}" placeholder="{{ __('Value') }}" aria-label="{{ __('Value') }}" required>
                            <button type="button" class="btn btn-ghost btn-icon text-muted hover:text-danger" data-builder-remove aria-label="{{ __('Remove condition') }}"><x-icon name="trash" size="16" /></button>
                        </div>
                    @endforeach
                </div>
                <template>
                    <div data-builder-row class="grid gap-2 sm:grid-cols-[1fr_1fr_1fr_2.25rem]">
                        <select class="input" data-name="field" aria-label="{{ __('Field') }}">@foreach ($fields as $value => $label)<option value="{{ $value }}">{{ __($label) }}</option>@endforeach</select>
                        <select class="input" data-name="operator" aria-label="{{ __('Comparison') }}">@foreach ($operators as $value => $label)<option value="{{ $value }}">{{ __($label) }}</option>@endforeach</select>
                        <input type="text" class="input" data-name="value" placeholder="{{ __('Value') }}" aria-label="{{ __('Value') }}" required>
                        <button type="button" class="btn btn-ghost btn-icon text-muted hover:text-danger" data-builder-remove aria-label="{{ __('Remove condition') }}"><x-icon name="trash" size="16" /></button>
                    </div>
                </template>
                <button type="button" class="btn btn-secondary btn-sm mt-3" data-builder-add><x-icon name="plus" size="14" />{{ __('Add condition') }}</button>
            </div>
        </x-card>

        {{-- THEN --}}
        <x-card :title="__('Then')">
            <div data-builder="actions">
                <div data-builder-rows class="space-y-3">
                    @foreach ($actions as $action)
                        @include('automation._action', ['action' => $action])
                    @endforeach
                </div>
                <template>@include('automation._action', ['action' => ['type' => 'notify_team']])</template>
                <button type="button" class="btn btn-secondary btn-sm mt-3" data-builder-add><x-icon name="plus" size="14" />{{ __('Add action') }}</button>
            </div>
            @error('actions')<p class="field-error">{{ $message }}</p>@enderror
            @foreach ($errors->get('actions.*') as $messages)<p class="field-error">{{ $messages[0] }}</p>@endforeach
            <p class="caption mt-4">{{ __('Messages can include:') }} @verbatim{{customer_name}}, {{invoice_number}}, {{amount}}, {{days_overdue}}, {{product_name}}, {{stock}}@endverbatim</p>
        </x-card>

        <div class="flex justify-end gap-2">
            <a href="{{ route('automation.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ $rule->exists ? __('Save rule') : __('Create rule') }}</button>
        </div>
    </form>

    @if ($rule->exists && isset($logs) && $logs->count())
        <h2 class="h2 mt-8 mb-3">{{ __('Recent runs') }}</h2>
        <div class="max-w-3xl">
            <x-table>
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Result') }}</th><th>{{ __('Detail') }}</th></tr></thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr><td data-label="{{ __('When') }}">{{ tdate($log->created_at, 'd M Y, H:i') }}</td><td data-label="{{ __('Result') }}"><x-badge :status="$log->status" /></td><td data-label="{{ __('Detail') }}" class="text-muted">{{ $log->message ?: '—' }}</td></tr>
                    @endforeach
                </tbody>
            </x-table>
        </div>
    @endif
@endsection
