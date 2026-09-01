<?php

declare(strict_types=1);

use App\Models\AdminAuditLog;
use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminSosExport;
use App\Models\AdminSosIncidentControl;
use App\Models\AdminSosSensitiveAccess;
use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\NotificationDelivery;
use App\Models\OrbitNotification;
use App\Models\SosEscalation;
use App\Models\SosEvent;
use App\Models\SosNotificationOutbox;
use App\Models\SosResponder;
use App\Models\User;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\Safety\Events\AdminSosIncidentUpdated;
use App\Modules\Admin\Safety\Listeners\BroadcastAdminSosLifecycleUpdate;
use App\Modules\Admin\Safety\Services\AdminSosRealtimeService;
use App\Modules\Admin\Services\AdminRbacService;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use App\Modules\Sos\Events\SosLocationUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createAdminSafetyAdministrator(string $role = 'senior-safety-operator'): AdminUser
{
    app(AdminRbacService::class)->syncDefaults();

    $admin = AdminUser::query()->create([
        'name' => 'Safety Administrator',
        'email' => Str::uuid().'@safety.orbit.test',
        'password' => 'StrongPassword!123',
        'status' => AdminStatus::Active,
        'mfa_confirmed_at' => now(),
        'activated_at' => now(),
    ]);

    $roleModel = AdminRole::query()->where('slug', $role)->firstOrFail();
    $admin->roles()->sync([$roleModel->id]);

    return $admin;
}

function adminSafetyHeaders(AdminUser $admin, bool $recentReauth = true): array
{
    app('auth')->forgetGuards();

    $token = $admin->createToken('admin-safety-test', ['admin'], now()->addHours(2));

    AdminSession::query()->create([
        'id' => (string) Str::uuid7(),
        'admin_user_id' => $admin->id,
        'access_token_id' => $token->accessToken->id,
        'last_seen_at' => now(),
        'idle_expires_at' => now()->addHour(),
        'expires_at' => now()->addHours(2),
        'reauthenticated_at' => $recentReauth ? now() : now()->subHour(),
        'mfa_verified_at' => now(),
    ]);

    return ['Authorization' => 'Bearer '.$token->plainTextToken];
}

function createAdminSafetyIncident(array $attributes = []): array
{
    $owner = User::factory()->create(['name' => 'SOS Owner']);
    $member = User::factory()->create(['name' => 'SOS Responder']);

    $circle = Circle::query()->create([
        'created_by' => $owner->id,
        'name' => 'Safety Command Circle',
        'type' => 'standard',
    ]);

    CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $owner->id,
        'role' => CircleRole::Owner,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]);

    CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $member->id,
        'role' => CircleRole::Member,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]);

    $incident = SosEvent::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'user_id' => $owner->id,
        'circle_id' => $circle->id,
        'status' => 'active',
        'escalation_stage' => 0,
        'activated_at' => now()->subMinute(),
    ], $attributes));

    SosResponder::query()->create([
        'sos_event_id' => $incident->id,
        'user_id' => $member->id,
        'status' => 'pending',
    ]);

    return [$incident, $owner, $member, $circle];
}

test('administrator SOS APIs require administrator authentication', function (): void {
    $this->getJson('/api/admin/v1/sos')->assertUnauthorized();
});

test('read only administrators can view SOS incidents but cannot change command center state', function (): void {
    [$incident] = createAdminSafetyIncident();
    $admin = createAdminSafetyAdministrator('read-only');
    $headers = adminSafetyHeaders($admin);

    $this->withHeaders($headers)->getJson('/api/admin/v1/sos')->assertOk();
    $this->withHeaders($headers)->getJson('/api/admin/v1/sos/'.$incident->id)->assertOk();

    $this->withHeaders($headers)->putJson('/api/admin/v1/sos/'.$incident->id.'/classification', [
        'false_alarm' => true,
        'reason' => 'Attempted read only classification',
    ])->assertForbidden();
});

