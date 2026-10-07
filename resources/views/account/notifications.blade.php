@extends('layouts.app')
@section('title', __('Notification preferences'))

@section('content')
    <x-page-header :title="__('Your account')" />
    @include('account._nav')

    <form method="POST" action="{{ route('account.notifications.update') }}" class="max-w-3xl">
        @csrf @method('PUT')
        <x-table>
            <thead><tr><th>{{ __('Notify me about') }}</th><th>{{ __('In the app') }}</th><th>{{ __('By email') }}</th></tr></thead>
            <tbody>
                @foreach ($categories as $key => $label)
                    <tr>
                        <td class="font-medium">{{ __($label) }}@if ($key === 'security')<span class="caption block">{{ __('Always on, for your protection') }}</span>@endif</td>
                        @foreach (['app' => __('In the app'), 'email' => __('By email')] as $channel => $channelLabel)
                            <td data-label="{{ $channelLabel }}">
                                <input type="checkbox" class="check" name="preferences[{{ $key }}][{{ $channel }}]" value="1" aria-label="{{ __($label) }}: {{ $channelLabel }}"
                                    @checked($user->wantsNotification($key, $channel)) @disabled($key === 'security')>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-4 flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Save preferences') }}</button></div>
    </form>
@endsection
