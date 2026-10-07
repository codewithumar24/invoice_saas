@extends('layouts.admin')
@section('title', $ticket->subject)

@section('content')
    <x-page-header :title="$ticket->subject" :subtitle="$ticket->tenant?->name.', '.$ticket->user?->email" :back="route('admin.support.index')">
        <x-badge :status="$ticket->status" class="text-sm" />
        @if ($ticket->tenant)<a href="{{ route('admin.tenants.show', $ticket->tenant) }}" class="btn btn-secondary">{{ __('View tenant') }}</a>@endif
    </x-page-header>

    <div class="max-w-3xl">
        @include('support._thread', ['admin' => true])

        <form method="POST" action="{{ route('admin.support.reply', $ticket->id) }}" class="card card-pad mt-6 space-y-4">
            @csrf
            <x-textarea name="body" :label="__('Reply')" rows="5" />
            <x-select name="status" :label="__('Set status')" :options="['pending' => __('Pending (waiting for the customer)'), 'open' => __('Open'), 'resolved' => __('Resolved'), 'closed' => __('Closed')]" :value="$ticket->status === 'open' ? 'pending' : $ticket->status" required />
            <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Send and update') }}</button></div>
        </form>
    </div>
@endsection
