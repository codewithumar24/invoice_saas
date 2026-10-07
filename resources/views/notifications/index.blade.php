@extends('layouts.app')
@section('title', __('Notifications'))

@section('content')
    <x-page-header :title="__('Notifications')">
        <a href="{{ route('account.notifications') }}" class="btn btn-secondary">{{ __('Preferences') }}</a>
        <x-action :action="route('notifications.read-all')" class="btn btn-secondary">{{ __('Mark all read') }}</x-action>
    </x-page-header>

    <nav class="mb-4 flex gap-6 border-b border-line" aria-label="{{ __('Filter') }}">
        @foreach (['' => __('All'), 'unread' => __('Unread'), 'read' => __('Read')] as $value => $label)
            <a href="{{ route('notifications.index', array_filter(['filter' => $value])) }}" class="tab" @if ((string) request('filter') === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($notifications->count())
        <ul class="card divide-y divide-line">
            @foreach ($notifications as $notification)
                <li>
                    <a href="{{ route('notifications.read', $notification->id) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-raised sm:px-6">
                        <span class="mt-2 h-2 w-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-line' : 'bg-brand' }}" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm {{ $notification->read_at ? '' : 'font-medium' }}">{{ $notification->data['title'] ?? '' }}@unless ($notification->read_at)<span class="sr-only"> ({{ __('unread') }})</span>@endunless</span>
                            @if (! empty($notification->data['message']))<span class="block text-sm text-muted">{{ $notification->data['message'] }}</span>@endif
                            <span class="caption">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        {{ $notifications->links() }}
    @else
        <div class="card"><x-empty :title="__('No notifications')" :text="__('Payments received, overdue invoices, low stock and replies from support appear here.')" /></div>
    @endif
@endsection
