<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Support\MailMarkup;

/**
 * Wiadomość z formularza kontaktowego storefrontu → do sklepu.
 *
 * Idzie przez outbox jak każdy mail, więc treść zostaje w bazie także wtedy,
 * gdy poczta chwilowo nie działa — nic nie przepada.
 *
 * `Reply-To` = adres klienta: sprzedawca klika „Odpowiedz" i pisze prosto do
 * niego. Do nadawcy NIC nie wysyłamy — formularz, który wysyła maile na adres
 * wpisany w polu, byłby gotową maszynką do rozsyłania spamu w cudzym imieniu.
 */
class ContactFormMailer
{
    public function send(Shop $shop, string $name, string $email, string $message): void
    {
        $recipient = $shop->contactFormRecipient();

        if ($recipient === null) {
            return;
        }

        EmailMessage::create([
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'from_name' => $shop->name,
            'reply_to' => $email,
            'to_email' => $recipient,
            'to_name' => $shop->name,
            'subject' => 'Wiadomość ze sklepu: '.$name,
            'preheader' => 'Ktoś napisał przez formularz kontaktowy w sklepie '.$shop->name.'.',
            'heading' => 'Nowa wiadomość z formularza kontaktowego',
            'intro_lines' => [
                [
                    'Od: **'.MailMarkup::literal($name).'**',
                    'E-mail: '.MailMarkup::literal($email),
                ],
                array_merge(['**Wiadomość:**'], $this->lines($message)),
            ],
            'outro_lines' => [
                'Odpowiedz na tego maila — odpowiedź trafi prosto do nadawcy.',
            ],
        ]);
    }

    /**
     * Treść jako linie (komponent maila skleja je `<br>`), z neutralizacją
     * znaczników — tekst pisze osoba z zewnątrz.
     *
     * @return list<string>
     */
    private function lines(string $text): array
    {
        $normalized = (string) preg_replace(["/\r\n?/", "/\n{3,}/"], ["\n", "\n\n"], trim($text));

        return array_map(MailMarkup::literal(...), explode("\n", $normalized));
    }
}
