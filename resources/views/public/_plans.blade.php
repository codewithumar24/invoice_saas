{{-- Plan cards, read from the database (plans + plan_features). Used on the home and pricing pages. --}}
@php
    $limitLabels = ['max_users' => __('team members'), 'max_customers' => __('customers'), 'max_invoices' => __('invoices per month'), 'max_products' => __('products & services'), 'max_projects' => __('projects')];
    $featureLabels = ['business_health' => __('Business Health and signals'), 'recurring_invoices' => __('Recurring invoices and reminders'), 'customer_portal' => __('Customer portal'), 'custom_branding' => __('Your branding'), 'projects' => __('Projects and time tracking'), 'inventory' => __('Inventory and locations'), 'advanced_reports' => __('Advanced reports'), 'automation' => __('Automation rules'), 'api_access' => __('API access'), 'webhooks' => __('Webhooks'), 'white_label' => __('White label'), 'custom_domain' => __('Custom domain')];
@endphp
@php
    // Full class names are listed so Tailwind can find them
    $columns = [1 => 'xl:grid-cols-1', 2 => 'xl:grid-cols-2', 3 => 'xl:grid-cols-3', 4 => 'xl:grid-cols-4', 5 => 'xl:grid-cols-5'][min(5, max(1, $plans->count()))];
@endphp
<div class="grid gap-4 md:grid-cols-2 {{ $columns }}">
    @foreach ($plans as $plan)
        @php $highlight = $plan->slug === 'professional'; @endphp
        <article class="flex flex-col rounded-lg border bg-surface p-6 {{ $highlight ? 'border-brand ring-2 ring-brand/15' : 'border-line' }}">
            <h3 class="text-lg font-semibold">{{ $plan->name }}</h3>
            <p class="mt-1 text-sm text-muted">{{ __($plan->description) }}</p>
            <p class="mt-4">
                @if ((float) $plan->price_monthly > 0)
                    <span class="num text-3xl font-semibold tracking-tight" data-price-monthly>{{ \App\Support\Money::format($plan->price_monthly, $plan->currency) }}</span>
                    <span class="num text-3xl font-semibold tracking-tight" data-price-yearly hidden>{{ \App\Support\Money::format((float) $plan->price_yearly / 12, $plan->currency) }}</span>
                    <span class="text-sm text-muted">/ {{ __('month') }}</span>
                    <span class="block text-xs text-muted" data-price-yearly hidden>{{ __('billed yearly at :amount', ['amount' => \App\Support\Money::format($plan->price_yearly, $plan->currency)]) }}</span>
                @else
                    <span class="text-3xl font-semibold tracking-tight">{{ __('Free') }}</span>
                @endif
            </p>
            <ul class="mt-5 flex-1 space-y-2 text-sm">
                @foreach ($limitLabels as $key => $label)
                    @php $value = $plan->featureValue($key); @endphp
                    <li class="flex gap-2"><x-icon name="check" size="16" class="mt-0.5 text-brand" /><span>{{ $value === null || $value === 'unlimited' ? __('Unlimited :things', ['things' => $label]) : $value.' '.$label }}</span></li>
                @endforeach
                @foreach ($featureLabels as $key => $label)
                    @if ($plan->featureValue($key) === '1')
                        <li class="flex gap-2"><x-icon name="check" size="16" class="mt-0.5 text-brand" /><span>{{ $label }}</span></li>
                    @endif
                @endforeach
            </ul>
            <a href="{{ route('register') }}" class="btn mt-6 {{ $highlight ? 'btn-primary' : 'btn-secondary' }}">{{ (float) $plan->price_monthly > 0 ? __('Start free trial') : __('Start free') }}</a>
        </article>
    @endforeach
</div>
