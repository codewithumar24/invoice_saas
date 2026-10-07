@props(['title', 'subtitle' => null, 'back' => null])
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="caption hover:text-ink">&larr; {{ __('Back') }}</a>
        @endif
        <h1 class="h1">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>@endif
    </div>
    @if (! $slot->isEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
