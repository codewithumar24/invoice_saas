@props(['id' => null, 'stack' => true])
{{-- Tables turn into labelled rows on phones (give each <td> a data-label). --}}
<div class="card overflow-hidden">
    <div class="{{ $stack ? '' : 'overflow-x-auto' }} md:overflow-x-auto">
        <table @if ($id) id="{{ $id }}" @endif {{ $attributes->merge(['class' => 'table'.($stack ? ' table-stack' : '')]) }}>
            {{ $slot }}
        </table>
    </div>
</div>
