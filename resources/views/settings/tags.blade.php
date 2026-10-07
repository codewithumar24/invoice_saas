@extends('layouts.app')
@section('title', __('Tags'))

@section('content')
    @component('settings._layout')
        <x-card :title="__('Tags')">
            <p class="mb-4 text-sm text-muted">{{ __('Tags group customers, invoices, expenses and products, for example VIP, Late Payer, Enterprise or Follow-up.') }}</p>
            @if ($tags->count())
                <ul class="mb-6 flex flex-wrap gap-2">
                    @foreach ($tags as $tag)
                        <li class="flex items-center gap-2 rounded-full border border-line py-1 ps-3 pe-1 text-sm">
                            <span class="h-2 w-2 rounded-full" style="background: {{ $tag->color }}"></span>{{ $tag->name }}
                            @can('settings.update')
                                <form method="POST" action="{{ route('settings.tags.destroy', $tag) }}" data-confirm="{{ __('Delete the tag :name? It is removed from every record.', ['name' => $tag->name]) }}" data-confirm-label="{{ __('Delete tag') }}">
                                    @csrf @method('DELETE')
                                    <button class="grid h-6 w-6 place-items-center rounded-full text-muted hover:bg-danger-soft hover:text-danger" aria-label="{{ __('Delete tag :name', ['name' => $tag->name]) }}"><x-icon name="x" size="12" /></button>
                                </form>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
            @can('settings.update')
                <form method="POST" action="{{ route('settings.tags.store') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-48 flex-1 sm:max-w-xs"><x-input name="name" :label="__('New tag')" required /></div>
                    <div>
                        <label for="f-color" class="label">{{ __('Colour') }}</label>
                        <input type="color" name="color" id="f-color" value="{{ old('color', '#64748b') }}" class="h-10 w-16 cursor-pointer rounded-md border border-line bg-surface p-1">
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('Add tag') }}</button>
                </form>
            @endcan
        </x-card>
    @endcomponent
@endsection
