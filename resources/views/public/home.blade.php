@extends('layouts.guest')
@section('title', __('Invoice, get paid and understand your numbers'))

@php
    $signals = [
        ['attention', __('3 invoices are overdue by more than 15 days.'), __('View overdue invoices')],
        ['risk', __('Your software expenses increased 31% this month.'), __('View expense breakdown')],
        ['risk', __('Customer ABC represents 28% of your monthly revenue.'), __('View sales by customer')],
        ['opportunity', __('Service Package A has generated 42% more revenue than last month.'), __('View sales by service')],
    ];
    $features = [
        [__('Invoicing'), __('Branded invoices with tax, discounts and five templates. Numbers never repeat.')],
        [__('Payments'), __('Online payment links, partial payments, refunds and automatic reminders.')],
        [__('Customers'), __('One profile per customer: revenue, balance, payment pattern, timeline and portal.')],
        [__('Expenses'), __('Record costs with receipts, categories and recurring bills.')],
        [__('Inventory'), __('Stock per location that moves with every sale, with low-stock alerts.')],
        [__('Reports'), __('Revenue, profit, cash flow, receivables and sales, exported to PDF or Excel.')],
        [__('Business Health'), __('Signals that tell you what needs attention before you go looking.')],
    ];
    $steps = [__('Create your workspace'), __('Add customers'), __('Create an invoice'), __('Send it'), __('Get paid'), __('Understand your business')];
    $faqs = [
        [__('Can I start with invoicing only?'), __('Yes. During setup you choose the modules you want. Expenses, inventory, projects and the rest stay hidden until you switch them on.')],
        [__('Is my data separate from other businesses?'), __('Every business is its own workspace. Data is isolated per workspace on the server, and each team member only sees what their role allows.')],
        [__('Which currencies and taxes are supported?'), __('You choose a base currency and can invoice in others; each invoice keeps the exchange rate of its own date. Tax can be added on top of prices or included in them, with several rates.')],
        [__('How do customers pay?'), __('From a secure link on the invoice. You can connect Stripe, PayPal or a local gateway, or show bank details for transfers. Payments by cash or transfer are recorded by hand.')],
        [__('Can I leave and take my data?'), __('Yes. Customers, invoices, payments, expenses and products can be exported to CSV or Excel at any time.')],
    ];
@endphp

@push('head')
    <style>
        /* One orchestrated moment: the signals arrive one after another. */
        @keyframes signal-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        .hero-signal { opacity: 0; animation: signal-in 0.5s ease-out forwards; }
        @media (prefers-reduced-motion: reduce) { .hero-signal { opacity: 1; animation: none; } }
    </style>
@endpush

