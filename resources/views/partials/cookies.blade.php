{{--
    Cookie consent. Necessary cookies (session, CSRF, this choice) are always on.
    Analytics and marketing stay OFF until the visitor opts in; nothing optional loads before that.
--}}
@php $consent = json_decode(request()->cookie('cookie_consent', ''), true); $analyticsId = platform_setting('analytics_snippet_id'); @endphp

@if (! is_array($consent))
    <div id="cookie-banner" class="no-print fixed inset-x-4 bottom-4 z-40 mx-auto max-w-2xl rounded-lg border border-line bg-surface p-4 shadow-xl sm:p-5" role="region" aria-label="{{ __('Cookies') }}">
        <p class="text-sm">{{ __('We use necessary cookies to run this site. With your permission we also use analytics cookies to improve it.') }} <a href="{{ route('legal.cookies') }}" class="link">{{ __('Cookie Policy') }}</a></p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary btn-sm" data-cookie-choice="all">{{ __('Accept all') }}</button>
            <button type="button" class="btn btn-secondary btn-sm" data-cookie-choice="necessary">{{ __('Necessary only') }}</button>
            <button type="button" class="btn btn-ghost btn-sm" data-open="cookie-preferences">{{ __('Choose') }}</button>
        </div>
    </div>
@endif

<x-modal id="cookie-preferences" :title="__('Cookie preferences')">
    <form id="cookie-form" class="space-y-4" data-no-loading>
        <x-checkbox name="necessary" :label="__('Necessary')" :hint="__('Sign-in, security and remembering this choice. Always on.')" checked disabled />
        <x-checkbox name="analytics" :label="__('Analytics')" :hint="__('Helps us understand which pages are used.')" :checked="$consent['analytics'] ?? false" />
        <x-checkbox name="marketing" :label="__('Marketing')" :hint="__('Measures the results of our campaigns.')" :checked="$consent['marketing'] ?? false" />
        <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save preferences') }}</button></div>
    </form>
</x-modal>

<script>
    (function () {
        function save(consent) {
            document.cookie = 'cookie_consent=' + encodeURIComponent(JSON.stringify(consent)) + ';path=/;max-age=' + 60 * 60 * 24 * 180 + ';samesite=lax' + (location.protocol === 'https:' ? ';secure' : '');
            location.reload();
        }
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-cookie-choice]');
            if (button) save({ analytics: button.dataset.cookieChoice === 'all', marketing: button.dataset.cookieChoice === 'all' });
        });
        var form = document.getElementById('cookie-form');
        if (form) form.addEventListener('submit', function (event) {
            event.preventDefault();
            save({ analytics: form.elements.analytics.checked, marketing: form.elements.marketing.checked });
        });
    })();
</script>

{{-- Analytics loads only after consent, and only when the platform has configured a measurement id --}}
@if (($consent['analytics'] ?? false) && $analyticsId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $analyticsId }}"></script>
    <script>window.dataLayer = window.dataLayer || []; function gtag() { dataLayer.push(arguments); } gtag('js', new Date()); gtag('config', @json($analyticsId), { anonymize_ip: true });</script>
@endif
