@extends('layouts.guest')
@section('title', __('Pricing'))
@section('description', __('Plans for freelancers, small businesses and teams. Start free and upgrade when you grow.'))

@section('content')
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <h1 class="font-serif text-4xl font-semibold tracking-tight">{{ __('Pricing') }}</h1>
        <p class="mt-4 max-w-2xl text-lg text-muted">{{ __('Start on the free plan, or try a paid plan first. You can change or cancel at any time.') }}</p>

        <div class="mt-8 mb-8 inline-flex rounded-md border border-line p-1" role="group" aria-label="{{ __('Billing period') }}">
            <button type="button" class="btn btn-sm btn-primary" data-billing="monthly" aria-pressed="true">{{ __('Monthly') }}</button>
            <button type="button" class="btn btn-sm btn-ghost" data-billing="yearly" aria-pressed="false">{{ __('Yearly') }}</button>
        </div>

        @include('public._plans')

        <section class="mt-16 max-w-3xl" aria-labelledby="pricing-faq">
            <h2 id="pricing-faq" class="font-serif text-2xl font-semibold tracking-tight">{{ __('Billing questions') }}</h2>
            <dl class="mt-6 space-y-6">
                <div><dt class="font-semibold">{{ __('What happens when I reach a limit?') }}</dt><dd class="mt-1 text-muted">{{ __('Nothing is deleted. You are asked to upgrade before adding more of that item, and everything you already have keeps working.') }}</dd></div>
                <div><dt class="font-semibold">{{ __('Can I cancel?') }}</dt><dd class="mt-1 text-muted">{{ __('Yes, from Settings, Plan & billing. You keep access until the end of the period you paid for.') }}</dd></div>
                <div><dt class="font-semibold">{{ __('Do you offer refunds?') }}</dt><dd class="mt-1 text-muted">{{ __('See the refund policy for the details.') }} <a class="link" href="{{ route('legal.refund-policy') }}">{{ __('Refund Policy') }}</a></dd></div>
                <div><dt class="font-semibold">{{ __('Need custom limits or support?') }}</dt><dd class="mt-1 text-muted">{{ __('Talk to us about the Enterprise plan.') }} <a class="link" href="{{ route('contact') }}">{{ __('Contact us') }}</a></dd></div>
            </dl>
        </section>
    </div>

    <script>
        document.querySelectorAll('[data-billing]').forEach(function (button) {
            button.addEventListener('click', function () {
                var yearly = button.dataset.billing === 'yearly';
                document.querySelectorAll('[data-billing]').forEach(function (b) {
                    var active = b === button;
                    b.classList.toggle('btn-primary', active);
                    b.classList.toggle('btn-ghost', !active);
                    b.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                document.querySelectorAll('[data-price-monthly]').forEach(function (el) { el.hidden = yearly; });
                document.querySelectorAll('[data-price-yearly]').forEach(function (el) { el.hidden = !yearly; });
            });
        });
    </script>
@endsection
