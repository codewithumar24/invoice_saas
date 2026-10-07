{{-- Shared <head>. $title is the page title; public pages also pass SEO data. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ isset($title) && $title ? $title.' · ' : '' }}{{ $appName ?? config('app.name') }}</title>
@isset($description)<meta name="description" content="{{ $description }}">@endisset
@isset($noindex)<meta name="robots" content="noindex, nofollow">@endisset
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600|ibm-plex-serif:500,600&display=swap" rel="stylesheet">
@if (! empty($faviconUrl))
    <link rel="icon" href="{{ $faviconUrl }}">
@else
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%230f6b4f'/%3E%3Cpath d='M9 10h14M9 16h14M9 22h8' stroke='%23fff' stroke-width='2.5' stroke-linecap='round'/%3E%3C/svg%3E">
@endif
{{-- Set the theme before the page paints, so there is no flash of the wrong colours --}}
<script>
    (function () {
        var p = document.documentElement.dataset.theme || localStorage.getItem('theme') || 'light';
        if (p === 'dark' || (p === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) document.documentElement.classList.add('dark');
    })();
</script>
@vite(['resources/css/app.css', 'resources/js/app.js'])
