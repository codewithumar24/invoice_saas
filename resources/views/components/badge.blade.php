@props(['status' => null, 'tone' => null])
@php
    // One mapping from business status to colour, used by every module
    $tone = $tone ?? match ($status) {
        'paid', 'active', 'accepted', 'completed', 'success', 'resolved', 'converted', 'done' => 'ok',
        'overdue', 'failed', 'rejected', 'cancelled', 'suspended', 'expired', 'past_due', 'refunded' => 'danger',
        'partially_paid', 'pending', 'processing', 'paused', 'on_hold', 'partially_refunded', 'trial', 'in_progress', 'uploaded' => 'warn',
        'sent', 'viewed', 'open', 'planned', 'running' => 'info',
        default => 'neutral',
    };
@endphp
<span {{ $attributes->merge(['class' => 'badge badge-'.$tone]) }}>{{ $slot->isEmpty() ? status_label($status) : $slot }}</span>
