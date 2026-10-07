@extends('layouts.guest')
@section('title', $article->title)
@section('description', \Illuminate\Support\Str::limit($article->body, 155))

@section('content')
    <div class="mx-auto grid max-w-5xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[1fr_16rem]">
        <article>
            <p class="text-sm"><a href="{{ route('help') }}" class="link">&larr; {{ __('Help centre') }}</a></p>
            <p class="caption mt-6">{{ __($article->category) }}</p>
            <h1 class="mt-1 font-serif text-3xl font-semibold tracking-tight">{{ $article->title }}</h1>
            <div class="prose-basic mt-6">
                @foreach (preg_split("/\n{2,}/", trim($article->body)) as $paragraph)
                    <p>{!! nl2br(e($paragraph)) !!}</p>
                @endforeach
            </div>
        </article>
        @if ($related->count())
            <aside aria-label="{{ __('Related guides') }}">
                <h2 class="border-b border-line pb-2 text-sm font-semibold">{{ __('More in :category', ['category' => __($article->category)]) }}</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($related as $item)<li><a href="{{ route('help.show', $item->slug) }}" class="link">{{ $item->title }}</a></li>@endforeach
                </ul>
            </aside>
        @endif
    </div>
@endsection
