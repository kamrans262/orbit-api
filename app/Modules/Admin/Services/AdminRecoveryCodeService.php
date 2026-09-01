<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Models\AdminRecoveryCode;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

final class AdminRecoveryCodeService
{
    /** @return list<string> */
    public function regenerate(AdminUser $admin): array
    {
        return DB::transaction(function () use ($admin): array {
            AdminRecoveryCode::query()->where('admin_user_id', $admin->id)->delete();
            $count = max(5, min((int) config('orbit_admin.recovery_code_count', 10), 20));
            $codes = [];

            for ($index = 0; $index < $count; $index++) {
                $raw = strtoupper(bin2hex(random_bytes(8)));
                $code = implode('-', str_split($raw, 4));
                $codes[] = $code;
                AdminRecoveryCode::query()->create([
                    'admin_user_id' => $admin->id,
                    'code_hash' => $this->hash($code),
                ]);
            }

            return $codes;
        });
    }

    public function matches(AdminUser $admin, string $code): bool
    {
        return AdminRecoveryCode::query()
            ->where('admin_user_id', $admin->id)
            ->where('code_hash', $this->hash($code))
            ->whereNull('used_at')
            ->exists();
    }

    public function consume(AdminUser $admin, string $code): bool
    {
        $hash = $this->hash($code);
        $record = AdminRecoveryCode::query()
            ->where('admin_user_id', $admin->id)
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->first();

        if ($record === null) {
            return false;
        }

        return AdminRecoveryCode::query()
            ->whereKey($record->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]) === 1;
    }

    private function hash(string $code): string
    {
        $normalized = strtoupper(str_replace([' ', '-'], '', trim($code)));

        return hash_hmac('sha256', $normalized, (string) config('app.key', 'orbit'));
    }
}
