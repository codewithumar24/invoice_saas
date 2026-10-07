{{-- Ticket conversation, shared by the workspace view and the admin support desk. --}}
<ol class="space-y-4">
    @foreach ($ticket->messages as $message)
        <li class="card card-pad {{ $message->is_staff ? 'border-brand/40 bg-brand-soft/40' : '' }}">
            <p class="flex flex-wrap items-center gap-2 text-sm">
                <span class="font-medium">{{ $message->user?->name }}</span>
                @if ($message->is_staff)<span class="badge badge-brand">{{ __('Support team') }}</span>@endif
                <span class="caption">{{ $message->created_at->diffForHumans() }}</span>
            </p>
            <p class="mt-2 whitespace-pre-line text-sm">{{ $message->body }}</p>
            @if ($message->attachment_path && ! ($admin ?? false))
                <p class="mt-3 text-sm"><a class="link" href="{{ route('support.attachment', [$ticket, $message->id]) }}">{{ $message->attachment_name }}</a></p>
            @elseif ($message->attachment_path)
                <p class="mt-3 text-sm text-muted">{{ __('Attachment: :name', ['name' => $message->attachment_name]) }}</p>
            @endif
        </li>
    @endforeach
</ol>
