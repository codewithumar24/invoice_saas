@extends('layouts.guest')
@section('title', __('Blog'))

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
        <h1 class="font-serif text-4xl font-semibold tracking-tight">{{ __('Blog') }}</h1>
        @if ($posts->count())
            <ul class="mt-10 divide-y divide-line border-y border-line">
                @foreach ($posts as $post)
                    <li class="py-6">
                        <p class="caption">{{ $post->published_at->format('d M Y') }}</p>
                        <h2 class="mt-1 text-xl font-semibold"><a href="{{ route('blog.show', $post->slug) }}" class="hover:text-brand">{{ $post->title }}</a></h2>
                        @if ($post->excerpt)<p class="mt-2 text-muted">{{ $post->excerpt }}</p>@endif
                    </li>
                @endforeach
            </ul>
            {{ $posts->links() }}
        @else
            <p class="mt-6 text-muted">{{ __('No articles yet. Check back soon.') }}</p>
        @endif
    </div>
@endsection
