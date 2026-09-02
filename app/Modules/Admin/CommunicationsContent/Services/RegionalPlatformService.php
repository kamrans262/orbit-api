<?php

declare(strict_types=1);

namespace App\Modules\Admin\CommunicationsContent\Services;

use App\Models\AppVersionPolicy;
use App\Models\MaintenanceWindow;
use App\Models\RegionalConfiguration;
use App\Models\User;
use App\Models\UserRegionalProfile;

final class RegionalPlatformService
{
    public function updateProfile(User $user, array $data): UserRegionalProfile
    {
        $profile = UserRegionalProfile::query()->firstOrNew(['user_id' => $user->getKey()]);

        if (array_key_exists('country_code', $data)) {
            $profile->country_code = $data['country_code'] !== null ? strtoupper((string) $data['country_code']) : null;
        }
        if (array_key_exists('platform', $data)) {
            $profile->platform = $data['platform'] !== null ? strtolower((string) $data['platform']) : null;
        }
        if (array_key_exists('app_version', $data)) {
            $profile->app_version = $data['app_version'];
        }
        if (array_key_exists('locale', $data)) {
            $profile->locale = $data['locale'];
        } elseif (! $profile->exists && ! $profile->locale) {
            $profile->locale = $user->locale ?? 'en';
        }

        $profile->save();

        return $profile->refresh();
    }

    public function publicConfig(?string $countryCode, ?string $platform, ?string $appVersion, string $environment): array
    {
        $country = $countryCode ? strtoupper($countryCode) : null;
        $region = $country ? RegionalConfiguration::query()->where('country_code', $country)->where('status', 'active')->first() : null;
        $policy = $platform ? AppVersionPolicy::query()->where('platform', strtolower($platform))->where('environment', $environment)->first() : null;
        $maintenance = $this->activeMaintenance($environment);

        return [
            'region' => $region ? [
                'country_code' => $region->country_code,
                'feature_availability' => $region->feature_availability ?? [],
                'subscription_availability' => $region->subscription_availability ?? [],
                'pricing' => $region->pricing ?? [],
                'legal_disclosures' => $region->legal_disclosures ?? [],
                'sms_available' => (bool) $region->sms_available,
                'emergency_information' => $region->emergency_information ?? [],
                'consent_requirements' => $region->consent_requirements ?? [],
            ] : null,
            'app_version' => $this->versionAssessment($policy, $appVersion),
            'maintenance' => $maintenance ? [
                'active' => true,
                'service' => $maintenance->service,
                'read_only' => (bool) $maintenance->read_only,
                'title' => $maintenance->title,
                'message' => $maintenance->message,
                'expected_restoration' => $maintenance->expected_restoration,
                'ends_at' => $maintenance->ends_at?->toIso8601String(),
                'sos_available' => true,
            ] : ['active' => false, 'sos_available' => true],
        ];
    }

    public function activeMaintenance(string $environment, string $service = 'global'): ?MaintenanceWindow
    {
        return MaintenanceWindow::query()
            ->where('environment', $environment)
            ->where('status', 'active')
            ->whereIn('service', ['global', $service])
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByRaw('CASE WHEN service = ? THEN 0 ELSE 1 END', [$service])
            ->first();
    }

    private function versionAssessment(?AppVersionPolicy $policy, ?string $version): array
    {
        if (! $policy || ! $version) {
            return ['status' => 'unknown'];
        }

        $status = 'supported';
        $message = null;
        if ($policy->minimum_supported_version && version_compare($version, $policy->minimum_supported_version, '<')) {
            $status = 'force_update';
            $message = $policy->forced_update_message;
        } elseif ($policy->recommended_version && version_compare($version, $policy->recommended_version, '<')) {
            $status = 'soft_update';
            $message = $policy->soft_update_message;
        }

        return [
            'status' => $status,
            'minimum_supported_version' => $policy->minimum_supported_version,
            'recommended_version' => $policy->recommended_version,
            'latest_version' => $policy->latest_version,
            'update_url' => $policy->update_url,
            'message' => $message,
        ];
    }
}
