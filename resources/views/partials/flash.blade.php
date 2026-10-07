{{-- Toast region. Screen readers announce new messages (polite for success, assertive for errors). --}}
<div id="toasts" class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:end-6 sm:bottom-6 sm:w-96" aria-live="polite">
    @if (session('success'))
        <div class="toast" role="status">
            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-ok"></span>
            <span class="flex-1">{{ session('success') }}</span>
            <button type="button" class="text-muted hover:text-ink" data-dismiss-toast aria-label="{{ __('Dismiss') }}"><x-icon name="x" size="14" /></button>
        </div>
    @endif
    @if (session('error'))
        <div class="toast" role="alert">
            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-danger"></span>
            <span class="flex-1">{{ session('error') }}</span>
            <button type="button" class="text-muted hover:text-ink" data-dismiss-toast aria-label="{{ __('Dismiss') }}"><x-icon name="x" size="14" /></button>
        </div>
    @endif
</div>

{{-- One confirm dialog for every dangerous action (delete, cancel, refund, remove user ...) --}}
<dialog id="confirm-dialog" class="modal" aria-labelledby="confirm-title">
    <form method="dialog" class="p-6">
        <h2 class="h2" id="confirm-title">{{ __('Please confirm') }}</h2>
        <p class="mt-2 text-sm text-muted" data-confirm-message></p>
        <div class="mt-6 flex justify-end gap-2">
            <button type="submit" value="no" class="btn btn-secondary" data-no-loading>{{ __('Keep it') }}</button>
            <button type="submit" value="yes" class="btn btn-danger" data-confirm-accept data-default="{{ __('Yes, continue') }}">{{ __('Yes, continue') }}</button>
        </div>
    </form>
</dialog>