test('SOS directory supports active history search escalation fallback and pagination filters', function (): void {
    [$active] = createAdminSafetyIncident(['escalation_stage' => 2]);
    [$resolved] = createAdminSafetyIncident([
        'status' => 'resolved',
        'resolved_at' => now(),
        'escalation_stage' => 0,
    ]);

    SosEscalation::query()->create([
        'sos_event_id' => $active->id,
        'stage' => 2,
        'action' => 'sms_fallback',
        'status' => 'pending_provider',
        'occurred_at' => now(),
    ]);

    $admin = createAdminSafetyAdministrator('safety-operator');

    $this->withHeaders(adminSafetyHeaders($admin))
        ->getJson('/api/admin/v1/sos?status=active&escalation_min=2&fallback_used=1&per_page=10')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $active->id)
        ->assertJsonPath('data.pagination.total', 1);

    $this->withHeaders(adminSafetyHeaders($admin))
        ->getJson('/api/admin/v1/sos?status=resolved')
        ->assertOk()
        ->assertJsonPath('data.items.0.id', $resolved->id);
});

test('normal SOS incident detail is E2EE safe and never exposes precise location or recording reference', function (): void {
    [$incident] = createAdminSafetyIncident([
        'last_latitude' => 31.5204,
        'last_longitude' => 74.3587,
        'last_location_accuracy_m' => 6.5,
        'last_location_at' => now(),
        'recording_ref' => 'media:sos:OPAQUE-ENCRYPTED-REFERENCE',
        'recording_expires_at' => now()->addDays(90),
    ]);

    $admin = createAdminSafetyAdministrator('safety-operator');

    $response = $this->withHeaders(adminSafetyHeaders($admin))
        ->getJson('/api/admin/v1/sos/'.$incident->id)
        ->assertOk()
        ->assertJsonPath('data.location_update_health.status', 'healthy')
        ->assertJsonPath('data.recording_upload_health.status', 'encrypted_reference_present')
        ->assertJsonPath('data.network_health.status', 'unknown');

    $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
    expect($encoded)
        ->not->toContain('31.5204')
        ->not->toContain('74.3587')
        ->not->toContain('OPAQUE-ENCRYPTED-REFERENCE');
});

test('command center detail exposes responder escalation push fallback and masked provider delivery metadata', function (): void {
    [$incident, , $member] = createAdminSafetyIncident();

    SosResponder::query()
        ->where('sos_event_id', $incident->id)
        ->where('user_id', $member->id)
        ->update(['status' => 'engaged', 'engaged_at' => now(), 'responded_at' => now(), 'last_location_at' => now()]);

    SosEscalation::query()->create([
        'sos_event_id' => $incident->id,
        'stage' => 1,
        'action' => 'notify_secondary_responders',
        'status' => 'queued',
        'occurred_at' => now(),
    ]);

    $outbox = SosNotificationOutbox::query()->create([
        'sos_event_id' => $incident->id,
        'target_user_id' => $member->id,
        'channel' => 'push',
        'kind' => 'sos.activated',
        'priority' => 'highest',
        'payload' => ['sos_id' => $incident->id, 'circle_id' => $incident->circle_id],
        'status' => 'accepted',
        'available_at' => now(),
        'delivered_at' => now(),
        'attempts' => 1,
    ]);

    $notification = OrbitNotification::query()->create([
        'user_id' => $member->id,
        'circle_id' => $incident->circle_id,
        'kind' => 'sos.activated',
        'priority' => 'highest',
        'idempotency_key' => 'sos-outbox:'.$outbox->id,
        'summary' => 'SOS alert',
        'payload' => ['sos_id' => $incident->id],
        'in_app_visible' => true,
    ]);

    NotificationDelivery::query()->create([
        'notification_id' => $notification->id,
        'target_user_id' => $member->id,
        'device_id' => '01HXSUPERSECRETDEVICE12345',
        'channel' => 'push',
        'provider' => 'apns',
        'priority' => 'highest',
        'silent' => false,
        'payload' => ['sos_id' => $incident->id],
        'status' => 'pending_provider',
        'available_at' => now(),
        'attempts' => 0,
    ]);

    $admin = createAdminSafetyAdministrator('safety-operator');

    $response = $this->withHeaders(adminSafetyHeaders($admin))
        ->getJson('/api/admin/v1/sos/'.$incident->id)
        ->assertOk()
        ->assertJsonPath('data.responders.0.status', 'engaged')
        ->assertJsonPath('data.escalations.0.stage', 1)
        ->assertJsonPath('data.notification_pipeline.outbox.0.status', 'accepted')
        ->assertJsonPath('data.notification_pipeline.provider_deliveries.0.provider', 'apns');

    expect((string) $response->json('data.notification_pipeline.provider_deliveries.0.device_id_masked'))
        ->toContain('••••')
        ->not->toBe('01HXSUPERSECRETDEVICE12345');
});

