@props(['id', 'title', 'wide' => false, 'autoOpen' => false])
{{-- Native <dialog>: focus is trapped inside, Esc closes it, and screen readers announce the title. --}}
<dialog id="{{ $id }}" class="modal {{ $wide ? 'modal-wide' : '' }}" aria-labelledby="{{ $id }}-title" @if ($autoOpen) data-auto-open @endif>
    <div class="flex items-center justify-between gap-4 border-b border-line px-6 py-4">
        <h2 class="h2" id="{{ $id }}-title">{{ $title }}</h2>
        <button type="button" class="btn btn-ghost btn-icon" data-close aria-label="{{ __('Close') }}"><x-icon name="x" /></button>
    </div>
    <div class="max-h-[70dvh] overflow-y-auto px-6 py-4">{{ $slot }}</div>
</dialog>
