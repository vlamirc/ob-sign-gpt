<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SigningCode extends Mailable
{
    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Seu código de confirmação · Assina');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.signing-code');
    }
}
