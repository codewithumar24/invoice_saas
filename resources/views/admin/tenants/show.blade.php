@extends('layouts.admin')
@section('title', $tenant->name)

@section('content')
    <x-page-header :title="$tenant->name" :subtitle="$tenant->slug" :back="route('admin.tenants.index')">
        <x-badge :status="$tenant->status" class="text-sm" />
        <x-action :action="route('admin.tenants.impersonate', $tenant)" :confirm="__('Sign in as the owner of :name? This is recorded in the audit log and shown to the workspace.', ['name' => $tenant->name])" :confirm-label="__('Impersonate')" class="btn btn-secondary">{{ __('Impersonate owner') }}</x-action>
        <x-action :action="route('admin.tenants.toggle', $tenant)" :confirm="$tenant->status === 'active' ? __('Suspend :name? Its members can no longer use the workspace.', ['name' => $tenant->name]) : __('Activate :name?', ['name' => $tenant->name])" :confirm-label="$tenant->status === 'active' ? __('Suspend') : __('Activate')" class="btn {{ $tenant->status === 'active' ? 'btn-danger' : 'btn-primary' }}">{{ $tenant->status === 'active' ? __('Suspend') : __('Activate') }}</x-action>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat :label="__('Members')" :value="$members->count()" />
        <x-stat :label="__('Customers')" :value="number_format($counts['customers'])" />
        <x-stat :label="__('Invoices')" :value="number_format($counts['invoices'])" />
        <x-stat :label="__('Products')" :value="number_format($counts['products'])" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <x-card :title="__('Workspace')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Business type') }}</dt><dd>{{ $tenant->business_type ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Country') }}</dt><dd>{{ $tenant->country ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Currency') }}</dt><dd>{{ $tenant->currency }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Timezone') }}</dt><dd>{{ $tenant->timezone }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Custom domain') }}</dt><dd>{{ $tenant->custom_domain ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Registered') }}</dt><dd>{{ $tenant->created_at->format('d M Y') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Onboarded') }}</dt><dd>{{ $tenant->onboarded_at?->format('d M Y') ?? __('Not finished') }}</dd></div>
                </dl>
            </x-card>

            <x-card :title="__('Subscription')">
                <p class="text-lg font-semibold">{{ $subscription?->plan?->name ?? __('No plan') }}</p>
                @if ($subscription)
                    <p class="mt-1 flex items-center gap-2 text-sm text-muted"><x-badge :status="$subscription->status" />{{ \App\Support\Money::format($subscription->amount, $subscription->currency) }} / {{ $subscription->billing_cycle }}</p>
                    <p class="caption mt-1">{{ __('Period ends :date', ['date' => $subscription->current_period_end?->format('d M Y') ?? '—']) }}</p>
                @endif
                <form method="POST" action="{{ route('admin.tenants.plan', $tenant) }}" class="mt-4 space-y-3 border-t border-line pt-4" data-confirm="{{ __('Change the plan of this workspace?') }}" data-confirm-label="{{ __('Change plan') }}">
                    @csrf
                    <x-select name="plan_id" :label="__('Change plan')" :options="$plans->pluck('name', 'id')->all()" :value="$tenant->plan_id" required />
                    <x-select name="cycle" :label="__('Billing')" :options="['monthly' => __('Monthly'), 'yearly' => __('Yearly')]" required />
                    <button type="submit" class="btn btn-secondary btn-sm">{{ __('Change plan') }}</button>
                </form>
            </x-card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-card :title="__('Members')" :padded="false">
                <table class="table table-stack">
                    <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Last sign-in') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($members as $member)
                            <tr>
                                <td data-label="{{ __('Name') }}" class="font-medium">{{ $member->name }}@if ($member->pivot->is_owner) <span class="badge badge-brand">{{ __('Owner') }}</span>@endif</td>
                                <td data-label="{{ __('Email') }}">{{ $member->email }}</td>
                                <td data-label="{{ __('Last sign-in') }}">{{ $member->last_login_at?->diffForHumans() ?? __('Never') }}</td>
                                <td data-label="{{ __('Status') }}"><x-badge :status="$member->pivot->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>

            <x-card :title="__('Usage this month')">
                @if ($usage->count())
                    <dl class="grid gap-4 sm:grid-cols-3">
                        @foreach ($usage as $metric => $quantity)
                            <div><dt class="text-sm text-muted">{{ status_label($metric) }}</dt><dd class="num text-lg font-semibold">{{ $metric === 'storage_bytes' ? round($quantity / 1048576, 1).' MB' : number_format($quantity) }}</dd></div>
                        @endforeach
                    </dl>
                @else
                    <p class="text-sm text-muted">{{ __('No usage recorded this month.') }}</p>
                @endif
            </x-card>

            <x-card :title="__('Billing')" :padded="false">
                @if ($billing->count())
                    <table class="table table-stack">
                        <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($billing as $invoice)
                                <tr>
                                    <td data-label="{{ __('Number') }}" class="num">{{ $invoice->number }}</td>
                                    <td data-label="{{ __('Date') }}">{{ $invoice->created_at->format('d M Y') }}</td>
                                    <td data-label="{{ __('Description') }}">{{ $invoice->description }}</td>
                                    <td data-label="{{ __('Amount') }}" class="num text-end">{{ \App\Support\Money::format($invoice->amount, $invoice->currency) }}</td>
                                    <td data-label="{{ __('Status') }}"><x-badge :status="$invoice->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="card-pad text-sm text-muted">{{ __('No subscription invoices yet.') }}</p>
                @endif
            </x-card>
        </div>
    </div>
@endsection
