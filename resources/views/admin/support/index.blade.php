@extends('layouts.admin')
@section('title', __('Support'))

@section('content')
    <x-page-header :title="__('Support tickets')" />
    <nav class="mb-4 flex gap-6 border-b border-line" aria-label="{{ __('Status') }}">
        <a href="{{ route('admin.support.index') }}" class="tab" @if (! request('status')) aria-current="page" @endif>{{ __('Needs attention') }}</a>
        @foreach (['open', 'pending', 'resolved', 'closed'] as $status)
            <a href="{{ route('admin.support.index', ['status' => $status]) }}" class="tab" @if (request('status') === $status) aria-current="page" @endif>{{ status_label($status) }}</a>
        @endforeach
    </nav>

    <x-table>
        <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Tenant') }}</th><th>{{ __('From') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Last reply') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td data-label="{{ __('Subject') }}"><a class="font-medium hover:text-brand" href="{{ route('admin.support.show', $ticket->id) }}">{{ $ticket->subject }}</a></td>
                    <td data-label="{{ __('Tenant') }}">{{ $ticket->tenant?->name }}</td>
                    <td data-label="{{ __('From') }}">{{ $ticket->user?->name }}</td>
                    <td data-label="{{ __('Priority') }}"><x-badge :tone="in_array($ticket->priority, ['high', 'urgent']) ? 'danger' : 'neutral'">{{ status_label($ticket->priority) }}</x-badge></td>
                    <td data-label="{{ __('Last reply') }}">{{ $ticket->last_reply_at?->diffForHumans() }}</td>
                    <td data-label="{{ __('Status') }}"><x-badge :status="$ticket->status" /></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">{{ __('No tickets here.') }}</td></tr>
            @endforelse
        </tbody>
    </x-table>
    {{ $tickets->links() }}
@endsection
