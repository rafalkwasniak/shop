@props([
    'brand' => null,
    'preheader' => null,
])

@php
    $brand ??= \App\Support\MailBranding::system();
@endphp

<!DOCTYPE html>
<html lang="pl" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $brand['name'] }}</title>
</head>
<body style="margin:0; padding:0; background-color:{{ $brand['page_bg'] }}; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    @isset($preheader)
        <span style="display:none !important; visibility:hidden; opacity:0; color:transparent; height:0; width:0; overflow:hidden; mso-hide:all;">{{ $preheader }}</span>
    @endisset

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $brand['page_bg'] }};">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="700" cellpadding="0" cellspacing="0" border="0" style="width:700px; max-width:700px;">

                    {{-- Nagłówek z brandingiem --}}
                    <tr>
                        <td align="center" style="padding:8px 0 24px 0;">
                            @if (!empty($brand['logo_url']))
                                <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['name'] }}" height="40" style="display:block; border:0; height:40px;">
                            @else
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td style="padding-right:10px;">
                                            <div style="width:40px; height:40px; border-radius:9999px; background-color:{{ $brand['gradient_from'] }}; background-image:linear-gradient(135deg, {{ $brand['gradient_from'] }}, {{ $brand['gradient_to'] }}); color:#ffffff; font-size:20px; line-height:40px; text-align:center;">{{ $brand['glyph'] }}</div>
                                        </td>
                                        <td style="font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:20px; font-weight:700; letter-spacing:-0.02em; color:{{ $brand['text'] }};">{{ $brand['name'] }}</td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    {{-- Karta z treścią („wsad”) --}}
                    <tr>
                        <td style="background-color:#ffffff; border-radius:20px; border:1px solid #f0eeec; padding:36px 36px 32px 36px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Stopka --}}
                    <tr>
                        <td align="center" style="padding:24px 16px 8px 16px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:12px; line-height:18px; color:{{ $brand['muted'] }};">
                            {{ $brand['name'] }} · ta wiadomość została wysłana automatycznie<br>
                            Jeśli nie spodziewałeś się tej wiadomości, możesz ją zignorować.
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>
</body>
</html>
