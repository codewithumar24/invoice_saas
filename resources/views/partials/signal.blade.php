{{-- One business signal: what is happening, why, and the action to take. --}}
<li class="signal signal-{{ $signal->type }} py-3">
    <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
        <div class="min-w-0 flex-1">
            <p class="font-medium">{{ $signal->title }}</p>
            @if ($signal->message)<p class="text-sm text-muted">{{ $signal->message }}</p>@endif
        </div>
        <div class="flex shrink-0 items-center gap-1">
            @if ($signal->action_url)
                <a href="{{ $signal->action_url }}" class="btn btn-secondary btn-sm">{{ $signal->action_label ?: __('View') }}</a>
            @endif
            @if ($dismissible ?? false)
                <form method="POST" action="{{ route('signals.dismiss', $signal) }}">@csrf<button class="btn btn-ghost btn-icon btn-sm" aria-label="{{ __('Dismiss signal') }}"><x-icon name="x" size="14" /></button></form>
            @endif
        </div>
    </div>
</li>
