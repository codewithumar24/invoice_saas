@extends('layouts.admin')
@section('title', __('Plans'))

@section('content')
    <x-page-header :title="__('Plans')" :subtitle="__('Limits and features are stored per plan in the database. Nothing about a plan is written in code.')">
        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" />{{ __('New plan') }}</a>
    </x-page-header>

    <x-table>
        <thead><tr><th>{{ __('Plan') }}</th><th class="text-end">{{ __('Monthly') }}</th><th class="text-end">{{ __('Yearly') }}</th><th class="text-end">{{ __('Trial days') }}</th><th>{{ __('Limits') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($plans as $plan)
                <tr>
                    <td data-label="{{ __('Plan') }}"><a class="font-medium hover:text-brand" href="{{ route('admin.plans.edit', $plan) }}">{{ $plan->name }}</a><span class="caption block">{{ $plan->slug }}</span></td>
                    <td data-label="{{ __('Monthly') }}" class="num text-end">{{ \App\Support\Money::format($plan->price_monthly, $plan->currency) }}</td>
                    <td data-label="{{ __('Yearly') }}" class="num text-end">{{ \App\Support\Money::format($plan->price_yearly, $plan->currency) }}</td>
                    <td data-label="{{ __('Trial days') }}" class="num text-end">{{ $plan->trial_days }}</td>
                    <td data-label="{{ __('Limits') }}" class="text-muted">{{ __(':users users, :invoices invoices / month', ['users' => $plan->featureValue('max_users') ?? '∞', 'invoices' => $plan->featureValue('max_invoices') ?? '∞']) }}</td>
                    <td data-label="{{ __('Status') }}"><x-badge :tone="$plan->is_active ? 'ok' : 'neutral'">{{ $plan->is_active ? ($plan->is_public ? __('Public') : __('Hidden')) : __('Off') }}</x-badge></td>
                    <td class="text-end"><x-action :action="route('admin.plans.destroy', $plan)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete the plan :name? Plans in use are hidden instead.', ['name' => $plan->name])" :confirm-label="__('Delete plan')">{{ __('Delete') }}</x-action></td>
                </tr>
            @endforeach
        </tbody>
    </x-table>
@endsection
