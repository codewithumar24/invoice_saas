@extends('layouts.app')
@section('title', __('Email templates'))

@php $editable = ['invoice_sent', 'payment_received', 'payment_failed', 'reminder_before_due', 'reminder_due', 'invoice_overdue', 'quote_sent', 'portal_invitation', 'team_invitation']; @endphp

@section('content')
    @component('settings._layout')
        <x-card :title="__('Emails your customers receive')" :padded="false">
            <ul class="divide-y divide-line">
                @foreach ($editable as $key)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                        <div class="min-w-0">
                            <p class="text-sm font-medium">{{ __($templates[$key]['name']) }} @if (in_array($key, $customised))<span class="badge badge-brand">{{ __('Customised') }}</span>@endif</p>
                            <p class="caption truncate">{{ $templates[$key]['subject'] }}</p>
                        </div>
                        <a href="{{ route('settings.email-templates.edit', $key) }}" class="btn btn-secondary btn-sm">{{ auth()->user()->hasPermission('settings.update') ? __('Edit') : __('View') }}</a>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endcomponent
@endsection
