<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\ShopEmployee;
use App\Support\Vocative;
use Illuminate\Support\Facades\Password;

/**
 * Kolejkuje zaproszenie pracownika do sklepu: token brokera `invitation`
 * (ważny 7 dni) + link do ustawienia własnego hasła. Wysyłką zajmuje się cron,
 * tak jak przy każdym innym mailu z outboxu.
 *
 * Ten sam mechanizm co aktywacja sprzedawcy — świadomie, zamiast własnego
 * systemu tokenów. Konto pracownika powstaje z losowym hasłem (kolumna jest
 * NOT NULL), którego nie zna nikt, a broker jest jedyną drogą do ustawienia
 * prawdziwego.
 */
class EmployeeInvitationMailer
{
    public function send(ShopEmployee $employment): void
    {
        $user = $employment->user;
        $shop = $employment->shop;

        $token = Password::broker('invitation')->createToken($user);

        $url = route('employee.invitation.show', [
            'token' => $token,
            'email' => $user->email,
        ]);

        $sections = collect($employment->sections())
            ->map(fn ($section) => $section->label())
            ->implode(', ');

        EmailMessage::create([
            'priority' => MailPriority::High,
            // Branding SKLEPU, nie platformy: pracownik zna sprzedawcę, który go
            // zaprasza, a o Kramio mógł nie słyszeć. Mail bez tej nazwy wyglądałby
            // jak zaproszenie znikąd i wylądowałby w koszu.
            'shop_id' => $shop->getKey(),
            'to_email' => $user->email,
            'to_name' => trim($user->name.' '.$user->surname),
            'subject' => $shop->name.' — zaproszenie do panelu sklepu',
            'preheader' => 'Ustaw własne hasło i wejdź do panelu.',
            'heading' => 'Zaproszenie do panelu sklepu '.$shop->name,
            'greeting' => Vocative::greeting($user->name),
            'intro_lines' => array_values(array_filter([
                $shop->name.' zaprasza Cię do wspólnej pracy w panelu sklepu na platformie '.config('app.name').'.',
                $sections !== ''
                    ? 'Dostaniesz dostęp do działów: '.$sections.'. Reszty panelu nie zobaczysz.'
                    : null,
                'Ostatni krok należy do Ciebie: ustaw własne hasło. Nikt inny go nie pozna — także osoba, która Cię zaprosiła.',
            ])),
            'action_text' => 'Ustaw hasło i wejdź do panelu',
            'action_url' => $url,
            'outro_lines' => [
                'Link jest ważny przez 7 dni. Jeśli wygaśnie, poproś o ponowne zaproszenie.',
                'Jeśli nie spodziewasz się tego zaproszenia, po prostu zignoruj tę wiadomość — bez ustawienia hasła konto pozostaje nieczynne.',
            ],
        ]);
    }
}
