@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'id' => null])
@php
    // "address[city]" -> "address.city" for old input and error lookups
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $id ?? 'f-'.str_replace('.', '-', $key);
    $current = $type === 'password' ? null : old($key, $value);
    if ($current instanceof \DateTimeInterface) {
        $current = $current->format($type === 'date' ? 'Y-m-d' : 'Y-m-d\TH:i');
    }
    $error = $errors->first($key);
@endphp
<div>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if ($attributes->has('required'))<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
    @endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $current }}"
        {{ $attributes->merge(['class' => 'input'.($error ? ' input-error' : '')]) }}
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif>
    @if ($error)
        <p class="field-error" id="{{ $id }}-error">{{ $error }}</p>
    @elseif ($hint)
        <p class="field-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
</div>
