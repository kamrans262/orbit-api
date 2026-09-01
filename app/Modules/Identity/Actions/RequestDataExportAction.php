<?php

declare(strict_types=1);

namespace App\Modules\Identity\Actions;

use App\Models\DataExportRequest;
use App\Models\User;
use App\Modules\Identity\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RequestDataExportAction
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(User $user, ?Request $request = null): DataExportRequest
    {
        $existing = DataExportRequest::query()
            ->where('user_id', $user->getKey())
            ->where('status', 'ready')
            ->where('expires_at', '>', now())
            ->latest('requested_at')
            ->first();

        if ($existing) {
            return $existing;
        }

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'profile' => $this->safeProfile($user),
            'devices' => $this->tableRows('devices', (int) $user->getKey(), ['id', 'client_device_id', 'platform', 'device_name', 'last_seen_at', 'created_at']),
            'circle_memberships' => $this->tableRows('circle_members', (int) $user->getKey(), ['circle_id', 'role', 'joined_at', 'created_at']),
            'identity_sessions' => $this->tableRows('identity_sessions', (int) $user->getKey(), ['id', 'device_id', 'status', 'last_seen_at', 'created_at', 'revoked_at']),
            'privacy_note' => 'Orbit private message and media plaintext is not present in the server export because the server stores only encrypted private content/routing metadata.',
        ];

        $export = DataExportRequest::query()->create([
            'user_id' => $user->getKey(),
            'status' => 'ready',
            'payload' => $payload,
            'requested_at' => now(),
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        $this->audit->write(
            'identity.data_export.created',
            (int) $user->getKey(),
            targetType: 'data_export',
            targetId: $export->id,
            request: $request,
        );

        return $export;
    }

    private function safeProfile(User $user): array
    {
        return collect($user->getAttributes())
            ->only(['id', 'name', 'email', 'created_at', 'updated_at', 'global_ghost_mode'])
            ->all();
    }

    private function tableRows(string $table, int $userId, array $preferredColumns): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
            return [];
        }

        $columns = array_values(array_intersect($preferredColumns, Schema::getColumnListing($table)));

        return DB::table($table)
            ->where('user_id', $userId)
            ->get($columns === [] ? ['user_id'] : $columns)
            ->map(fn (object $row): array => (array) $row)
            ->values()
            ->all();
    }
}
