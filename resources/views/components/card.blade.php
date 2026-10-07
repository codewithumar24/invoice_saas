@props(['title' => null, 'padded' => true])
<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <div class="card-head">
            <h2 class="h3">{{ $title }}</h2>
            @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
        </div>
    @endif
    <div @class(['card-pad' => $padded])>{{ $slot }}</div>
</section>
