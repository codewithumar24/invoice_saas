@props(['action', 'method' => 'POST', 'confirm' => null, 'confirmLabel' => null])
{{-- A one-button form for row actions (send, cancel, delete ...). Dangerous actions pass "confirm". --}}
<form method="POST" action="{{ $action }}" class="contents" @if ($confirm) data-confirm="{{ $confirm }}" @endif @if ($confirmLabel) data-confirm-label="{{ $confirmLabel }}" @endif>
    @csrf
    @if (strtoupper($method) !== 'POST') @method($method) @endif
    <button type="submit" {{ $attributes->merge(['class' => 'btn btn-secondary btn-sm']) }}>{{ $slot }}</button>
</form>
