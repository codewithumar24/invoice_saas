{{-- Settings shell: section navigation on the side, the section's content in the slot. --}}
@php
    $user = auth()->user();
    $canSettings = $user->hasPermission('settings.view') || $user->hasPermission('settings.update');
    $sections = array_filter([
        __('Business') => $canSettings ? ['settings.business' => __('General'), 'settings.branding' => __('Branding'), 'settings.modules' => __('Modules'), 'settings.branches' => __('Branches')] : [],
        __('Money') => $canSettings ? ['settings.invoices' => __('Invoice'), 'settings.tax' => __('Tax'), 'settings.currency' => __('Currency'), 'settings.payments' => __('Payment methods')] : [],
        __('Communication') => $canSettings ? ['settings.notifications' => __('Reminders & briefs'), 'settings.email-templates' => __('Email templates')] : [],
        __('Data') => array_filter([
            'settings.custom-fields' => $canSettings ? __('Custom fields') : null,
            'settings.tags' => $canSettings ? __('Tags') : null,
            'settings.data' => $canSettings ? __('Export & deletion') : null,
            'settings.audit' => $user->hasPermission('audit.view') ? __('Audit log') : null,
        ]),
        __('Access') => array_filter([
            'settings.security' => $canSettings ? __('Security') : null,
            'settings.api-tokens' => feature('api_access') && $user->hasPermission('api.manage') ? __('API tokens') : null,
            'settings.webhooks' => feature('webhooks') && $user->hasPermission('api.manage') ? __('Webhooks') : null,
        ]),
        __('Subscription') => $user->hasPermission('billing.manage') ? ['settings.billing' => __('Plan & billing')] : [],
    ]);
@endphp
<x-page-header :title="__('Settings')" />

<div class="grid gap-6 lg:grid-cols-[13rem_minmax(0,1fr)]">
    <nav aria-label="{{ __('Settings') }}" class="flex gap-1 overflow-x-auto lg:block lg:overflow-visible">
        @foreach ($sections as $group => $links)
            <p class="nav-group hidden first:mt-0 lg:block">{{ $group }}</p>
            @foreach ($links as $route => $label)
                <a href="{{ route($route) }}" class="nav-link shrink-0 whitespace-nowrap" @if (request()->routeIs($route.'*')) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        @endforeach
    </nav>
    <div class="min-w-0 space-y-6">
        {{ $slot }}
    </div>
</div>
