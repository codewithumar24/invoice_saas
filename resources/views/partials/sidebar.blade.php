{{--
    Main navigation. Every entry is checked twice: is the module switched on for this workspace
    (feature) and may this user see it (permission). Hiding a link is only cosmetic; the routes
    enforce the same rules on the server.
--}}
@php
    $user = auth()->user();
    $can = fn (string $permission) => $user->hasPermission($permission);
    $link = function (string $route, string $label, string $pattern) {
        return '<a href="'.e(route($route)).'" class="nav-link"'.(request()->routeIs($pattern) ? ' aria-current="page"' : '').'>'.e($label).'</a>';
    };
@endphp
<nav aria-label="{{ __('Main') }}" class="flex flex-col px-3 pb-6">
    @if ($can('dashboard.view')){!! $link('dashboard', __('Dashboard'), 'dashboard') !!}@endif
    @if (feature('business_health') && $can('health.view')){!! $link('health.index', __('Business Health'), 'health.*') !!}@endif

    <p class="nav-group">{{ __('Sales') }}</p>
    @if ($can('customers.view')){!! $link('customers.index', __('Customers'), 'customers.*') !!}@endif
    @if (feature('invoicing') && $can('invoices.view')){!! $link('invoices.index', __('Invoices'), 'invoices.*') !!}@endif
    @if (feature('invoicing') && feature('recurring_invoices') && $can('invoices.create')){!! $link('recurring.index', __('Recurring invoices'), 'recurring.*') !!}@endif
    @if (feature('quotes') && $can('quotes.view')){!! $link('quotes.index', __('Quotes'), 'quotes.*') !!}@endif
    @if ($can('payments.view')){!! $link('payments.index', __('Payments'), 'payments.*') !!}@endif

    @if ((feature('expenses') && $can('expenses.view')) || (feature('products') && $can('products.view')) || (feature('inventory') && ($can('inventory.view') || $can('inventory.manage'))))
        <p class="nav-group">{{ __('Money out & stock') }}</p>
        @if (feature('expenses') && $can('expenses.view')){!! $link('expenses.index', __('Expenses'), 'expenses.*') !!}@endif
        @if (feature('products') && $can('products.view')){!! $link('products.index', __('Products & services'), 'products.*') !!}@endif
        @if (feature('inventory') && ($can('inventory.view') || $can('inventory.manage'))){!! $link('inventory.index', __('Inventory'), 'inventory.*') !!}@endif
    @endif

    @if (feature('projects') && ($can('projects.view') || $can('time.track')))
        <p class="nav-group">{{ __('Work') }}</p>
        @if ($can('projects.view')){!! $link('projects.index', __('Projects'), 'projects.*') !!}@endif
        @if ($can('time.track') || $can('projects.manage')){!! $link('time.index', __('Time tracking'), 'time.*') !!}@endif
    @endif

    <p class="nav-group">{{ __('Insight') }}</p>
    @if (feature('reports') && $can('reports.view')){!! $link('reports.index', __('Reports'), 'reports.*') !!}@endif
    @if (feature('automation') && $can('automation.manage')){!! $link('automation.index', __('Automation'), 'automation.*') !!}@endif
    @if ($can('documents.view') || $can('documents.manage')){!! $link('documents.index', __('Documents'), 'documents.*') !!}@endif

    <p class="nav-group">{{ __('Workspace') }}</p>
    @if ($can('team.view') || $can('team.manage')){!! $link('team.index', __('Team'), 'team.*') !!}@endif
    @if ($can('settings.view') || $can('settings.update')){!! $link('settings.business', __('Settings'), 'settings.*') !!}@endif
    {!! $link('support.index', __('Support'), 'support.*') !!}
    <a href="{{ route('help') }}" class="nav-link" target="_blank" rel="noopener">{{ __('Guides') }} <x-icon name="external" size="13" /></a>
</nav>
