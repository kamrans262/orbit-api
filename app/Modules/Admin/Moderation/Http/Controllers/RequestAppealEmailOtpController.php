<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Http\Controllers;

use App\Models\AdminUserControl;
use App\Models\ModerationEnforcement;
use App\Models\User;
use App\Modules\Auth\Actions\RequestEmailOtpAction;
use App\Modules\Auth\Exceptions\EmailOtpDeliveryException;
use App\Modules\Auth\Support\EmailNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RequestAppealEmailOtpController
{
    public function __invoke(Request $request, RequestEmailOtpAction $action): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'enforcement_id' => ['required', 'uuid'],
        ]);

        $email = EmailNormalizer::normalize((string) $data['email']);
        $user = User::query()->where('email', $email)->first();

        $eligible = $user !== null
            && $this->isCurrentlySuspended($user)
            && ModerationEnforcement::query()
                ->whereKey((string) $data['enforcement_id'])
                ->where('target_type', 'user')
                ->where('target_id', (string) $user->getKey())
                ->whereIn('status', ['applied', 'modified'])
                ->exists();

        if ($eligible) {
            try {
                $action->handle($email);
            } catch (EmailOtpDeliveryException $exception) {
                report($exception);

                return response()->json([
                    'success' => false,
                    'message' => 'OTP delivery is currently unavailable.',
                    'code' => 'OTP_DELIVERY_UNAVAILABLE',
                ], 503);
            }
        }

        // Deliberately return the same response for eligible and ineligible
        // identifiers so this public endpoint cannot enumerate accounts or
        // enforcement records.
        return response()->json([
            'success' => true,
            'message' => 'If this appeal is eligible, a verification code has been sent.',
        ], 202);
    }

    private function isCurrentlySuspended(User $user): bool
    {
        $control = AdminUserControl::query()->whereKey($user->getKey())->first();

        return $control !== null
            && $control->status === 'suspended'
            && ($control->suspended_until === null || $control->suspended_until->isFuture());
    }
}
