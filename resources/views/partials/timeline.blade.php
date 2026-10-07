{{-- Activity timeline, reusable for any entity. $items is a collection of ActivityLog. --}}
@if ($items->count())
    <ol class="relative ms-2 space-y-4 border-s border-line ps-6">
        @foreach ($items as $item)
            <li class="relative">
                <span class="absolute -start-[1.6875rem] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-surface bg-brand" aria-hidden="true"></span>
                <p class="text-sm">{{ $item->description }}</p>
                <p class="caption">{{ tdate($item->created_at, 'd M Y, H:i') }}@if ($item->user), {{ $item->user->name }}@endif</p>
            </li>
        @endforeach
    </ol>
@else
    <p class="text-sm text-muted">{{ $empty ?? __('Nothing has happened here yet.') }}</p>
@endif
