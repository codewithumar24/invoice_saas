@extends('layouts.app')
@section('title', __('Search'))

@section('content')
    <x-page-header :title="__('Search')" />

    <form method="GET" action="{{ route('search') }}" class="mb-6 flex max-w-xl gap-2" data-no-loading>
        <label for="q" class="sr-only">{{ __('Search') }}</label>
        <input type="search" name="q" id="q" value="{{ $term }}" class="input" placeholder="{{ __('Customers, invoices, quotes, products, payments, expenses') }}" autofocus>
        <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
    </form>

    @forelse ($groups as $group)
        <x-card :title="$group['label']" :padded="false" class="mb-4">
            <ul class="divide-y divide-line">
                @foreach ($group['results'] as $result)
                    <li><a href="{{ $result['url'] }}" class="flex items-baseline justify-between gap-4 px-4 py-2.5 text-sm hover:bg-raised sm:px-6"><span class="font-medium">{{ $result['title'] }}</span><span class="truncate text-muted">{{ $result['subtitle'] }}</span></a></li>
                @endforeach
            </ul>
        </x-card>
    @empty
        <div class="card">
            <x-empty :title="mb_strlen($term) < 2 ? __('Type at least two characters') : __('Nothing found for ":term"', ['term' => $term])" :text="__('Search by name, number, email, SKU or reference. You only see results you have permission to open.')" />
        </div>
    @endforelse
@endsection
