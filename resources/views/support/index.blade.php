@extends('layouts.app')
@section('title', __('Support'))

@section('content')
    <x-page-header :title="__('Support')" :subtitle="__('Ask the platform team for help. Replies arrive here and as a notification.')">
        <a href="{{ route('help') }}" class="btn btn-secondary" target="_blank" rel="noopener">{{ __('Read the guides') }}</a>
        <button type="button" class="btn btn-primary" data-open="new-ticket"><x-icon name="plus" size="16" />{{ __('New ticket') }}</button>
    </x-page-header>

    @if ($tickets->count())
        <x-table>
            <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Category') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Opened by') }}</th><th>{{ __('Last reply') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
                @foreach ($tickets as $ticket)
                    <tr>
                        <td data-label="{{ __('Subject') }}"><a href="{{ route('support.show', $ticket) }}" class="font-medium hover:text-brand">{{ $ticket->subject }}</a></td>
                        <td data-label="{{ __('Category') }}">{{ __($categories[$ticket->category] ?? $ticket->category) }}</td>
                        <td data-label="{{ __('Priority') }}">{{ status_label($ticket->priority) }}</td>
                        <td data-label="{{ __('Opened by') }}">{{ $ticket->user?->name }}</td>
                        <td data-label="{{ __('Last reply') }}" class="text-muted">{{ $ticket->last_reply_at?->diffForHumans() }}</td>
                        <td data-label="{{ __('Status') }}"><x-badge :status="$ticket->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </x-table>
        {{ $tickets->links() }}
    @else
        <div class="card">
            <x-empty :title="__('No support tickets')" :text="__('Stuck or found a problem? Open a ticket and describe what you were doing and what happened.')">
                <button type="button" class="btn btn-primary" data-open="new-ticket">{{ __('Open a ticket') }}</button>
            </x-empty>
        </div>
    @endif

    <x-modal id="new-ticket" :title="__('New support ticket')" wide :auto-open="$errors->hasAny(['subject', 'body', 'category', 'priority', 'attachment'])">
        <form method="POST" action="{{ route('support.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <x-input name="subject" :label="__('Subject')" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-select name="category" :label="__('Category')" :options="collect($categories)->map(fn ($label) => __($label))->all()" required />
                <x-select name="priority" :label="__('Priority')" :options="['low' => __('Low'), 'normal' => __('Normal'), 'high' => __('High'), 'urgent' => __('Urgent')]" value="normal" required />
            </div>
            <x-textarea name="body" :label="__('What do you need help with?')" rows="6" required />
            <div>
                <label for="f-attachment" class="label">{{ __('Attachment (optional)') }}</label>
                <input type="file" name="attachment" id="f-attachment" class="input">
                @error('attachment')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-close>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Send ticket') }}</button>
            </div>
        </form>
    </x-modal>
@endsection
