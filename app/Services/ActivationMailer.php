<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Kolejkuje mail aktywacyjny dla świeżo zarejestrowanego konta: token brokera
 * `activation` (ważny 24 h) + link do ustawienia hasła, wrzucone do outboxu z
 * priorytetem High (wyprzedza newslettery). Wysyłką zajmuje się cron.
 */
class ActivationMailer
{
    public function send(User $user): void
    {
        $token = Password::broker('activation')->createToken($user);

        $url = route('activation.show', [
            'token' => $token,
            'email' => $user->email,
        ]);

        EmailMessage::create([
            'priority' => MailPriority::High,
            'to_email' => $user->email,
            'to_name' => trim($user->name.' '.$user->surname),
            'subject' => 'Aktywuj konto w '.config('app.name').' — ustaw hasło',
            'preheader' => 'Dokończ zakładanie konta — ustaw hasło.',
            'heading' => 'Witaj w '.config('app.name').'!',
            'greeting' => 'Cześć '.$user->name.',',
            'intro_lines' => [
                'Dziękujemy za rejestrację. Cieszymy się, że chcesz prowadzić swój sklep właśnie u nas.',
                'Aby dokończyć zakładanie konta, ustaw hasło i potwierdź swoje dane — zajmie to chwilę.',
            ],
            'action_text' => 'Dokończ rejestrację',
            'action_url' => $url,
            'outro_lines' => [
                'Link jest ważny przez 24 godziny. Jeśli to nie Ty zakładałeś konto, zignoruj tę wiadomość.',
            ],
        ]);
    }
}
