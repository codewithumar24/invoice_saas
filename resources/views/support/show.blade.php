@extends('layouts.app')
@section('title', $ticket->subject)

@section('content')
    <x-page-header :title="$ticket->subject" :subtitle="__($ticket->category).', '.status_label($ticket->priority).' '.__('priority')" :back="route('support.index')">
        <x-badge :status="$ticket->status" class="text-sm" />
    </x-page-header>

    <div class="max-w-3xl">
        @include('support._thread')

        @if ($ticket->status !== 'closed')
            <form method="POST" action="{{ route('support.reply', $ticket) }}" enctype="multipart/form-data" class="card card-pad mt-6 space-y-4">
                @csrf
                <x-textarea name="body" :label="__('Your reply')" rows="4" required />
                <div>
                    <label for="f-attachment" class="label">{{ __('Attachment (optional)') }}</label>
                    <input type="file" name="attachment" id="f-attachment" class="input">
                    @error('attachment')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="flex justify-end"><button type="submit" class="btn btn-primary">{{ __('Send reply') }}</button></div>
            </form>
        @else
            <x-alert tone="info" class="mt-6">{{ __('This ticket is closed. Open a new ticket if you need more help.') }}</x-alert>
        @endif
    </div>
@endsection
