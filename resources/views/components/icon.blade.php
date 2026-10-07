@props(['name', 'size' => 18])
@php
    // Small stroke icon set (24x24 grid). Add a path here to add an icon.
    $paths = [
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'search' => 'M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm10 2-4.3-4.3',
        'plus' => 'M12 5v14M5 12h14',
        'bell' => 'M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8zm4 12a2 2 0 0 0 4 0',
        'x' => 'M6 6l12 12M18 6 6 18',
        'chevron-down' => 'm6 9 6 6 6-6',
        'check' => 'm5 12 5 5 9-10',
        'download' => 'M12 4v11m0 0-4-4m4 4 4-4M5 20h14',
        'print' => 'M7 9V4h10v5M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v6H7z',
        'send' => 'M21 3 10 14M21 3l-7 18-4-7-7-4z',
        'more' => 'M5 12h.01M12 12h.01M19 12h.01',
        'trash' => 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
        'edit' => 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4',
        'arrow-up' => 'M12 19V5m0 0-6 6m6-6 6 6',
        'arrow-down' => 'M12 5v14m0 0-6-6m6 6 6-6',
        'external' => 'M14 4h6v6M20 4 10 14M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5',
        'copy' => 'M9 9h10v11H9zM5 15V4h10',
        'clock' => 'M12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18zm0-13v4l3 2',
        'filter' => 'M4 5h16l-6 8v6l-4-2v-4z',
        'columns' => 'M4 5h16v14H4zM10 5v14M15 5v14',
        'alert' => 'M12 9v4m0 4h.01M10.3 4.3 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0z',
        'sun' => 'M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM12 2v2m0 16v2M4 12H2m20 0h-2M5 5l1.5 1.5M17.5 17.5 19 19M5 19l1.5-1.5M17.5 6.5 19 5',
        'moon' => 'M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z',
        'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm-8 9a8 8 0 0 1 16 0',
        'logout' => 'M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 16l-4-4 4-4M6 12h10',
        'play' => 'M7 5v14l12-7z',
        'pause' => 'M8 5v14M16 5v14',
        'stop' => 'M6 6h12v12H6z',
        'refresh' => 'M4 12a8 8 0 0 1 14-5l2 2m0-5v5h-5M20 12a8 8 0 0 1-14 5l-2-2m0 5v-5h5',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'shrink-0']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? '' }}" /></svg>
