@props(['name', 'label', 'checked' => false, 'value' => '1', 'hint' => null, 'id' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $id ?? 'f-'.str_replace('.', '-', $key).'-'.\Illuminate\Support\Str::slug((string) $value);
    // After a failed submit the old input decides; before that the given state does
    $isChecked = session()->hasOldInput() ? (is_array(old($key)) ? in_array($value, old($key)) : (bool) old($key)) : (bool) $checked;
@endphp
<div class="flex items-start gap-3">
    <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" @checked($isChecked) {{ $attributes->merge(['class' => 'check mt-1']) }}>
    <label for="{{ $id }}" class="text-sm">
        <span class="font-medium text-ink">{{ $label }}</span>
        @if ($hint)<span class="block text-muted">{{ $hint }}</span>@endif
    </label>
</div>
