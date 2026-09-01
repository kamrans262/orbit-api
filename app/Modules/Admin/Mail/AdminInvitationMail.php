<?php

declare(strict_types=1);

namespace App\Modules\Admin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class AdminInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $activationUrl,
        public readonly string $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Orbit administrator invitation',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-invitation',
            with: [
                'activationUrl' => $this->activationUrl,
                'expiresAt' => $this->expiresAt,
            ],
        );
    }
}
