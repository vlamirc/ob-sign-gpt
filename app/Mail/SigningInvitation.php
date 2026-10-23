<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SigningInvitation extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(public string $signerName, public string $documentTitle, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Convite para assinatura · Assina');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.signing-invitation');
    }
}
