@props(['tone' => 'info'])
<div {{ $attributes->merge(['class' => 'alert alert-'.$tone]) }} role="{{ $tone === 'danger' ? 'alert' : 'status' }}">
    <x-icon :name="$tone === 'ok' ? 'check' : 'alert'" class="mt-0.5" />
    <div class="min-w-0 flex-1">{{ $slot }}</div>
</div>
