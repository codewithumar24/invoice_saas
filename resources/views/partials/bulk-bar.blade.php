{{-- Bar that appears when rows are ticked. $actions: [value => label]; the slot can add extra inputs. --}}
<div class="mb-3 flex flex-wrap items-center gap-3 rounded-md border border-brand/40 bg-brand-soft px-4 py-2 text-sm" data-bulk-bar hidden>
    <span><span class="num font-semibold" data-bulk-count>0</span> {{ __('selected') }}</span>
    <label for="bulk-action" class="sr-only">{{ __('Action') }}</label>
    <select name="action" id="bulk-action" class="input w-auto py-1.5" required>
        <option value="">{{ __('Choose action') }}</option>
        @foreach ($actions as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    {{ $slot ?? '' }}
    <button type="submit" class="btn btn-primary btn-sm" data-confirm="{{ __('Apply this action to the selected rows?') }}" data-confirm-label="{{ __('Apply') }}">{{ __('Apply') }}</button>
</div>
