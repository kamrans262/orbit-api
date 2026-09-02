<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Actions;

use App\Models\AdminUserControl;
use App\Models\EmailOtp;
use App\Models\ModerationEnforcement;
use App\Models\User;
use App\Modules\Admin\Moderation\Exceptions\ModerationDomainException;
use App\Modules\Auth\Exceptions\EmailOtpException;
use App\Modules\Auth\Support\EmailNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class VerifyAppealEmailOtpAction
{
    /**
     * @return array{access_token:string, token_type:string, expires_at:string}
     */
    public function handle(string $email, string $otpCode, string $enforcementId): array
    {
        $email = EmailNormalizer::normalize($email);
        $maxAttempts = (int) config('orbit.auth.email_otp.max_attempts', 5);
        $expiresAt = now()->addMinutes(30);

        /** @var array{access_token:string, token_type:string, expires_at:string}|EmailOtpException|ModerationDomainException $result */
        $result = DB::transaction(function () use ($email, $otpCode, $enforcementId, $maxAttempts, $expiresAt): array|EmailOtpException|ModerationDomainException {
            $user = User::query()->where('email', $email)->first();
            if ($user === null
                || ! $this->isCurrentlySuspended($user)
                || ! $this->hasAppealableEnforcement($user, $enforcementId)) {
                return new ModerationDomainException(
                    'APPEAL_AUTH_UNAVAILABLE',
                    'Appeal authentication is unavailable for this enforcement.',
                    404,
                );
            }

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
                    message: 'The OTP code is incorrect.',
                );
            }

            $emailOtp->forceFill(['used_at' => now()])->save();

            $newToken = $user->createToken(
                'moderation-appeal',
                ['appeals:submit'],
                $expiresAt,
            );

            return [
                'access_token' => $newToken->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        });

        if ($result instanceof EmailOtpException || $result instanceof ModerationDomainException) {
            throw $result;
        }

        return $result;
    }

    private function isCurrentlySuspended(User $user): bool
    {
        $control = AdminUserControl::query()->whereKey($user->getKey())->first();

        return $control !== null
            && $control->status === 'suspended'
            && ($control->suspended_until === null || $control->suspended_until->isFuture());
    }

    private function hasAppealableEnforcement(User $user, string $enforcementId): bool
    {
        return ModerationEnforcement::query()
            ->whereKey($enforcementId)
            ->where('target_type', 'user')
            ->where('target_id', (string) $user->getKey())
            ->whereIn('status', ['applied', 'modified'])
            ->exists();
    }
}
