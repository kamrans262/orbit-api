<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Models\EmailOtp;
use App\Models\User;
use App\Modules\Auth\Exceptions\EmailOtpException;
use App\Modules\Auth\Support\EmailNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class VerifyEmailOtpAction
{
    /**
     * @return array{user: User, token: string, token_type: string, expires_at: string}
     */
    public function handle(string $email, string $otpCode, string $deviceName): array
    {
        $email = EmailNormalizer::normalize($email);
        $maxAttempts = (int) config('orbit.auth.email_otp.max_attempts', 5);
        $tokenDays = (int) config('orbit.auth.token_expiration_days', 30);

        /** @var array{user: User, token: string, token_type: string, expires_at: string}|EmailOtpException $result */
        $result = DB::transaction(function () use ($email, $otpCode, $deviceName, $maxAttempts, $tokenDays): array|EmailOtpException {
            /** @var EmailOtp|null $emailOtp */
            $emailOtp = EmailOtp::query()
                ->where('email', $email)
                ->whereNull('used_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($emailOtp === null) {
                return new EmailOtpException(
                    apiCode: 'OTP_NOT_FOUND',
                    message: 'No active OTP was found for this email address.',
                );
            }

            if ($emailOtp->expires_at->isPast()) {
                $emailOtp->forceFill(['used_at' => now()])->save();

                return new EmailOtpException(
                    apiCode: 'OTP_EXPIRED',
                    message: 'The OTP has expired. Request a new OTP and try again.',
                );
            }

            if ($emailOtp->attempts >= $maxAttempts) {
                $emailOtp->forceFill(['used_at' => now()])->save();

                return new EmailOtpException(
                    apiCode: 'OTP_ATTEMPTS_EXCEEDED',
                    message: 'Too many incorrect OTP attempts. Request a new OTP.',
                    status: 429,
                );
            }

            if (! Hash::check($otpCode, $emailOtp->code_hash)) {
                $emailOtp->attempts++;

                if ($emailOtp->attempts >= $maxAttempts) {
                    $emailOtp->used_at = now();
                    $emailOtp->save();

                    return new EmailOtpException(
                        apiCode: 'OTP_ATTEMPTS_EXCEEDED',
                        message: 'Too many incorrect OTP attempts. Request a new OTP.',
                        status: 429,
                    );
                }

                $emailOtp->save();

                return new EmailOtpException(
                    apiCode: 'INVALID_OTP',
                    message: 'The OTP is incorrect.',
                );
            }

            $emailOtp->forceFill(['used_at' => now()])->save();

            /** @var User $user */
            $user = User::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => null,
                    'email_verified_at' => now(),
                    'password' => null,
                ],
            );

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $expiresAt = now()->addDays($tokenDays);
            $token = $user->createToken($deviceName, ['*'], $expiresAt);

            return [
                'user' => $user->fresh(),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        });

        if ($result instanceof EmailOtpException) {
            throw $result;
        }

        return $result;
    }
}
