<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Exceptions\EmailOtpDeliveryException;
use App\Modules\Auth\Mail\EmailOtpMail;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class EmailOtpDeliveryService
{
    public function deliver(string $email, string $otp, CarbonInterface $expiresAt): void
    {
        try {
            Mail::to($email)->send(new EmailOtpMail($otp, $expiresAt));
        } catch (Throwable $exception) {
            throw new EmailOtpDeliveryException(
                message: 'Unable to deliver the email OTP.',
                previous: $exception,
            );
        }

        // Development convenience only. In production the OTP is never written
        // to application logs; it is delivered through the configured mailer.
        if (app()->environment(['local', 'testing'])) {
            Log::info('Orbit email OTP (development only).', [
                'email' => $email,
                'otp' => $otp,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
        }
    }
}
