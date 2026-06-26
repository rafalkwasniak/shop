@props([
    'brand' => null,
    'preheader' => null,
    'heading' => null,
    'greeting' => null,
    'lines' => [],          // akapity przed przyciskiem
    'actionText' => null,   // tekst przycisku CTA (opcjonalny)
    'actionUrl' => null,
    'outroLines' => [],     // akapity po przycisku
])

@php
    $brand ??= \App\Support\MailBranding::system();
@endphp

<x-mail.layout :brand="$brand" :preheader="$preheader">
    @isset($heading)
        <h1 style="margin:0 0 16px 0; font-size:24px; line-height:1.25; font-weight:700; letter-spacing:-0.02em; color:{{ $brand['text'] }};">{{ $heading }}</h1>
    @endisset

    @isset($greeting)
        <p style="margin:0 0 16px 0; font-size:15px; line-height:1.65; color:{{ $brand['text'] }};">{{ $greeting }}</p>
    @endisset

    @foreach ($lines as $line)
        <p style="margin:0 0 16px 0; font-size:15px; line-height:1.65; color:{{ $brand['muted'] }};">{{ $line }}</p>
    @endforeach

    @if ($actionText && $actionUrl)
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;">
            <tr>
                <td style="border-radius:14px; background-color:{{ $brand['gradient_from'] }}; background-image:linear-gradient(135deg, {{ $brand['gradient_from'] }}, {{ $brand['gradient_to'] }});">
                    <a href="{{ $actionUrl }}" target="_blank" rel="noopener"
                       style="display:inline-block; padding:14px 28px; font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none;">{{ $actionText }}</a>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 8px 0; font-size:15px; line-height:1.65; color:{{ $brand['muted'] }};">
            Gdyby przycisk nie działał, skopiuj ten adres do przeglądarki:<br>
            <a href="{{ $actionUrl }}" style="color:{{ $brand['accent'] }}; word-break:break-all;">{{ $actionUrl }}</a>
        </p>
    @endif

    @foreach ($outroLines as $line)
        <p style="margin:16px 0 0 0; font-size:15px; line-height:1.65; color:{{ $brand['muted'] }};">{{ $line }}</p>
    @endforeach

    {{ $slot ?? '' }}
</x-mail.layout>
