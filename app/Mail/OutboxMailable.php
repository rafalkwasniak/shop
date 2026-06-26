<?php

namespace App\Mail;

use App\Models\EmailMessage;
use App\Support\MailBranding;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Renderuje zakolejkowany {@see EmailMessage} w naszym brandowalnym komponencie
 * (x-mail.message). Szatę kolorystyczną dobiera MailBranding po `shop_id` —
 * domyślnie system, docelowo per sklep.
 */
class OutboxMailable extends Mailable
{
    public function __construct(
        public readonly EmailMessage $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->message->subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.outbox',
            with: [
                'brand' => MailBranding::for($this->message->shop_id),
                'preheader' => $this->message->preheader,
                'heading' => $this->message->heading,
                'greeting' => $this->message->greeting,
                'lines' => $this->message->intro_lines ?? [],
                'actionText' => $this->message->action_text,
                'actionUrl' => $this->message->action_url,
                'outroLines' => $this->message->outro_lines ?? [],
            ],
        );
    }
}
