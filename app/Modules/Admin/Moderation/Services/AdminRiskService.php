<?php

declare(strict_types=1);

namespace App\Modules\Admin\Moderation\Services;

use App\Models\AdminRiskProfile;
use App\Models\AdminRiskSignal;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\User;
use App\Modules\Admin\Services\AdminAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final readonly class AdminRiskService
{
    public function __construct(private AdminAuditLogger $audit) {}

    /** @param array<string,mixed> $metadata */
    public function record(int $userId, string $type, string $severity, string $source, ?string $sourceId, array $metadata = []): AdminRiskSignal
    {
        $existing = $sourceId !== null
            ? AdminRiskSignal::query()->where('user_id', $userId)->where('type', $type)->where('source', $source)->where('source_id', $sourceId)->first()
            : null;
        if ($existing) {
            return $existing;
        }

        $signal = AdminRiskSignal::query()->create([
            'user_id' => $userId, 'type' => $type, 'severity' => $severity, 'source' => $source,
            'source_id' => $sourceId, 'metadata' => $this->sanitize($metadata), 'occurred_at' => now(),
        ]);
        $this->recompute($userId);

        return $signal;
    }

    public function manual(User $user, AdminUser $admin, AdminSession $session, string $type, string $severity, array $metadata, string $reason, Request $request): AdminRiskSignal
    {
        return DB::transaction(function () use ($user, $admin, $session, $type, $severity, $metadata, $reason, $request) {
            $signal = $this->record((int) $user->id, $type, $severity, 'admin', null, $metadata);
            $this->audit->write(
                'admin.risk.signal.created', $admin, $session, 'risk_signal', $signal->id,
                reason: $reason, after: ['user_id' => (int) $user->id, 'type' => $type, 'severity' => $severity], request: $request
            );

            return $signal;
        });
    }

    public function resolve(AdminRiskSignal $signal, AdminUser $admin, AdminSession $session, string $reason, Request $request): AdminRiskSignal
    {
        if ($signal->resolved_at !== null) {
            return $signal;
        }
        $signal->forceFill([
            'resolved_at' => now(), 'resolved_by_admin_id' => $admin->id, 'resolution_note' => $reason,
        ])->save();
        $profile = $this->recompute((int) $signal->user_id);
        $this->audit->write(
            'admin.risk.signal.resolved', $admin, $session, 'risk_signal', $signal->id,
            reason: $reason, after: ['profile_score' => $profile->score, 'profile_level' => $profile->level], request: $request
        );

        return $signal->refresh();
    }

    public function recompute(int $userId): AdminRiskProfile
    {
        $signals = AdminRiskSignal::query()->where('user_id', $userId)->whereNull('resolved_at')->get();
        $weights = ['low' => 10, 'medium' => 25, 'high' => 45, 'critical' => 70];
        $score = min(100, $signals->sum(fn (AdminRiskSignal $s): int => $weights[$s->severity] ?? 10));
        $level = match (true) {
            $score >= 80 => 'critical',
            $score >= 60 => 'high',
            $score >= 40 => 'elevated',
            $score >= 20 => 'watch',
            default => 'normal',
        };

        return AdminRiskProfile::query()->updateOrCreate(
            ['user_id' => $userId],
            ['score' => $score, 'level' => $level, 'triggered_rules' => $signals->pluck('type')->unique()->values()->all(), 'last_evaluated_at' => now()],
        );
    }

    private function sanitize(array $value): array
    {
        $blockedFragments = [
            'token',
            'authorization',
            'password',
            'secret',
            'otp',
            'recovery_code',
            'ciphertext',
            'plaintext',
            'encrypted_key',
            'private_key',
            'recording_ref',
            'latitude',
            'longitude',
        ];

        $out = [];

        foreach ($value as $key => $item) {
            $normalized = strtolower((string) $key);
            $blocked = false;

            foreach ($blockedFragments as $fragment) {
                if (str_contains($normalized, $fragment)) {
                    $blocked = true;
                    break;
                }
            }

            if ($blocked) {
                continue;
            }

            $out[$key] = is_array($item) ? $this->sanitize($item) : $item;
        }

        return $out;
    }
}
