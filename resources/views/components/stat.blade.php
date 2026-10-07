@props(['label', 'value', 'change' => null, 'note' => null, 'href' => null, 'tone' => null, 'goodWhenUp' => true])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card card-pad block'.($href ? ' hover:border-brand/60' : '')]) }}>
    <p class="text-sm text-muted">{{ $label }}</p>
    <p @class(['num mt-1 text-2xl font-semibold tracking-tight', 'text-danger' => $tone === 'danger', 'text-ok' => $tone === 'ok'])>{{ $value }}</p>
    @if ($change !== null)
        @php $good = ($change >= 0) === $goodWhenUp; @endphp
        <p class="mt-1 flex items-center gap-1 text-xs {{ $good ? 'text-ok' : 'text-danger' }}">
            <x-icon :name="$change >= 0 ? 'arrow-up' : 'arrow-down'" size="12" />
            <span class="num">{{ abs($change) }}%</span>
            <span class="text-muted">{{ __('vs previous period') }}</span>
        </p>
    @elseif ($note)
        <p class="mt-1 text-xs text-muted">{{ $note }}</p>
    @endif
</{{ $tag }}>
