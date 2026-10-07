@props(['column', 'label'])
@php
    $active = request('sort') === $column;
    $next = $active && request('direction') === 'asc' ? 'desc' : 'asc';
@endphp
<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $next, 'page' => null]) }}" class="inline-flex items-center gap-1 hover:text-ink" @if ($active) aria-sort="{{ request('direction') === 'asc' ? 'ascending' : 'descending' }}" @endif>
    {{ $label }}
    @if ($active)<x-icon :name="request('direction') === 'asc' ? 'arrow-up' : 'arrow-down'" size="12" />@endif
</a>
