{{-- One THEN action. Fields are shown or hidden by the action type (resources/js/settings/builder.js). --}}
<div data-builder-row class="rounded-md border border-line p-3">
    <div class="flex items-start gap-2">
        <div class="flex-1">
            <select class="input" data-name="type" aria-label="{{ __('Action') }}">
                @foreach ($actionTypes as $value => $label)<option value="{{ $value }}" @selected(($action['type'] ?? '') === $value)>{{ __($label) }}</option>@endforeach
            </select>
        </div>
        <button type="button" class="btn btn-ghost btn-icon text-muted hover:text-danger" data-builder-remove aria-label="{{ __('Remove action') }}"><x-icon name="trash" size="16" /></button>
    </div>
    <div class="mt-3 space-y-3">
        <div data-only="email_customer">
            <input type="text" class="input" data-name="subject" value="{{ $action['subject'] ?? '' }}" placeholder="{{ __('Subject (leave empty to use the standard email for this event)') }}" aria-label="{{ __('Subject') }}">
        </div>
        <div data-only="email_customer|notify_team">
            <textarea class="input min-h-0" rows="2" data-name="message" placeholder="{{ __('Message') }}" aria-label="{{ __('Message') }}">{{ $action['message'] ?? '' }}</textarea>
        </div>
        <label class="flex items-center gap-2 text-sm" data-only="notify_team">
            <input type="checkbox" class="check" data-name="email" value="1" @checked(! empty($action['email']))>{{ __('Also send by email') }}
        </label>
        <div data-only="add_tag">
            <input type="text" class="input" data-name="tag" value="{{ $action['tag'] ?? '' }}" placeholder="{{ __('Tag to add to the customer') }}" aria-label="{{ __('Tag') }}">
        </div>
        <div data-only="webhook">
            <input type="url" class="input" data-name="url" value="{{ $action['url'] ?? '' }}" placeholder="https://" aria-label="{{ __('Webhook URL') }}">
        </div>
    </div>
</div>
