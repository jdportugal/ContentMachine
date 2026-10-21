<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The free video is ready: a link to watch and download it. */
class VideoGratisPronto extends Mailable
{
    public function __construct(public string $nome, public string $link) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'O vídeo do seu imóvel está pronto');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.video-gratis-pronto');
    }
}
