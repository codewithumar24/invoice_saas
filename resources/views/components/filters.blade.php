@props(['search' => true, 'placeholder' => null])
{{-- Filter bar: GET form, so every filtered view has a shareable URL. --}}
<form method="GET" class="mb-4 flex flex-wrap items-end gap-3" data-no-loading>
    @if ($search)
        <div class="w-full sm:w-64">
            <label for="filter-search" class="sr-only">{{ __('Search') }}</label>
            <input type="search" id="filter-search" name="search" value="{{ request('search') }}" placeholder="{{ $placeholder ?? __('Search') }}" class="input">
        </div>
    @endif
    {{ $slot }}
    @if (request('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="direction" value="{{ request('direction') }}">@endif
    <button type="submit" class="btn btn-secondary">{{ __('Filter') }}</button>
    @if (request()->except(['page', 'sort', 'direction']))
        <a href="{{ url()->current() }}" class="btn btn-ghost">{{ __('Clear') }}</a>
    @endif
</form>
