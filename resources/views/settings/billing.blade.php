@extends('layouts.app')
@section('title', __('Plan and billing'))

@php
    $current = $subscription?->plan;
    $limitLabels = ['max_users' => __('Team members'), 'max_customers' => __('Customers'), 'max_invoices' => __('Invoices per month'), 'max_products' => __('Products & services'), 'max_storage' => __('Storage (MB)'), 'max_locations' => __('Inventory locations'), 'max_projects' => __('Projects')];
@endphp

@section('content')
    @component('settings._layout')
        @if (request('checkout') === 'success')
            <x-alert tone="ok">{{ __('Thank you. Your payment is being confirmed and your plan updates within a minute.') }}</x-alert>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <x-card :title="__('Current plan')">
                <p class="text-2xl font-semibold">{{ $current?->name ?? __('No plan') }}</p>
                @if ($subscription)
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted">
                        <x-badge :status="$subscription->status" />
                        @if ($subscription->status === 'trial')
                            {{ __('Trial ends :date', ['date' => tdate($subscription->trial_ends_at)]) }}
                        @elseif ($subscription->status === 'cancelled')
                            {{ __('Access until :date', ['date' => tdate($subscription->ends_at)]) }}
                        @elseif ((float) $subscription->amount > 0)
                            {{ __(':amount per :cycle, renews :date', ['amount' => money($subscription->amount, $subscription->currency), 'cycle' => $subscription->billing_cycle === 'yearly' ? __('year') : __('month'), 'date' => tdate($subscription->current_period_end)]) }}
                        @endif
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($subscription->status === 'cancelled' && $subscription->ends_at?->isFuture())
                            <x-action :action="route('settings.billing.resume')" class="btn btn-primary btn-sm">{{ __('Resume subscription') }}</x-action>
                        @elseif (in_array($subscription->status, ['active', 'trial', 'past_due']) && (float) ($current->price_monthly ?? 0) > 0)
                            <x-action :action="route('settings.billing.cancel')" class="btn btn-danger btn-sm" :confirm="__('Cancel your subscription? You keep access until the end of the current period, then the workspace moves to the free plan.')" :confirm-label="__('Cancel subscription')">{{ __('Cancel subscription') }}</x-action>
                        @endif
                    </div>
                @endif
                <dl class="mt-6 space-y-2 border-t border-line pt-4 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Payment method') }}</dt><dd>{{ $subscription?->gateway === 'stripe' ? __('Card (Stripe)') : __('Invoice / bank transfer') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Referral link') }}</dt><dd class="break-all text-end"><button type="button" class="link" data-copy="{{ route('referral', $tenant->referral_code) }}" data-copied="{{ __('Copied') }}">{{ __('Copy link') }}</button> <span class="text-muted">({{ trans_choice(':count click|:count clicks', $tenant->referral_clicks, ['count' => $tenant->referral_clicks]) }})</span></dd></div>
                </dl>
            </x-card>

            <x-card :title="__('Usage')">
                <dl class="space-y-4">
                    @foreach ($usage as $row)
                        <div>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <dt>{{ $row['label'] }}</dt>
                                <dd class="num">{{ $row['used'] }}@if ($row['limit'] !== null) / {{ $row['limit'] }}@elseif (str_contains($row['label'], __('this month')))@else <span class="text-muted">/ {{ __('unlimited') }}</span>@endif</dd>
                            </div>
                            @if ($row['limit'] !== null && $row['limit'] > 0)
                                @php $percent = min(100, round($row['used'] / $row['limit'] * 100)); @endphp
                                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-line"><div class="h-full rounded-full {{ $percent >= 100 ? 'bg-danger' : ($percent >= 80 ? 'bg-warn' : 'bg-brand') }}" style="width: {{ $percent }}%"></div></div>
                            @endif
                        </div>
                    @endforeach
                </dl>
            </x-card>
        </div>

        <div>
            <h2 class="h2 mb-3">{{ __('Change plan') }}</h2>
            <form method="POST" action="{{ route('settings.billing.change') }}" data-confirm="{{ __('Change your plan now?') }}" data-confirm-label="{{ __('Change plan') }}">
                @csrf
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($plans as $plan)
                        <label class="card card-pad block cursor-pointer has-[:checked]:border-brand has-[:checked]:ring-2 has-[:checked]:ring-brand/20">
                            <span class="flex items-start justify-between gap-3">
                                <span>
                                    <span class="font-semibold">{{ $plan->name }}</span>
                                    @if ($current?->id === $plan->id)<span class="badge badge-brand ms-1">{{ __('Current') }}</span>@endif
                                    <span class="block text-sm text-muted">{{ __($plan->description) }}</span>
                                </span>
                                <input type="radio" name="plan_id" value="{{ $plan->id }}" class="mt-1 accent-[var(--c-brand)]" @checked(old('plan_id', $current?->id) == $plan->id) required>
                            </span>
                            <span class="mt-3 block"><span class="num text-2xl font-semibold">{{ (float) $plan->price_monthly > 0 ? money($plan->price_monthly, $plan->currency) : __('Free') }}</span>@if ((float) $plan->price_monthly > 0)<span class="text-sm text-muted"> / {{ __('month') }}</span>@endif</span>
                            <ul class="mt-3 space-y-1 text-sm text-muted">
                                @foreach ($limitLabels as $key => $label)
                                    @php $value = $plan->featureValue($key); @endphp
                                    <li class="flex justify-between gap-3"><span>{{ $label }}</span><span class="num text-ink">{{ $value === null || $value === 'unlimited' ? __('Unlimited') : $value }}</span></li>
                                @endforeach
                            </ul>
                            <p class="mt-3 flex flex-wrap gap-1">
                                @foreach (config('saas.features') as $feature)
                                    @if ($plan->featureValue($feature) === '1')<span class="badge badge-neutral">{{ __(ucfirst(str_replace('_', ' ', $feature))) }}</span>@endif
                                @endforeach
                            </p>
                        </label>
                    @endforeach
                </div>

                <div class="card card-pad mt-4 flex flex-wrap items-end gap-4">
                    <x-select name="cycle" :label="__('Billing')" :options="['monthly' => __('Monthly'), 'yearly' => __('Yearly')]" :value="$subscription?->billing_cycle ?? 'monthly'" required />
                    <div class="w-48"><x-input name="coupon" :label="__('Coupon code')" autocomplete="off" /></div>
                    <button type="submit" class="btn btn-primary">{{ $gateway ? __('Continue to payment') : __('Change plan') }}</button>
                    @unless ($gateway)<p class="w-full text-sm text-muted">{{ __('Paid plans are billed by invoice. Your plan changes immediately and the invoice appears in your billing history.') }}</p>@endunless
                </div>
            </form>
        </div>

        <div>
            <h2 class="h2 mb-3">{{ __('Billing history') }}</h2>
            @if ($history->count())
                <x-table>
                    <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Description') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($history as $invoice)
                            <tr>
                                <td data-label="{{ __('Number') }}" class="num font-medium">{{ $invoice->number }}</td>
                                <td data-label="{{ __('Date') }}">{{ tdate($invoice->created_at) }}</td>
                                <td data-label="{{ __('Description') }}">{{ $invoice->description }}</td>
                                <td data-label="{{ __('Amount') }}" class="num text-end">{{ money($invoice->amount, $invoice->currency) }}</td>
                                <td data-label="{{ __('Status') }}"><x-badge :status="$invoice->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
            @else
                <div class="card"><x-empty :title="__('No billing history yet')" :text="__('Invoices for your subscription appear here.')" /></div>
            @endif
        </div>
    @endcomponent
@endsection
