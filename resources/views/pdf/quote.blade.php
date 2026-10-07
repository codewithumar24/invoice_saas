<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $quote->number }}</title>
    <style>
        @page { margin: 14mm 14mm 16mm 14mm; }
        body { margin: 0; }
    </style>
    @include('pdf._styles')
</head>
<body>
    @include('pdf._quote')
</body>
</html>
