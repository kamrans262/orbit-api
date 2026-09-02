<?php

declare(strict_types=1);

namespace App\Modules\Admin\BackendCompletion\Services;

use App\Models\AdminIpPolicy;

final class AdminIpPolicyService
{
    public function validCidr(string $cidr): bool
    {
        [$address, $prefix] = array_pad(explode('/', trim($cidr), 2), 2, null);
        $packed = @inet_pton($address);
        if ($packed === false) {
            return false;
        }

        $bits = strlen($packed) * 8;
        if ($prefix === null || $prefix === '') {
            return true;
        }

        return ctype_digit($prefix) && (int) $prefix >= 0 && (int) $prefix <= $bits;
    }

    public function allows(int $adminUserId, ?string $ip): bool
    {
        $policies = AdminIpPolicy::query()
            ->where('admin_user_id', $adminUserId)
            ->where('enabled', true)
            ->pluck('cidr')
            ->all();

        if ($policies === []) {
            return true;
        }

        if (! is_string($ip) || @inet_pton($ip) === false) {
            return false;
        }

        foreach ($policies as $cidr) {
            if ($this->contains((string) $cidr, $ip)) {
                return true;
            }
        }

        return false;
    }

    private function contains(string $cidr, string $ip): bool
    {
        [$network, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
        $networkPacked = @inet_pton($network);
        $ipPacked = @inet_pton($ip);
        if ($networkPacked === false || $ipPacked === false || strlen($networkPacked) !== strlen($ipPacked)) {
            return false;
        }

        $bits = strlen($networkPacked) * 8;
        $prefixLength = $prefix === null || $prefix === '' ? $bits : (int) $prefix;
        if ($prefixLength < 0 || $prefixLength > $bits) {
            return false;
        }

        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if ($fullBytes > 0 && substr($networkPacked, 0, $fullBytes) !== substr($ipPacked, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($networkPacked[$fullBytes]) & $mask) === (ord($ipPacked[$fullBytes]) & $mask);
    }
}
