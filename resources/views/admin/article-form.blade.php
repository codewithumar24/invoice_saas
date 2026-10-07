@extends('layouts.admin')
@section('title', $article->exists ? __('Edit') : __('New'))

@php $published = $type === 'blog' ? $article->published_at !== null : (bool) $article->is_published; @endphp

@section('content')
    <x-page-header :title="$article->exists ? $article->title : ($type === 'blog' ? __('New post') : __('New article'))" :back="route($type === 'blog' ? 'admin.blog' : 'admin.kb')" />

    <form method="POST" action="{{ $article->exists ? route('admin.articles.update', [$type, $article->id]) : route('admin.articles.store', $type) }}" class="card card-pad max-w-3xl space-y-4">
        @csrf
        @if ($article->exists) @method('PUT') @endif
        <x-input name="title" :label="__('Title')" :value="$article->title" required />
        @if ($type === 'kb')
            <x-input name="category" :label="__('Category')" :value="$article->category" required :hint="__('For example: Getting started, Invoices, Payments, Customers, Inventory, Reports, Billing')" />
        @else
            <x-textarea name="excerpt" :label="__('Summary')" :value="$article->excerpt" rows="2" />
        @endif
        <x-textarea name="body" :label="__('Text')" :value="$article->body" rows="16" required :hint="__('Plain text. Leave an empty line between paragraphs.')" />
        <x-switch name="is_published" :label="__('Published')" :checked="$article->exists ? $published : true" />
        <div class="flex justify-end gap-2">
            <a href="{{ route($type === 'blog' ? 'admin.blog' : 'admin.kb') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
@endsection
