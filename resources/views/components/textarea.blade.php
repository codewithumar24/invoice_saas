@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'rows' => 3, 'id' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $id ?? 'f-'.str_replace('.', '-', $key);
    $error = $errors->first($key);
@endphp
<div>
    @if ($label)
        <label for="{{ $id }}" class="label">{{ $label }}@if ($attributes->has('required'))<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" {{ $attributes->merge(['class' => 'input'.($error ? ' input-error' : '')]) }} @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>{{ old($key, $value) }}</textarea>
    @if ($error)
        <p class="field-error" id="{{ $id }}-error">{{ $error }}</p>
    @elseif ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif
</div>
