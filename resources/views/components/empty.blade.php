@props(['title', 'text' => null])
{{-- An empty screen is an invitation to act: say what belongs here and offer the first step. --}}
<div {{ $attributes->merge(['class' => 'px-6 py-12 text-center']) }}>
    <h3 class="h3">{{ $title }}</h3>
    @if ($text)<p class="mx-auto mt-1 max-w-md text-sm text-muted">{{ $text }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>@endif
</div>
