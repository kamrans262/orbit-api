<?php

declare(strict_types=1);

namespace App\Modules\Auth\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class EmailOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $otp,
        public readonly CarbonInterface $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Orbit verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.email-otp',
            with: [
                'otp' => $this->otp,
                'expiresAt' => $this->expiresAt,
            ],
        );
    }
}
