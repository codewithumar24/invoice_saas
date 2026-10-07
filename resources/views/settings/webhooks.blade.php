@extends('layouts.app')
@section('title', __('Webhooks'))

@section('content')
    @component('settings._layout')
        @if (session('webhook_secret'))
            <x-alert tone="warn">
                <p class="font-medium">{{ __('Copy the signing secret now. It is shown only once.') }}</p>
                <code class="mt-1 block break-all text-xs">{{ session('webhook_secret') }}</code>
                <button type="button" class="btn btn-secondary btn-sm mt-2" data-copy="{{ session('webhook_secret') }}" data-copied="{{ __('Copied') }}">{{ __('Copy secret') }}</button>
            </x-alert>
        @endif

        <x-card :title="__('Webhooks')" :padded="false">
            <p class="px-4 pt-4 text-sm text-muted sm:px-6">{{ __('Send events to your own systems. Every request is signed: the X-Webhook-Signature header is the HMAC-SHA256 of "timestamp.body" with the signing secret, and X-Webhook-Timestamp carries the timestamp. Failed deliveries are retried.') }}</p>
            @if ($webhooks->count())
                <table class="table table-stack mt-3">
                    <thead><tr><th>{{ __('URL') }}</th><th>{{ __('Events') }}</th><th class="text-end">{{ __('Deliveries') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($webhooks as $webhook)
                            <tr>
                                <td data-label="{{ __('URL') }}" class="break-all"><a class="link" href="{{ route('settings.webhooks.show', $webhook) }}">{{ $webhook->url }}</a></td>
                                <td data-label="{{ __('Events') }}" class="text-muted">{{ implode(', ', $webhook->events ?? []) }}</td>
                                <td data-label="{{ __('Deliveries') }}" class="num text-end">{{ $webhook->deliveries_count }}</td>
                                <td data-label="{{ __('Status') }}"><x-badge :tone="$webhook->is_active ? 'ok' : 'neutral'">{{ $webhook->is_active ? __('On') : __('Off') }}</x-badge></td>
                                <td class="text-end">
                                    <div class="flex flex-wrap justify-end gap-1">
                                        <x-action :action="route('settings.webhooks.toggle', $webhook)" class="btn btn-ghost btn-sm">{{ $webhook->is_active ? __('Switch off') : __('Switch on') }}</x-action>
                                        <x-action :action="route('settings.webhooks.destroy', $webhook)" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete this webhook and its delivery log?')" :confirm-label="__('Delete webhook')">{{ __('Delete') }}</x-action>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <x-empty :title="__('No webhooks yet')" />
            @endif
        </x-card>

        <x-card :title="__('Add a webhook')">
            <form method="POST" action="{{ route('settings.webhooks.store') }}" class="space-y-4">
                @csrf
                <x-input name="url" type="url" :label="__('Endpoint URL')" placeholder="https://" required />
                <fieldset>
                    <legend class="label">{{ __('Events to send') }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($events as $event)
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="check" name="events[]" value="{{ $event }}" @checked(in_array($event, old('events', [])))><code>{{ $event }}</code></label>
                        @endforeach
                    </div>
                    @error('events')<p class="field-error">{{ $message }}</p>@enderror
                </fieldset>
                <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Add webhook') }}</button></div>
            </form>
        </x-card>
    @endcomponent
@endsection