@section('content')
    {{-- Hero: the product's own voice (business signals) is the first thing a visitor sees --}}
    <section class="border-b border-line bg-raised">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:items-center lg:py-24">
            <div>
                <h1 class="font-serif text-4xl leading-[1.1] font-semibold tracking-tight sm:text-5xl">{{ __('Run your business. Invoice. Get paid. Understand your numbers.') }}</h1>
                <p class="mt-5 max-w-xl text-lg text-muted">{{ __('One workspace for customers, invoices, payments, expenses and stock, that tells you what needs your attention today.') }}</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="btn btn-primary px-6 py-3 text-base">{{ __('Start free') }}</a>
                    <a href="{{ route('features') }}" class="btn btn-secondary px-6 py-3 text-base">{{ __('Explore features') }}</a>
                </div>
                <p class="mt-4 text-sm text-muted">{{ __('Free plan available. No card needed to start.') }}</p>
            </div>

            <div class="rounded-lg border border-line bg-surface shadow-sm" aria-label="{{ __('Example of business signals') }}">
                <div class="flex items-center justify-between border-b border-line px-5 py-3">
                    <p class="font-semibold">{{ __('What needs your attention?') }}</p>
                    <p class="text-xs text-muted">{{ __('Example') }}</p>
                </div>
                <ul class="divide-y divide-line px-5">
                    @foreach ($signals as $index => [$type, $text, $action])
                        <li class="signal signal-{{ $type }} hero-signal py-3.5" style="animation-delay: {{ 0.3 + $index * 0.45 }}s">
                            <p class="font-medium">{{ $text }}</p>
                            <p class="mt-0.5 text-sm text-brand">{{ $action }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Product preview --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6" aria-labelledby="preview-title">
        <h2 id="preview-title" class="font-serif text-3xl font-semibold tracking-tight">{{ __('Open the dashboard and know where you stand') }}</h2>
        <p class="mt-3 max-w-2xl text-muted">{{ __('What happened, what is due, where money comes from and where it goes. The numbers below are an example.') }}</p>

        <div class="mt-8 rounded-lg border border-line bg-canvas p-4 sm:p-6" role="img" aria-label="{{ __('Example dashboard with revenue, expenses, profit and overdue amounts and a revenue chart') }}">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([[__('Revenue'), '$42,500', '+12%'], [__('Expenses'), '$15,400', '+4%'], [__('Estimated profit'), '$18,900', '+9%'], [__('Outstanding'), '$8,200', __('5 invoices')]] as [$label, $value, $note])
                    <div class="rounded-lg border border-line bg-surface p-4">
                        <p class="text-sm text-muted">{{ $label }}</p>
                        <p class="num mt-1 text-2xl font-semibold tracking-tight">{{ $value }}</p>
                        <p class="mt-1 text-xs text-muted">{{ $note }}</p>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 grid gap-3 lg:grid-cols-3">
                <div class="rounded-lg border border-line bg-surface p-4 lg:col-span-2">
                    <p class="text-sm font-medium">{{ __('Revenue and expenses') }}</p>
                    <div class="mt-4 flex h-40 items-end gap-2 sm:gap-3" aria-hidden="true">
                        @foreach ([[38, 22], [46, 24], [52, 30], [49, 27], [63, 31], [72, 36], [68, 33], [84, 40], [92, 38]] as [$revenue, $expense])
                            <div class="flex flex-1 items-end gap-1">
                                <div class="flex-1 rounded-t bg-brand" style="height: {{ $revenue }}%"></div>
                                <div class="flex-1 rounded-t bg-warn/70" style="height: {{ $expense }}%"></div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="rounded-lg border border-line bg-surface p-4">
                    <p class="text-sm font-medium">{{ __('Business health') }}</p>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">{{ __('Collection risk') }}</dt><dd class="font-medium text-warn">{{ __('Medium') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">{{ __('Cash flow') }}</dt><dd class="font-medium text-ok">{{ __('Healthy') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">{{ __('Overdue') }}</dt><dd class="num font-medium text-danger">$650</dd></div>
                    </dl>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="border-y border-line bg-raised" aria-labelledby="features-title">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <h2 id="features-title" class="font-serif text-3xl font-semibold tracking-tight">{{ __('Everything from the first quote to the final profit') }}</h2>
            <dl class="mt-10 grid gap-x-10 gap-y-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as [$name, $text])
                    <div class="border-t-2 border-ink pt-4">
                        <dt class="font-semibold">{{ $name }}</dt>
                        <dd class="mt-1 text-muted">{{ $text }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-10"><a href="{{ route('features') }}" class="link">{{ __('See every feature') }}</a></p>
        </div>
    </section>

    {{-- Business types --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6" aria-labelledby="types-title">
        <h2 id="types-title" class="font-serif text-3xl font-semibold tracking-tight">{{ __('The same product, shaped to how you work') }}</h2>
        <p class="mt-3 max-w-2xl text-muted">{{ __('Tell us your business type during setup and the dashboard, menu and modules start in the right shape for it.') }}</p>
        <ul class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([['freelancers', __('Freelancer'), __('Invoices, clients, time, projects')], ['agencies', __('Agency'), __('Projects, clients, team, time')], ['consultants', __('Consultant'), __('Quotes, retainers, receivables')], ['retail', __('Retail'), __('Products, stock, sales')], ['service-business', __('Service business'), __('Services, jobs, payments')], [null, __('Startup'), __('Recurring revenue, cash flow')], [null, __('Small business'), __('The whole picture in one place')]] as [$slug, $name, $focus])
                <li>
                    @if ($slug)
                        <a href="{{ route('solutions', $slug) }}" class="block rounded-lg border border-line p-4 hover:border-brand">
                            <span class="font-semibold">{{ $name }}</span><span class="block text-sm text-muted">{{ $focus }}</span>
                        </a>
                    @else
                        <div class="rounded-lg border border-line p-4"><span class="font-semibold">{{ $name }}</span><span class="block text-sm text-muted">{{ $focus }}</span></div>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    {{-- How it works: a real sequence, so it is numbered --}}
    <section class="border-y border-line bg-raised" aria-labelledby="how-title">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <h2 id="how-title" class="font-serif text-3xl font-semibold tracking-tight">{{ __('How it works') }}</h2>
            <ol class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-6">
                @foreach ($steps as $index => $step)
                    <li>
                        <span class="num font-serif text-3xl font-semibold text-brand">{{ $index + 1 }}</span>
                        <p class="mt-2 font-medium">{{ $step }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Business Health --}}
    <section class="mx-auto grid max-w-6xl gap-10 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:items-center" aria-labelledby="health-title">
        <div>
            <h2 id="health-title" class="font-serif text-3xl font-semibold tracking-tight">{{ __('It does not just record your business. It reads it.') }}</h2>
            <p class="mt-4 text-muted">{{ __('Most invoicing tools tell you that you made $10,000. This one also tells you that expenses are growing faster than revenue, that three high-value customers went quiet this month, and which invoice to chase first.') }}</p>
            <ul class="mt-6 space-y-2 text-sm">
                @foreach ([__('A health score built from plain calculations you can inspect'), __('Signals for late payers, rising costs, low margins and stock risks'), __('A cash position projected 30, 60 and 90 days ahead'), __('A daily, weekly or monthly brief in your inbox')] as $point)
                    <li class="flex gap-2"><x-icon name="check" size="16" class="mt-0.5 text-brand" />{{ $point }}</li>
                @endforeach
            </ul>
        </div>
        <div class="rounded-lg border border-line bg-surface p-6">
            <p class="text-sm text-muted">{{ __('Your business brief, example') }}</p>
            <dl class="mt-3 grid grid-cols-2 gap-4">
                @foreach ([[__('Revenue'), '$12,500'], [__('Outstanding'), '$2,400'], [__('Expenses'), '$4,100'], [__('Profit'), '$8,400']] as [$label, $value])
                    <div><dt class="text-sm text-muted">{{ $label }}</dt><dd class="num text-xl font-semibold">{{ $value }}</dd></div>
                @endforeach
            </dl>
            <ul class="mt-5 divide-y divide-line border-t border-line">
                <li class="signal signal-attention py-3"><span class="text-sm text-muted">{{ __('Attention') }}</span><span class="block font-medium">{{ __('3 overdue invoices') }}</span></li>
                <li class="signal signal-opportunity py-3"><span class="text-sm text-muted">{{ __('Opportunity') }}</span><span class="block font-medium">{{ __('2 customers increased activity') }}</span></li>
                <li class="signal signal-risk py-3"><span class="text-sm text-muted">{{ __('Risk') }}</span><span class="block font-medium">{{ __('Software expenses increased 22%') }}</span></li>
            </ul>
        </div>
    </section>

    {{-- Testimonials: shown only when real quotes are configured (config/saas.php) --}}
    @if (count(config('saas.testimonials')))
        <section class="border-y border-line bg-raised" aria-labelledby="testimonials-title">
            <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                <h2 id="testimonials-title" class="font-serif text-3xl font-semibold tracking-tight">{{ __('What customers say') }}</h2>
                <div class="mt-8 grid gap-6 md:grid-cols-3">
                    @foreach (config('saas.testimonials') as $testimonial)
                        <figure class="rounded-lg border border-line bg-surface p-6">
                            <blockquote class="text-lg">{{ $testimonial['quote'] }}</blockquote>
                            <figcaption class="mt-4 text-sm"><span class="font-semibold">{{ $testimonial['name'] }}</span><span class="block text-muted">{{ $testimonial['role'] ?? '' }}</span></figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Pricing --}}
    <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6" aria-labelledby="pricing-title">
        <h2 id="pricing-title" class="font-serif text-3xl font-semibold tracking-tight">{{ __('Pricing that grows with you') }}</h2>
        <p class="mt-3 mb-8 text-muted">{{ __('Start free. Upgrade when you need more team members, modules or volume.') }} <a href="{{ route('pricing') }}" class="link">{{ __('Compare plans') }}</a></p>
        @include('public._plans')
    </section>

    {{-- FAQ --}}
    <section class="border-t border-line bg-raised" aria-labelledby="faq-title">
        <div class="mx-auto max-w-3xl px-4 py-20 sm:px-6">
            <h2 id="faq-title" class="font-serif text-3xl font-semibold tracking-tight">{{ __('Questions') }}</h2>
            <div class="mt-8 divide-y divide-line border-y border-line">
                @foreach ($faqs as [$question, $answer])
                    <details class="group py-4">
                        <summary class="flex cursor-pointer items-center justify-between gap-4 font-medium">{{ $question }}<x-icon name="chevron-down" class="transition-transform group-open:rotate-180" /></summary>
                        <p class="mt-3 text-muted">{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA --}}
    <section class="mx-auto max-w-6xl px-4 pt-20 sm:px-6">
        <div class="rounded-lg bg-ink px-6 py-12 text-center text-surface sm:px-12">
            <h2 class="font-serif text-3xl font-semibold tracking-tight">{{ __('Record less by hand. Understand more automatically.') }}</h2>
            <p class="mx-auto mt-3 max-w-xl opacity-80">{{ __('Create your workspace and send your first invoice in a few minutes.') }}</p>
            <a href="{{ route('register') }}" class="btn mt-6 bg-surface px-6 py-3 text-base text-ink hover:opacity-90">{{ __('Start free') }}</a>
        </div>
    </section>
@endsection
