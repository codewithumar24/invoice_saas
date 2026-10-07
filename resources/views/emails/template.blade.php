{{-- One email layout for every templated email. Table-based and inline-styled for email clients. --}}
@php $color = $branding['color'] ?? '#0F6B4F'; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailSubject }}</title>
</head>
<body style="margin:0;padding:0;background:#f2f5f3;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#14211c;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f5f3;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #d9e0dc;border-radius:8px;">
                    <tr>
                        <td style="padding:24px 28px 0 28px;border-top:4px solid {{ $color }};border-radius:8px 8px 0 0;">
                            @if (! empty($branding['logo_url']))
                                <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['name'] ?? config('app.name') }}" height="36" style="height:36px;max-width:200px;">
                            @else
                                <p style="margin:0;font-size:16px;font-weight:600;">{{ $branding['name'] ?? config('app.name') }}</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px 8px 28px;font-size:15px;line-height:24px;">
                            {!! $bodyHtml !!}
                        </td>
                    </tr>
                    @if ($actionUrl)
                        <tr>
                            <td style="padding:8px 28px 24px 28px;">
                                <a href="{{ $actionUrl }}" style="display:inline-block;background:{{ $color }};color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:10px 18px;border-radius:6px;">{{ $actionLabel ?? __('Open') }}</a>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:16px 28px;border-top:1px solid #d9e0dc;font-size:12px;line-height:18px;color:#55655e;">
                            @if (! empty($branding['footer'])){{ $branding['footer'] }}<br>@endif
                            @if (empty($branding['white_label'])){{ __('Sent with :app', ['app' => config('app.name')]) }}@endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
