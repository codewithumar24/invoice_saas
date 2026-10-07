@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'id' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $id ?? 'f-'.str_replace('.', '-', $key);
    $current = old($key, $value);
    $error = $errors->first($key);
@endphp
<div>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if ($attributes->has('required'))<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
    @endif
    <select name="{{ $name }}" id="{{ $id }}" {{ $attributes->merge(['class' => 'input'.($error ? ' input-error' : '')]) }} @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @if ($error)
        <p class="field-error" id="{{ $id }}-error">{{ $error }}</p>
    @elseif ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif
</div>
