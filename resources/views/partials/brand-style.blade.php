{{-- A workspace's own brand colour (custom branding plan feature). Applied in light mode; dark mode keeps the readable default. --}}
@php $brandColor = $brandColor ?? null; @endphp
@if ($brandColor && preg_match('/^#[0-9A-Fa-f]{6}$/', $brandColor) && strtolower($brandColor) !== '#0f6b4f')
    <style>
        html:not(.dark) {
            --c-brand: {{ $brandColor }};
            --c-brand-strong: color-mix(in srgb, {{ $brandColor }} 82%, #000);
            --c-brand-soft: color-mix(in srgb, {{ $brandColor }} 12%, #fff);
        }
    </style>
@endif