test('safety operators can assign incidents only to active administrators with SOS management permission', function (): void {
    Event::fake([AdminSosIncidentUpdated::class]);

    [$incident] = createAdminSafetyIncident();
    $actor = createAdminSafetyAdministrator('safety-operator');
    $assignee = createAdminSafetyAdministrator('safety-operator');
    $headers = adminSafetyHeaders($actor);

    $this->withHeaders($headers)
        ->patchJson('/api/admin/v1/sos/'.$incident->id.'/assignment', [
            'assigned_admin_id' => $assignee->id,
            'reason' => 'Assign active safety operator',
        ])
        ->assertOk()
        ->assertJsonPath('data.assigned_admin_id', $assignee->id);

    expect(AdminSosIncidentControl::query()->findOrFail($incident->id)->assigned_admin_id)->toBe($assignee->id);
    expect(AdminAuditLog::query()->where('action', 'admin.sos.assignment.updated')->exists())->toBeTrue();
    Event::assertDispatched(AdminSosIncidentUpdated::class);
});

test('SOS assignment rejects administrators without SOS management permission', function (): void {
    [$incident] = createAdminSafetyIncident();
    $actor = createAdminSafetyAdministrator('safety-operator');
    $readOnly = createAdminSafetyAdministrator('read-only');

    $this->withHeaders(adminSafetyHeaders($actor))
        ->patchJson('/api/admin/v1/sos/'.$incident->id.'/assignment', [
            'assigned_admin_id' => $readOnly->id,
            'reason' => 'Invalid assignment target',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'ADMIN_SOS_INVALID_ASSIGNEE');
});

test('SOS command center classification tracks false alarm technical failure abuse and internal escalation', function (): void {
    Event::fake([AdminSosIncidentUpdated::class]);

    [$incident] = createAdminSafetyIncident();
    $admin = createAdminSafetyAdministrator('safety-operator');

    $this->withHeaders(adminSafetyHeaders($admin))
        ->putJson('/api/admin/v1/sos/'.$incident->id.'/classification', [
            'operational_status' => 'escalated',
            'internal_escalation_level' => 'critical',
            'false_alarm' => true,
            'technical_failure' => true,
            'abuse_flag' => true,
            'reason' => 'Post incident safety classification',
        ])
        ->assertOk()
        ->assertJsonPath('data.operational_status', 'escalated')
        ->assertJsonPath('data.internal_escalation_level', 'critical')
        ->assertJsonPath('data.false_alarm', true)
        ->assertJsonPath('data.technical_failure', true)
        ->assertJsonPath('data.abuse_flag', true);

    Event::assertDispatched(AdminSosIncidentUpdated::class);
});

test('operational closure requires a resolution and does not silently resolve the consumer SOS event', function (): void {
    [$incident] = createAdminSafetyIncident();
    $admin = createAdminSafetyAdministrator('safety-operator');
    $headers = adminSafetyHeaders($admin);

    $this->withHeaders($headers)
        ->putJson('/api/admin/v1/sos/'.$incident->id.'/classification', [
            'operational_status' => 'closed',
            'reason' => 'Attempt close without resolution',
        ])
        ->assertStatus(409);

    $this->withHeaders($headers)
        ->putJson('/api/admin/v1/sos/'.$incident->id.'/classification', [
            'operational_status' => 'closed',
            'operational_resolution' => 'Safety team review completed; consumer incident remains authoritative.',
            'reason' => 'Close admin operational workflow',
        ])
        ->assertOk()
        ->assertJsonPath('data.operational_status', 'closed');

    expect($incident->fresh()->status)->toBe('active');
});

test('safety operators can add internal notes without exposing them to consumer SOS records', function (): void {
    Event::fake([AdminSosIncidentUpdated::class]);

    [$incident] = createAdminSafetyIncident();
    $admin = createAdminSafetyAdministrator('safety-operator');

    $this->withHeaders(adminSafetyHeaders($admin))
        ->postJson('/api/admin/v1/sos/'.$incident->id.'/notes', [
            'note' => 'Responder acknowledged by phone; monitor incident.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.note', 'Responder acknowledged by phone; monitor incident.');

    $this->withHeaders(adminSafetyHeaders($admin))
        ->getJson('/api/admin/v1/sos/'.$incident->id)
        ->assertOk()
        ->assertJsonPath('data.notes.0.note', 'Responder acknowledged by phone; monitor incident.');

    Event::assertDispatched(AdminSosIncidentUpdated::class);
});

test('safety operators and super administrators do not receive precise location permission by default', function (): void {
    [$incident] = createAdminSafetyIncident(['last_latitude' => 31.5204, 'last_longitude' => 74.3587, 'last_location_at' => now()]);

    foreach (['safety-operator', 'super-administrator'] as $role) {
        $admin = createAdminSafetyAdministrator($role);
        $this->withHeaders(adminSafetyHeaders($admin))
            ->postJson('/api/admin/v1/sos/'.$incident->id.'/sensitive/location', [
                'purpose' => 'active_incident_support',
                'reason' => 'Emergency safety access requested for active incident.',
            ])
            ->assertForbidden();
    }
});

test('senior safety precise location access requires recent reauthentication', function (): void {
    [$incident] = createAdminSafetyIncident(['last_latitude' => 31.5204, 'last_longitude' => 74.3587, 'last_location_at' => now()]);
    $admin = createAdminSafetyAdministrator('senior-safety-operator');

    $this->withHeaders(adminSafetyHeaders($admin, false))
        ->postJson('/api/admin/v1/sos/'.$incident->id.'/sensitive/location', [
            'purpose' => 'active_incident_support',
            'reason' => 'Emergency safety access requested for active incident.',
        ])
        ->assertStatus(428)
        ->assertJsonPath('code', 'ADMIN_REAUTH_REQUIRED');
});

test('authorized precise location access is reason coded immutable and audited', function (): void {
    [$incident] = createAdminSafetyIncident([
        'last_latitude' => 31.5204,
        'last_longitude' => 74.3587,
        'last_location_accuracy_m' => 5.5,
        'last_location_at' => now(),
    ]);
    $admin = createAdminSafetyAdministrator('senior-safety-operator');

    $this->withHeaders(adminSafetyHeaders($admin))
        ->postJson('/api/admin/v1/sos/'.$incident->id.'/sensitive/location', [
            'purpose' => 'active_incident_support',
            'reason' => 'Responder navigation requires precise current incident location.',
        ])
        ->assertOk()
        ->assertJsonPath('data.latitude', 31.5204)
        ->assertJsonPath('data.longitude', 74.3587);

    $access = AdminSosSensitiveAccess::query()->sole();
    expect($access->access_type)->toBe('location')
        ->and($access->purpose)->toBe('active_incident_support')
        ->and(AdminAuditLog::query()->where('action', 'admin.sos.sensitive.location.viewed')->exists())->toBeTrue();

    expect(fn () => $access->forceFill(['reason' => 'tampered'])->save())->toThrow(LogicException::class);
});

test('authorized recording access reveals only an opaque encrypted reference and never decryption material', function (): void {
    [$incident] = createAdminSafetyIncident([
        'recording_ref' => 'media:sos:ENCRYPTED-OPAQUE-REF',
        'recording_expires_at' => now()->addDays(90),
    ]);
    $admin = createAdminSafetyAdministrator('senior-safety-operator');

    $response = $this->withHeaders(adminSafetyHeaders($admin))
        ->postJson('/api/admin/v1/sos/'.$incident->id.'/sensitive/recording', [
            'purpose' => 'post_incident_review',
            'reason' => 'Senior safety review requires the encrypted recording reference.',
        ])
        ->assertOk()
        ->assertJsonPath('data.encrypted_recording_reference', 'media:sos:ENCRYPTED-OPAQUE-REF')
        ->assertJsonPath('data.plaintext_available_to_admin', false)
        ->assertJsonPath('data.decryption_keys_exposed', false);

    $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
    expect($encoded)->not->toContain('encrypted_key')->not->toContain('private_key');
});

test('sensitive SOS access history is separately permissioned', function (): void {
    [$incident] = createAdminSafetyIncident(['last_latitude' => 31.5204, 'last_longitude' => 74.3587, 'last_location_at' => now()]);
    $senior = createAdminSafetyAdministrator('senior-safety-operator');

    $this->withHeaders(adminSafetyHeaders($senior))
        ->postJson('/api/admin/v1/sos/'.$incident->id.'/sensitive/location', [
            'purpose' => 'active_incident_support',
            'reason' => 'Record an access item for audit history verification.',
        ])->assertOk();

    $operator = createAdminSafetyAdministrator('safety-operator');
    $this->withHeaders(adminSafetyHeaders($operator))
        ->getJson('/api/admin/v1/sos/'.$incident->id.'/sensitive-access')
        ->assertForbidden();

    $this->withHeaders(adminSafetyHeaders($senior))
        ->getJson('/api/admin/v1/sos/'.$incident->id.'/sensitive-access')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.access_type', 'location');
});

test('SOS export requires reauthentication and produces a privacy preserving snapshot', function (): void {
    [$incident] = createAdminSafetyIncident([
        'last_latitude' => 31.5204,
        'last_longitude' => 74.3587,
        'last_location_at' => now(),
        'recording_ref' => 'media:sos:PRIVATE-OPAQUE-REF',
        'recording_expires_at' => now()->addDays(90),
    ]);
    $admin = createAdminSafetyAdministrator('senior-safety-operator');

    $payload = ['format' => 'json', 'reason' => 'Authorized incident export for safety case record.'];

    $this->withHeaders(adminSafetyHeaders($admin, false))
        ->postJson('/api/admin/v1/sos/'.$incident->id.'/exports', $payload)
        ->assertStatus(428);

    $response = $this->withHeaders(adminSafetyHeaders($admin))
        ->postJson('/api/admin/v1/sos/'.$incident->id.'/exports', $payload)
        ->assertCreated()
        ->assertJsonPath('data.snapshot.privacy.contains_precise_location', false)
        ->assertJsonPath('data.snapshot.privacy.contains_recording_reference', false);

    $encoded = json_encode($response->json('data.snapshot'), JSON_THROW_ON_ERROR);
    expect($encoded)
        ->not->toContain('31.5204')
        ->not->toContain('74.3587')
        ->not->toContain('PRIVATE-OPAQUE-REF');
});

test('expired temporary SOS exports are purged by the scheduled cleanup command', function (): void {
    [$incident] = createAdminSafetyIncident();
    $admin = createAdminSafetyAdministrator('senior-safety-operator');

    AdminSosExport::query()->create([
        'sos_event_id' => $incident->id,
        'requested_by_admin_id' => $admin->id,
        'format' => 'json',
        'status' => 'ready',
        'snapshot' => ['safe' => true],
        'requested_at' => now()->subHours(2),
        'expires_at' => now()->subMinute(),
    ]);

    $this->artisan('orbit:admin:sos:purge-expired-exports')->assertSuccessful();

    expect(AdminSosExport::query()->count())->toBe(0);
});

test('SOS directory can filter false alarms abuse flags and unassigned incidents', function (): void {
    [$flagged] = createAdminSafetyIncident();
    [$other] = createAdminSafetyIncident();

    AdminSosIncidentControl::query()->create([
        'sos_event_id' => $flagged->id,
        'false_alarm' => true,
        'abuse_flag' => true,
        'operational_status' => 'monitoring',
    ]);

    $admin = createAdminSafetyAdministrator('safety-operator');

    $this->withHeaders(adminSafetyHeaders($admin))
        ->getJson('/api/admin/v1/sos?false_alarm=1&abuse_flag=1&operational_status=monitoring')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $flagged->id);

    $this->withHeaders(adminSafetyHeaders($admin))
        ->getJson('/api/admin/v1/sos?unassigned=1')
        ->assertOk()
        ->assertJsonPath('data.pagination.total', 2);
});

test('consumer SOS lifecycle updates bridge into a privacy safe administrator realtime event', function (): void {
    Event::fake([AdminSosIncidentUpdated::class]);

    [$incident] = createAdminSafetyIncident([
        'last_latitude' => 31.5204,
        'last_longitude' => 74.3587,
        'last_location_at' => now(),
    ]);

    $event = new SosLocationUpdated([
        'channel' => 'orbit.sos.'.$incident->id,
        'event_name' => 'sos.location.updated',
        'payload' => [
            'sos_id' => $incident->id,
            'latitude' => 31.5204,
            'longitude' => 74.3587,
        ],
    ]);

    app(BroadcastAdminSosLifecycleUpdate::class)->handle($event);

    Event::assertDispatched(AdminSosIncidentUpdated::class, function (AdminSosIncidentUpdated $adminEvent): bool {
        $encoded = json_encode($adminEvent->payload, JSON_THROW_ON_ERROR);

        return ! str_contains($encoded, '31.5204')
            && ! str_contains($encoded, '74.3587')
            && ! array_key_exists('latitude', $adminEvent->payload)
            && ! array_key_exists('longitude', $adminEvent->payload);
    });
});

test('administrator SOS realtime payload itself contains only safe command center metadata', function (): void {
    [$incident] = createAdminSafetyIncident([
        'last_latitude' => 31.5204,
        'last_longitude' => 74.3587,
        'last_location_at' => now(),
        'recording_ref' => 'media:sos:SECRET-REF',
        'recording_expires_at' => now()->addDay(),
    ]);

    Event::fake([AdminSosIncidentUpdated::class]);
    app(AdminSosRealtimeService::class)->broadcast($incident, 'test');

    Event::assertDispatched(AdminSosIncidentUpdated::class, function (AdminSosIncidentUpdated $event): bool {
        $encoded = json_encode($event->payload, JSON_THROW_ON_ERROR);

        return ! str_contains($encoded, '31.5204')
            && ! str_contains($encoded, '74.3587')
            && ! str_contains($encoded, 'SECRET-REF')
            && ($event->payload['has_encrypted_recording_reference'] ?? false) === true;
    });
});

test('default SOS RBAC gives sensitive safety access to senior safety but never implicitly to super administrator', function (): void {
    app(AdminRbacService::class)->syncDefaults();

    $senior = createAdminSafetyAdministrator('senior-safety-operator');
    $super = createAdminSafetyAdministrator('super-administrator');

    expect($senior->hasPermission('sos.view'))->toBeTrue()
        ->and($senior->hasPermission('sos.manage'))->toBeTrue()
        ->and($senior->hasPermission('sos.location.access'))->toBeTrue()
        ->and($senior->hasPermission('sos.recordings.access'))->toBeTrue()
        ->and($super->hasPermission('sos.view'))->toBeTrue()
        ->and($super->hasPermission('sos.manage'))->toBeTrue()
        ->and($super->hasPermission('sos.location.access'))->toBeFalse()
        ->and($super->hasPermission('sos.recordings.access'))->toBeFalse();
});

test('safety operator assignment and classification mutations are fully reason audited', function (): void {
    [$incident] = createAdminSafetyIncident();
    $admin = createAdminSafetyAdministrator('safety-operator');
    $headers = adminSafetyHeaders($admin);

    $this->withHeaders($headers)->putJson('/api/admin/v1/sos/'.$incident->id.'/classification', [
        'internal_escalation_level' => 'elevated',
        'reason' => 'Escalate operational review due delayed responder engagement.',
    ])->assertOk();

    $audit = AdminAuditLog::query()->where('action', 'admin.sos.classification.updated')->sole();

    expect($audit->reason)->toBe('Escalate operational review due delayed responder engagement.')
        ->and($audit->target_type)->toBe('sos_event')
        ->and($audit->target_id)->toBe($incident->id)
        ->and($audit->request_id)->not->toBeNull()
        ->and($audit->before_state)->toBeArray()
        ->and($audit->after_state)->toBeArray();
});

test('SOS command center returns not found instead of leaking unknown incident identifiers', function (): void {
    $admin = createAdminSafetyAdministrator('safety-operator');

    $this->withHeaders(adminSafetyHeaders($admin))
        ->getJson('/api/admin/v1/sos/'.Str::uuid())
        ->assertNotFound()
        ->assertJsonPath('code', 'ADMIN_SOS_NOT_FOUND');
});

test('administrator realtime authentication endpoint is protected by admin authentication', function (): void {
    $this->postJson('/api/admin/v1/realtime/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-admin.safety',
    ])->assertUnauthorized();
});
