<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The one email layout: a title, a line of text, a coral button and a small note (sign-in links, "it's ready"). */
class EmailImocena extends Mailable
{
    use Queueable;

    public function __construct(
        public string $assunto,
        public string $titulo,
        public string $texto,
        public string $botao,
        public string $url,
        public string $nota = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->assunto);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.imocena');
    }
}
