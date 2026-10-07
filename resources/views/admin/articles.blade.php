@extends('layouts.admin')
@section('title', $type === 'blog' ? __('Blog') : __('Knowledge base'))

@section('content')
    <x-page-header :title="$type === 'blog' ? __('Blog') : __('Knowledge base')">
        <a href="{{ route('admin.articles.create', $type) }}" class="btn btn-primary"><x-icon name="plus" size="16" />{{ $type === 'blog' ? __('New post') : __('New article') }}</a>
    </x-page-header>

    <x-table>
        <thead><tr><th>{{ __('Title') }}</th>@if ($type === 'kb')<th>{{ __('Category') }}</th>@endif<th>{{ __('Status') }}</th><th>{{ __('Updated') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($articles as $article)
                @php $published = $type === 'blog' ? $article->published_at !== null : $article->is_published; @endphp
                <tr>
                    <td data-label="{{ __('Title') }}"><a class="font-medium hover:text-brand" href="{{ route('admin.articles.edit', [$type, $article->id]) }}">{{ $article->title }}</a></td>
                    @if ($type === 'kb')<td data-label="{{ __('Category') }}">{{ $article->category }}</td>@endif
                    <td data-label="{{ __('Status') }}"><x-badge :tone="$published ? 'ok' : 'neutral'">{{ $published ? __('Published') : __('Draft') }}</x-badge></td>
                    <td data-label="{{ __('Updated') }}">{{ $article->updated_at->diffForHumans() }}</td>
                    <td class="text-end"><x-action :action="route('admin.articles.destroy', [$type, $article->id])" method="DELETE" class="btn btn-ghost btn-sm text-danger" :confirm="__('Delete :title?', ['title' => $article->title])" :confirm-label="__('Delete')">{{ __('Delete') }}</x-action></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">{{ __('Nothing written yet.') }}</td></tr>
            @endforelse
        </tbody>
    </x-table>
    {{ $articles->links() }}
@endsection
