@extends('layouts.customer')
@section('title', __('Messages'))

@section('content')
    <h1 class="h1 mb-6">{{ __('Messages with :business', ['business' => $portalTenant->name]) }}</h1>
    <div class="max-w-2xl">
        <ul class="mb-6 space-y-3">
            @forelse ($messages as $message)
                <li class="{{ $message->from_customer ? 'ps-8' : 'pe-8' }}">
                    <p class="whitespace-pre-line rounded-md px-4 py-3 text-sm {{ $message->from_customer ? 'bg-brand-soft' : 'border border-line bg-surface' }}">{{ $message->body }}</p>
                    <p class="caption mt-1">{{ $message->from_customer ? __('You') : $portalTenant->name }}, {{ $message->created_at->diffForHumans() }}</p>
                </li>
            @empty
                <li class="card"><x-empty :title="__('No messages yet')" :text="__('Have a question about an invoice or quote? Send a message.')" /></li>
            @endforelse
        </ul>
        <form method="POST" action="{{ route('portal.messages.send', $portalTenant->slug) }}" class="card card-pad space-y-3">
            @csrf
            <x-textarea name="body" :label="__('Your message')" rows="3" required />
            <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Send message') }}</button></div>
        </form>
    </div>
@endsection
