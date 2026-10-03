<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\User;
use App\Support\Vocative;

use function Illuminate\Support\defer;

/**
 * Kolejkuje mail z kodem logowania do centrali i od razu opróżnia outbox.
 *
 * JEDNA ścieżka wysyłki, jak dla każdego maila: wiersz w outboksie + ta sama
 * wysyłka, której używa cron (EmailOutbox). Różnica jest tylko w chwili
 * startu — nie czekamy na cron (do minuty), tylko wysyłamy tuż PO odpowiedzi.
 * Ekran z polem na kod pokazuje się od razu, a mail wychodzi sekundę później.
 * NIE przez `Artisan::call()` — powód w EmailOutbox. Priorytet High
 * stawia go na czele paczki, nawet gdy w kolejce zalega newsletter. Gdyby cron
 * akurat pracował, wysyłka poczeka na jego blokadę.
 *
 * Kod stoi w TEMACIE i obok słowa „kod" w treści — tak Safari rozpoznaje go
 * w aplikacji Mail i podpowiada nad klawiaturą, zanim otworzy się wiadomość.
 */
class LoginCodeMailer
{
    public function send(User $user, string $code): void
    {
        EmailMessage::create([
            'priority' => MailPriority::High,
            'to_email' => $user->email,
            'to_name' => trim($user->name.' '.$user->surname),
        ] + $this->content($user->name, $code));

        defer(fn () => app(EmailOutbox::class)->dispatch());
    }

    /**
     * Treść maila. Osobno, bo z tej samej metody korzysta podgląd szablonów
     * w panelu admina — inaczej podgląd i realny mail rozjechałyby się.
     *
     * @return array<string, mixed>
     */
    public function content(?string $name, string $code): array
    {
        $app = config('app.name');
        $minutes = (int) config('security.two_factor.ttl_minutes');
        $rememberDays = intdiv((int) config('auth.guards.web.remember'), 60 * 24);
        $when = 'Dziś o '.now()->format('H:i');

        return [
            'subject' => "Kod logowania do {$app}: {$code}",
            'preheader' => "Twój kod logowania: {$code}. Ważny {$minutes} minut.",
            // Nagłówek-powitanie jak w wiadomościach platformy i serwisowych.
            'heading' => Vocative::headline($name),
            'greeting' => null,
            'intro_lines' => [
                "{$when} ktoś zaczął logowanie do Twojego panelu w {$app} i podał poprawne hasło. Został ostatni krok — potwierdzenie, że to naprawdę Ty.",
                "**Twój kod logowania: {$code}**",
                "Wpisz go na ekranie logowania, w tym samym oknie przeglądarki, w którym podano hasło. Kod jest ważny przez {$minutes} minut i działa tylko raz. W Safari na iPhonie i Macu kod zwykle sam pojawia się nad klawiaturą — wystarczy go kliknąć.",
            ],
            'outro_lines' => [
                '**Dlaczego prosimy o kod?** Z panelu zarządzasz całym sklepem: zamówieniami, danymi klientów, płatnościami, wysyłkami i fakturami. Samo hasło może wyciec — z innego serwisu, w którym użyto tego samego hasła, albo przez fałszywą stronę logowania. Kod trafia wyłącznie do Twojej skrzynki, więc nawet ktoś, kto pozna hasło, nie wejdzie do panelu bez dostępu do Twojej poczty.',
                "Jeśli przy logowaniu zaznaczysz „Zapamiętaj mnie na {$rememberDays} dni”, na tym urządzeniu poprosimy o kod najpóźniej za {$rememberDays} dni.",
                // Kod dostaje tylko ktoś, kto zna hasło. Mail, o który się nie
                // prosiło, to więc sygnał, że hasło wyciekło — trzeba to nazwać.
                '**To nie Ty?** To znaczy, że ktoś zna Twoje hasło. Nie podawaj nikomu tego kodu — zespół '.$app.' nigdy o niego nie prosi — i jak najszybciej [ustaw nowe hasło]('.route('password.request').').',
            ],
        ];
    }
}
