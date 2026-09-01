<?php

declare(strict_types=1);

namespace App\Modules\Admin\Operations\Http\Middleware;

use App\Models\AdminCircleControl;
use App\Models\AdminDeviceControl;
use App\Models\AdminUserControl;
use App\Models\IdentitySession;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class EnforceConsumerOperationalControls
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/admin/*') || $request->is('api/v1/health')) {
            return $next($request);
        }

        if ($request->is('api/v1/auth/email-otp/verify')) {
            return $this->handleOtpVerification($request, $next);
        }

        $token = $this->consumerAccessToken($request);
        $user = $token?->tokenable;
        if (! $user instanceof User) {
            return $next($request);
        }

        $control = AdminUserControl::query()->whereKey($user->id)->first();
        if ($control !== null) {
            if ($this->isSuspended($control)) {
                return $this->error('This Orbit account is suspended.', 'ACCOUNT_SUSPENDED', 403);
            }
            if ($control->require_reverification) {
                return $this->error('Account re-verification is required.', 'REVERIFICATION_REQUIRED', 401);
            }
            if ($this->featureRestricted((array) ($control->feature_restrictions ?? []), $request, false)) {
                return $this->error('This account is restricted from using this feature.', 'FEATURE_RESTRICTED', 403);
            }
            if (! $request->is('api/v1/sos/*', 'api/v1/sos') && $control->rate_limit_per_minute !== null && ! $this->withinUserRateLimit($user, (int) $control->rate_limit_per_minute)) {
                return $this->error('This account has reached its administrative rate limit.', 'ADMIN_RATE_LIMITED', 429);
            }
        }

        $deviceEnforcement = $this->enforceDeviceControls($request, $user, $token);
        if ($deviceEnforcement !== null) {
            return $deviceEnforcement;
        }

        $circleId = $this->operationalCircleId($request);
        if ($circleId !== null) {
            $circleControl = AdminCircleControl::query()->whereKey($circleId)->first();
            if ($circleControl?->status === 'removed') {
                return $this->error('Circle not found.', 'CIRCLE_NOT_FOUND', 404);
            }
            if ($circleControl?->status === 'frozen' && ! in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true)) {
                return $this->error('This Circle is frozen by platform operations.', 'CIRCLE_FROZEN', 423);
            }
            if ($circleControl !== null && $this->featureRestricted((array) ($circleControl->feature_restrictions ?? []), $request, true)) {
                return $this->error('This Circle is restricted from using this feature.', 'CIRCLE_FEATURE_RESTRICTED', 403);
            }
        }

        return $next($request);
    }

    private function enforceDeviceControls(Request $request, User $user, ?PersonalAccessToken $token): ?Response
    {
        $identitySession = IdentitySession::query()
            ->where('access_token_id', $token?->getKey())
            ->where('status', 'active')
            ->first();

        if ($identitySession !== null) {
            $deviceControl = AdminDeviceControl::query()->whereKey($identitySession->device_id)->first();

            if ($deviceControl?->enforcement_revoked) {
                return $this->error('This device has been revoked by platform operations.', 'DEVICE_ADMIN_REVOKED', 403);
            }

            if ($deviceControl?->require_verification) {
                return $this->error('This device requires verification before it can continue.', 'DEVICE_VERIFICATION_REQUIRED', 401);
            }
        }

        if ($request->is('api/v1/devices') && strtoupper($request->method()) === 'POST') {
            $clientDeviceId = trim((string) $request->input('client_device_id', ''));

            if ($clientDeviceId !== '') {
                $deviceId = DB::table('devices')
                    ->where('user_id', $user->id)
                    ->where('client_device_id', $clientDeviceId)
                    ->value('id');

                if (is_string($deviceId) && AdminDeviceControl::query()->whereKey($deviceId)->where('enforcement_revoked', true)->exists()) {
                    return $this->error('This device has been revoked by platform operations.', 'DEVICE_ADMIN_REVOKED', 403);
                }
            }
        }

        if ($request->is('api/v1/identity/sessions') && strtoupper($request->method()) === 'POST') {
            $deviceId = trim((string) $request->input('device_id', ''));

            if ($deviceId !== '' && AdminDeviceControl::query()->whereKey($deviceId)->where('enforcement_revoked', true)->exists()) {
                return $this->error('This device has been revoked by platform operations.', 'DEVICE_ADMIN_REVOKED', 403);
            }
        }

        $approvalDeviceId = $request->route('deviceId');
        if (
            $request->is('api/v1/identity/devices/*/approve')
            && is_string($approvalDeviceId)
            && AdminDeviceControl::query()->whereKey($approvalDeviceId)->where('enforcement_revoked', true)->exists()
        ) {
            return $this->error('This device has been revoked by platform operations.', 'DEVICE_ADMIN_REVOKED', 403);
        }

        return null;
    }

    private function operationalCircleId(Request $request): ?string
    {
        $routeCircleId = $request->route('circleId');
        if (is_string($routeCircleId) && $routeCircleId !== '') {
            return $routeCircleId;
        }

        if ($request->is('api/v1/pings') && strtoupper($request->method()) === 'POST') {
            $payloadCircleId = trim((string) $request->input('circle_id', ''));

            return $payloadCircleId !== '' ? $payloadCircleId : null;
        }

        $momentId = $request->route('momentId');
        if (is_string($momentId) && $momentId !== '') {
            return $this->scalarString(DB::table('moments')->where('id', $momentId)->value('circle_id'));
        }

        $assetId = $request->route('assetId');
        if (is_string($assetId) && $assetId !== '') {
            return $this->scalarString(DB::table('media_assets')->where('id', $assetId)->value('circle_id'));
        }

        $uploadId = $request->route('uploadId');
        if (is_string($uploadId) && $uploadId !== '') {
            return $this->scalarString(DB::table('media_uploads')->where('id', $uploadId)->value('circle_id'));
        }

        $pingId = $request->route('pingId');
        if (is_string($pingId) && $pingId !== '') {
            return $this->scalarString(DB::table('pings')->where('id', $pingId)->value('circle_id'));
        }

        $envelopeId = $request->route('envelopeId');
        if (is_string($envelopeId) && $envelopeId !== '') {
            return $this->scalarString(
                DB::table('message_envelopes')
                    ->join('messages', 'messages.id', '=', 'message_envelopes.message_id')
                    ->where('message_envelopes.envelope_id', $envelopeId)
                    ->value('messages.circle_id'),
            );
        }

        return null;
    }

    private function scalarString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $string = (string) $value;

        return $string !== '' ? $string : null;
    }

    private function handleOtpVerification(Request $request, Closure $next): Response
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $user = $email !== '' ? User::query()->where('email', $email)->first() : null;
        $control = $user ? AdminUserControl::query()->whereKey($user->id)->first() : null;
        if ($control !== null && $this->isSuspended($control)) {
            return $this->error('This Orbit account is suspended.', 'ACCOUNT_SUSPENDED', 403);
        }

        $response = $next($request);
        if ($user !== null && $control?->require_reverification && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $control->forceFill(['require_reverification' => false])->save();
        }

        return $response;
    }

    private function consumerAccessToken(Request $request): ?PersonalAccessToken
    {
        $plain = $request->bearerToken();
        if (! is_string($plain) || $plain === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($plain);

        return $token?->tokenable instanceof User ? $token : null;
    }

    private function isSuspended(AdminUserControl $control): bool
    {
        return $control->status === 'suspended'
            && ($control->suspended_until === null || $control->suspended_until->isFuture());
    }

    /** @param list<string> $restrictions */
    private function featureRestricted(array $restrictions, Request $request, bool $circle): bool
    {
        $path = '/'.ltrim($request->path(), '/');
        $method = strtoupper($request->method());
        $checks = [
            'messaging' => str_contains($path, '/messages') || str_contains($path, '/message-devices') || str_contains($path, '/typing'),
            'moments' => str_contains($path, '/moments'),
            'ping' => str_contains($path, '/pings') || str_contains($path, '/ping'),
            'presence' => str_contains($path, '/presence'),
            'media' => str_contains($path, '/media'),
            'circle_mutations' => $this->isCircleManagementMutation($path, $method),
            'invites' => $circle && str_contains($path, '/invites'),
            'membership_mutations' => $circle && str_contains($path, '/members') && ! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true),
        ];

        foreach ($restrictions as $restriction) {
            if (($checks[$restriction] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    private function isCircleManagementMutation(string $path, string $method): bool
    {
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) || ! str_contains($path, '/circles')) {
            return false;
        }

        foreach (['/messages', '/typing', '/moments', '/media', '/presence', '/pings'] as $domainPath) {
            if (str_contains($path, $domainPath)) {
                return false;
            }
        }

        return true;
    }

    private function withinUserRateLimit(User $user, int $limit): bool
    {
        $limit = max(1, min($limit, (int) config('orbit_admin_operations.max_user_rate_limit_per_minute', 600)));
        $key = 'admin-user-rate:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return false;
        }
        RateLimiter::hit($key, 60);

        return true;
    }

    private function error(string $message, string $code, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message, 'code' => $code], $status);
    }
}
