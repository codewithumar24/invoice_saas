{{--
    Document styles shared by the PDF, the print view and the public page, so all three look the same.
    Written for dompdf: tables for layout, no flexbox or grid. Everything is scoped under .doc.
    $style holds the workspace's invoice customisation (template, colour, font).
--}}
@php
    $accent = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($style['color'] ?? '')) ? $style['color'] : '#0F6B4F';
    $font = in_array($style['font'] ?? '', ['DejaVu Sans', 'DejaVu Serif', 'Helvetica', 'Times', 'Courier'], true) ? $style['font'] : 'DejaVu Sans';
    $template = $style['template'] ?? 'classic';
    $compact = $template === 'compact';
@endphp
<style>
    .doc { font-family: '{{ $font }}', DejaVu Sans, sans-serif; font-size: {{ $compact ? '9.5px' : '11.5px' }}; line-height: 1.45; color: #1c2521; }
    .doc table { width: 100%; border-collapse: collapse; }
    .doc td, .doc th { vertical-align: top; text-align: left; }
    .doc .right { text-align: right; }
    .doc .muted { color: #5b6a63; }
    .doc .small { font-size: {{ $compact ? '8.5px' : '10px' }}; }
    .doc h1 { margin: 0; font-size: {{ $compact ? '18px' : '24px' }}; font-weight: bold; letter-spacing: 0.5px; color: {{ $accent }}; }
    .doc h2 { margin: 0 0 4px 0; font-size: {{ $compact ? '9px' : '10px' }}; font-weight: bold; color: #5b6a63; }
    .doc .logo { max-height: {{ $compact ? '44px' : '60px' }}; max-width: 220px; }
    .doc .business-name { font-size: {{ $compact ? '13px' : '15px' }}; font-weight: bold; }
    .doc .head { margin-bottom: {{ $compact ? '14px' : '24px' }}; }
    .doc .parties { margin-bottom: {{ $compact ? '12px' : '22px' }}; }
    .doc .meta td { padding: 1px 0 1px 12px; }
    .doc .items { margin-bottom: {{ $compact ? '10px' : '18px' }}; }
    .doc .items th { padding: {{ $compact ? '4px 6px' : '7px 8px' }}; font-size: {{ $compact ? '8.5px' : '10px' }}; font-weight: bold; border-bottom: 2px solid {{ $accent }}; color: #1c2521; }
    .doc .items td { padding: {{ $compact ? '4px 6px' : '8px' }}; border-bottom: 1px solid #dfe5e2; }
    .doc .items .desc { color: #5b6a63; font-size: {{ $compact ? '8.5px' : '10px' }}; }
    .doc .totals td { padding: {{ $compact ? '2px 6px' : '4px 8px' }}; }
    .doc .totals .grand td { font-size: {{ $compact ? '11px' : '13.5px' }}; font-weight: bold; border-top: 2px solid {{ $accent }}; padding-top: 7px; }
    .doc .totals .due td { font-weight: bold; color: {{ $accent }}; }
    .doc .box { border: 1px solid #dfe5e2; padding: {{ $compact ? '6px 8px' : '10px 12px' }}; margin-bottom: 10px; }
    .doc .status { display: inline-block; padding: 2px 8px; font-size: 10px; font-weight: bold; border: 1px solid {{ $accent }}; color: {{ $accent }}; }
    .doc .status-paid { border-color: #156b40; color: #156b40; }
    .doc .status-overdue, .doc .status-cancelled { border-color: #b42318; color: #b42318; }
    .doc .foot { margin-top: 18px; padding-top: 8px; border-top: 1px solid #dfe5e2; text-align: center; color: #5b6a63; font-size: 10px; }
    .doc .pre { white-space: pre-line; }

    @if ($template === 'modern')
        /* Modern: a solid colour band across the top */
        .doc .head { background: {{ $accent }}; color: #ffffff; padding: 18px 20px; }
        .doc .head h1, .doc .head .muted, .doc .head .business-name { color: #ffffff; }
        .doc .items th { background: #f2f5f3; border-bottom: 0; }
    @elseif ($template === 'minimal')
        /* Minimal: no colour, hairlines only */
        .doc h1 { color: #1c2521; font-weight: normal; }
        .doc .items th { border-bottom: 1px solid #1c2521; font-weight: normal; color: #5b6a63; }
        .doc .totals .grand td { border-top: 1px solid #1c2521; }
        .doc .totals .due td, .doc .status { color: #1c2521; border-color: #1c2521; }
    @elseif ($template === 'professional')
        /* Professional: solid table header and a boxed total */
        .doc .items th { background: {{ $accent }}; color: #ffffff; border-bottom: 0; }
        .doc .items td { border-bottom: 1px solid #c9d2cd; }
        .doc .totals { border: 1px solid #c9d2cd; }
        .doc .totals .grand td { background: #f2f5f3; }
        .doc .head { border-bottom: 3px solid {{ $accent }}; padding-bottom: 12px; }
    @endif
</style>
