@props(['label' => null, 'align' => 'end'])
<details class="menu">
    <summary {{ $attributes->merge(['class' => 'btn btn-secondary btn-sm']) }} aria-haspopup="menu">
        @isset($trigger){{ $trigger }}@else{{ $label }}<x-icon name="chevron-down" size="14" />@endisset
    </summary>
    <div class="menu-panel {{ $align === 'start' ? 'start-0 end-auto' : '' }}" role="menu">{{ $slot }}</div>
</details>
