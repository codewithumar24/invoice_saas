@extends('layouts.admin')
@section('title', __('Contact messages'))

@section('content')
    <x-page-header :title="__('Contact messages')" :subtitle="__('Sent from the contact form on the public website.')" />
    @if ($messages->count())
        <ul class="space-y-3">
            @foreach ($messages as $message)
                <li class="card card-pad">
                    <p class="text-sm"><span class="font-medium">{{ $message->name }}</span> <a class="link" href="mailto:{{ $message->email }}">{{ $message->email }}</a> <span class="caption">{{ $message->created_at->diffForHumans() }}</span></p>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $message->message }}</p>
                </li>
            @endforeach
        </ul>
        {{ $messages->links() }}
    @else
        <div class="card"><x-empty :title="__('No messages yet')" /></div>
    @endif
@endsection
