@extends('layouts.guest')
@section('title', __('Help centre'))

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6">
        <h1 class="font-serif text-4xl font-semibold tracking-tight">{{ __('Help centre') }}</h1>
        <form method="GET" class="mt-6 flex max-w-xl gap-2" role="search" data-no-loading>
            <label for="q" class="sr-only">{{ __('Search the guides') }}</label>
            <input type="search" name="q" id="q" value="{{ $term }}" class="input" placeholder="{{ __('Search the guides') }}">
            <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
        </form>

        @if ($groups->count())
            <div class="mt-12 grid gap-10 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($groups as $category => $articles)
                    <section>
                        <h2 class="border-b border-line pb-2 font-semibold">{{ __($category) }}</h2>
                        <ul class="mt-3 space-y-2">
                            @foreach ($articles as $article)
                                <li><a href="{{ route('help.show', $article->slug) }}" class="link">{{ $article->title }}</a></li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @else
            <p class="mt-10 text-muted">{{ $term !== '' ? __('No guide matches ":term". Try other words, or contact us.', ['term' => $term]) : __('Guides are being written.') }}</p>
        @endif
    </div>
@endsection
