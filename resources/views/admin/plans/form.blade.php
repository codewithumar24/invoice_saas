@extends('layouts.admin')
@section('title', $plan->exists ? __('Edit plan') : __('New plan'))

@php
    $limitLabels = ['max_users' => __('Team members'), 'max_customers' => __('Customers'), 'max_invoices' => __('Invoices per month'), 'max_products' => __('Products & services'), 'max_storage' => __('Storage (MB)'), 'max_locations' => __('Inventory locations'), 'max_projects' => __('Projects')];
@endphp

@section('content')
    <x-page-header :title="$plan->exists ? __('Edit plan: :name', ['name' => $plan->name]) : __('New plan')" :back="route('admin.plans.index')" />

    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <x-card :title="__('Plan')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-input name="name" :label="__('Name')" :value="$plan->name" required />
                <div class="sm:col-span-2"><x-input name="description" :label="__('Description')" :value="$plan->description" /></div>
                <x-input name="price_monthly" type="number" step="any" min="0" :label="__('Monthly price')" :value="(float) $plan->price_monthly" required />
                <x-input name="price_yearly" type="number" step="any" min="0" :label="__('Yearly price')" :value="(float) $plan->price_yearly" required />
                <x-select name="currency" :label="__('Currency')" :options="collect(config('currencies'))->map(fn ($c, $code) => $code)->all()" :value="$plan->currency" required />
                <x-input name="trial_days" type="number" min="0" max="365" :label="__('Trial days')" :value="$plan->trial_days ?? 0" required />
                <x-input name="sort_order" type="number" min="0" :label="__('Order on the pricing page')" :value="$plan->sort_order ?? 0" required />
            </div>
            <div class="mt-4 flex flex-wrap gap-6">
                <x-switch name="is_active" :label="__('Available')" :checked="$plan->is_active" />
                <x-switch name="is_public" :label="__('Shown on the pricing page')" :checked="$plan->is_public" />
            </div>
        </x-card>

        <x-card :title="__('Limits')">
            <p class="mb-4 text-sm text-muted">{{ __('Leave a limit empty for unlimited.') }}</p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($limitLabels as $key => $label)
                    <x-input name="limits[{{ $key }}]" type="number" min="0" :label="$label" :value="($values[$key] ?? 'unlimited') === 'unlimited' ? null : $values[$key]" placeholder="{{ __('Unlimited') }}" />
                @endforeach
            </div>
        </x-card>

        <x-card :title="__('Features')">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (config('saas.features') as $feature)
                    <x-checkbox name="features[{{ $feature }}]" :label="__(ucfirst(str_replace('_', ' ', $feature)))" :checked="($values[$feature] ?? '0') === '1'" />
                @endforeach
            </div>
        </x-card>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.plans.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ $plan->exists ? __('Save plan') : __('Create plan') }}</button>
        </div>
    </form>
@endsection
