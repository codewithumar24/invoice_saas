@extends('layouts.guest')
@section('title', $post->title)
@section('description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 155))

@push('head')
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $post->title, 'datePublished' => $post->published_at->toIso8601String(), 'publisher' => ['@type' => 'Organization', 'name' => config('app.name')]], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <p class="text-sm"><a href="{{ route('blog') }}" class="link">&larr; {{ __('Blog') }}</a></p>
        <h1 class="mt-4 font-serif text-4xl font-semibold tracking-tight">{{ $post->title }}</h1>
        <p class="caption mt-2">{{ $post->published_at->format('d M Y') }}</p>
        {{-- Article text is stored as plain text and escaped; blank lines become paragraphs --}}
        <div class="prose-basic mt-6">
            @foreach (preg_split("/\n{2,}/", trim($post->body)) as $paragraph)
                <p>{!! nl2br(e($paragraph)) !!}</p>
            @endforeach
        </div>
    </article>
@endsection
