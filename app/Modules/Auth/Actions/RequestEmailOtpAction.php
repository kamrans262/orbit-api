<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Models\EmailOtp;
use App\Modules\Auth\Exceptions\EmailOtpDeliveryException;
use App\Modules\Auth\Services\EmailOtpDeliveryService;
use App\Modules\Auth\Services\EmailOtpGenerator;
use App\Modules\Auth\Support\EmailNormalizer;
use Illuminate\Support\Facades\Hash;

final class RequestEmailOtpAction
{
    public function __construct(
        private readonly EmailOtpGenerator $generator,
        private readonly EmailOtpDeliveryService $delivery,
    ) {}

    /**
     * @return array{email: string, expires_in_seconds: int}
     */
    public function handle(string $email): array
    {
        $email = EmailNormalizer::normalize($email);
        $ttlMinutes = (int) config('orbit.auth.email_otp.ttl_minutes', 10);
        $expiresAt = now()->addMinutes($ttlMinutes);

        EmailOtp::query()
            ->where('email', $email)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $otp = $this->generator->generate();

        $record = EmailOtp::query()->create([
            'email' => $email,
            'code_hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => $expiresAt,
        ]);

        try {
            $this->delivery->deliver($email, $otp, $expiresAt);
        } catch (EmailOtpDeliveryException $exception) {
            // Do not leave an active OTP behind when delivery failed.
            $record->forceFill(['used_at' => now()])->save();

            throw $exception;
        }

        return [
            'email' => $email,
            'expires_in_seconds' => $ttlMinutes * 60,
        ];
    }
}
