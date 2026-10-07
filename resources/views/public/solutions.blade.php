@extends('layouts.guest')
@section('title', $solution ? __($solution['title']) : __('Solutions'))

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        @if ($solution)
            <p class="text-sm"><a href="{{ route('solutions') }}" class="link">&larr; {{ __('All solutions') }}</a></p>
            <h1 class="mt-4 max-w-3xl font-serif text-4xl font-semibold tracking-tight">{{ __($solution['headline']) }}</h1>
            <p class="mt-4 text-lg text-muted">{{ __('For :type', ['type' => mb_strtolower(__($solution['title']))]) }}</p>

            {{-- The connected workflow for this kind of business --}}
            <ol class="mt-10 flex flex-wrap items-center gap-x-2 gap-y-3" aria-label="{{ __('Workflow') }}">
                @foreach ($solution['flow'] as $step)
                    <li class="flex items-center gap-2">
                        <span class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm font-medium">{{ __($step) }}</span>
                        @unless ($loop->last)<span class="text-muted rtl:rotate-180" aria-hidden="true">&rarr;</span>@endunless
                    </li>
                @endforeach
            </ol>

            <ul class="mt-10 grid gap-6 sm:grid-cols-3">
                @foreach ($solution['points'] as $point)
                    <li class="border-t-2 border-ink pt-4 font-medium">{{ __($point) }}</li>
                @endforeach
            </ul>

            <div class="mt-12 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="btn btn-primary px-6 py-3 text-base">{{ __('Start free') }}</a>
                <a href="{{ route('features') }}" class="btn btn-secondary px-6 py-3 text-base">{{ __('See every feature') }}</a>
            </div>
        @else
            <h1 class="font-serif text-4xl font-semibold tracking-tight">{{ __('Solutions') }}</h1>
            <p class="mt-4 max-w-2xl text-lg text-muted">{{ __('One product that adapts to the way your business earns money.') }}</p>
            <ul class="mt-10 grid gap-4 sm:grid-cols-2">
                @foreach ($solutions as $slug => $item)
                    <li>
                        <a href="{{ route('solutions', $slug) }}" class="block h-full rounded-lg border border-line p-6 hover:border-brand">
                            <span class="text-lg font-semibold">{{ __($item['title']) }}</span>
                            <span class="mt-1 block text-muted">{{ __($item['headline']) }}</span>
                            <span class="mt-3 block text-sm text-muted">{{ implode(' → ', array_map('__', $item['flow'])) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
