@extends('layouts.app')
@section('title', __('Reminders and briefs'))

@section('content')
    @component('settings._layout')
        <form method="POST" action="{{ route('settings.notifications.update') }}" class="space-y-6">
            @csrf @method('PUT')
            <x-card :title="__('Invoice reminders to customers')">
                <x-switch name="reminders_enabled" :label="__('Send reminders automatically')" :checked="setting('reminders_enabled')" />
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-input name="reminder_before_days" type="number" min="0" max="60" :label="__('Days before the due date')" :value="setting('reminder_before_days')" required :hint="__('0 means no reminder before the due date.')" />
                    <x-input name="reminder_after_days" :label="__('Days after the due date')" :value="implode(', ', (array) setting('reminder_after_days'))" :hint="__('Separate with commas, for example: 3, 7, 15')" />
                </div>
                <div class="mt-4"><x-switch name="reminder_on_due" :label="__('Remind on the due date')" :checked="setting('reminder_on_due')" /></div>
                <p class="mt-4 text-sm text-muted">{{ __('Reminders are sent once a day after 8:00 in your timezone, only for invoices that are still unpaid. Edit their wording under Email templates.') }}</p>
            </x-card>

            <x-card :title="__('Business briefs by email')">
                <div class="space-y-3">
                    <x-switch name="digest_daily" :label="__('Daily: a short operational summary')" :checked="setting('digest_daily')" />
                    <x-switch name="digest_weekly" :label="__('Weekly: business performance')" :checked="setting('digest_weekly')" />
                    <x-switch name="digest_monthly" :label="__('Monthly: financial overview')" :checked="setting('digest_monthly')" />
                </div>
                <p class="mt-4 text-sm text-muted">{{ __('Sent to team members who can see Business Health. Each person can also switch briefs off in their own notification preferences.') }}</p>
            </x-card>

            @can('settings.update')<div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save') }}</button></div>@endcan
        </form>
    @endcomponent
@endsection
