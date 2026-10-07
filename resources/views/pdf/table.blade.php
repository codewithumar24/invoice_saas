{{-- Generic table PDF for exports and reports. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1c2521; margin: 0; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        p { margin: 0 0 12px 0; color: #5b6a63; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 5px 6px; border-bottom: 2px solid #1c2521; font-size: 9px; }
        td { padding: 4px 6px; border-bottom: 1px solid #dfe5e2; }
        .num { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>{{ $tenant?->name }}, {{ now(tenant()?->timezone ?? 'UTC')->format('d M Y H:i') }}</p>
    <table>
        <thead><tr>@foreach ($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>@foreach ($row as $cell)<td class="{{ is_int($cell) || is_float($cell) ? 'num' : '' }}">{{ is_float($cell) ? number_format($cell, 2) : $cell }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
