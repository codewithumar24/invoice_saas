@props(['name', 'label', 'checked' => false, 'hint' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $isChecked = session()->hasOldInput() ? (bool) old($key) : (bool) $checked;
@endphp
<label class="switch">
    <input type="checkbox" name="{{ $name }}" value="1" role="switch" @checked($isChecked) {{ $attributes }}>
    <span class="switch-track" aria-hidden="true"></span>
    <span class="text-sm">
        <span class="font-medium text-ink">{{ $label }}</span>
        @if ($hint)<span class="block text-muted">{{ $hint }}</span>@endif
    </span>
</label>
