<?php

declare(strict_types=1);

use App\Models\AdminAuditLog;
use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\DataExportRequest;
use App\Models\ModerationEnforcement;
use App\Models\PrivacyExportDeliveryLink;
use App\Models\PrivacyRequest;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\SupportTicketLink;
use App\Models\User;
use App\Models\UserContactEvent;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\PrivacySupport\Services\ContactHistoryService;
use App\Modules\Admin\Services\AdminRbacService;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use App\Modules\Identity\Actions\RequestAccountDeletionAction;
use App\Modules\Identity\Actions\RequestDataExportAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;

uses(RefreshDatabase::class);

function m5Admin(string $role = 'compliance-officer'): AdminUser
{
    app(AdminRbacService::class)->syncDefaults();

    $admin = AdminUser::query()->create([
        'name' => 'M5 Administrator',
        'email' => Str::uuid().'@m5.orbit.test',
        'password' => 'StrongPassword!123',
        'status' => AdminStatus::Active,
        'mfa_confirmed_at' => now(),
        'activated_at' => now(),
    ]);

    $roleModel = AdminRole::query()->where('slug', $role)->firstOrFail();
    $admin->roles()->sync([$roleModel->id]);

    return $admin;
}

function m5AdminHeaders(AdminUser $admin, bool $recentReauth = true): array
{
    app('auth')->forgetGuards();

    $token = $admin->createToken('admin-m5-test', ['admin'], now()->addHours(2));

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

function m5ConsumerHeaders(User $user): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('m5-consumer')->plainTextToken];
}

function m5Privacy(User $user, array $overrides = []): PrivacyRequest
{
    return PrivacyRequest::query()->create(array_merge([
        'user_id' => $user->id,
        'type' => 'correction',
        'source' => 'consumer',
        'status' => 'new',
        'identity_status' => 'account_authenticated',
        'details' => 'Please correct the account information in this request.',
        'deadline_at' => now()->addDays(30),
    ], $overrides));
}

test('privacy and support administrator APIs require administrator authentication', function (): void {
    $this->getJson('/api/admin/v1/privacy/requests')->assertUnauthorized();
    $this->getJson('/api/admin/v1/privacy/data-exports')->assertUnauthorized();
    $this->getJson('/api/admin/v1/privacy/account-deletions')->assertUnauthorized();
    $this->getJson('/api/admin/v1/support/tickets')->assertUnauthorized();
});

test('consumer privacy and support endpoints require consumer authentication', function (): void {
    $this->getJson('/api/v1/privacy/requests')->assertUnauthorized();
    $this->postJson('/api/v1/privacy/requests', [])->assertUnauthorized();
    $this->getJson('/api/v1/support/tickets')->assertUnauthorized();
    $this->postJson('/api/v1/support/tickets', [])->assertUnauthorized();
});

test('consumer can create and list correction consent and access privacy requests', function (): void {
    $user = User::factory()->create();
    $headers = m5ConsumerHeaders($user);

    foreach (['access', 'correction', 'consent'] as $type) {
        $this->withHeaders($headers)->postJson('/api/v1/privacy/requests', [
            'type' => $type,
            'details' => 'Please process this privacy request with the required account review.',
        ])->assertAccepted()->assertJsonPath('data.type', $type);
    }

    $this->withHeaders($headers)->getJson('/api/v1/privacy/requests')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('consumer privacy request detail is scoped to its owner', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();
    $privacy = m5Privacy($one);

    $this->withHeaders(m5ConsumerHeaders($two))
        ->getJson('/api/v1/privacy/requests/'.$privacy->id)
        ->assertNotFound();
});

test('identity data export automatically creates a linked privacy case', function (): void {
    $user = User::factory()->create();

    $export = app(RequestDataExportAction::class)->handle($user);

    $privacy = PrivacyRequest::query()->where('linked_data_export_id', $export->id)->firstOrFail();
    expect($privacy->user_id)->toBe($user->id)
        ->and($privacy->type)->toBe('data_export')
        ->and($privacy->status)->toBe('completed');
});

test('identity account deletion lifecycle automatically synchronizes privacy case state', function (): void {
    $user = User::factory()->create();

    $deletion = app(RequestAccountDeletionAction::class)->handle($user);
    $privacy = PrivacyRequest::query()->where('linked_deletion_id', $deletion->id)->firstOrFail();

    expect($privacy->status)->toBe('in_progress');

    $deletion->forceFill(['status' => 'blocked', 'blocking_reason' => 'Ownership transfer required.'])->save();

    expect($privacy->refresh()->status)->toBe('waiting_user')
        ->and($privacy->resolution)->toBe('Ownership transfer required.');
});

test('privacy directory supports overdue unassigned and workflow filters with pagination', function (): void {
    $user = User::factory()->create();
    m5Privacy($user, ['deadline_at' => now()->subDay()]);
    m5Privacy($user, ['status' => 'completed', 'completed_at' => now(), 'deadline_at' => now()->subDay()]);

    $admin = m5Admin('read-only');

    $this->withHeaders(m5AdminHeaders($admin))
        ->getJson('/api/admin/v1/privacy/requests?overdue=1&unassigned=1&per_page=10')
        ->assertOk()
        ->assertJsonCount(1, 'data.items');
});

test('read only administrator can view privacy and support but cannot mutate them', function (): void {
    $user = User::factory()->create();
    $privacy = m5Privacy($user);
    $ticket = SupportTicket::query()->create([
        'user_id' => $user->id, 'category' => 'account', 'priority' => 'normal',
        'status' => 'new', 'subject' => 'Read only support test', 'sla_due_at' => now()->addDay(),
    ]);
    $headers = m5AdminHeaders(m5Admin('read-only'));

    $this->withHeaders($headers)->getJson('/api/admin/v1/privacy/requests')->assertOk();
    $this->withHeaders($headers)->getJson('/api/admin/v1/support/tickets')->assertOk();
    $this->withHeaders($headers)->patchJson('/api/admin/v1/privacy/requests/'.$privacy->id, [
        'status' => 'in_progress', 'reason' => 'Attempt',
    ])->assertForbidden();
    $this->withHeaders($headers)->patchJson('/api/admin/v1/support/tickets/'.$ticket->id, [
        'status' => 'open', 'reason' => 'Attempt',
    ])->assertForbidden();
});

test('privacy assignment accepts only active privacy eligible administrators', function (): void {
    $user = User::factory()->create();
    $privacy = m5Privacy($user);
    $actor = m5Admin('compliance-officer');
    $eligible = m5Admin('compliance-officer');
    $ineligible = m5Admin('finance-manager');
    $headers = m5AdminHeaders($actor);

    $this->withHeaders($headers)->patchJson('/api/admin/v1/privacy/requests/'.$privacy->id.'/assignment', [
        'assigned_admin_id' => $eligible->id, 'reason' => 'Assign compliance case',
    ])->assertOk()->assertJsonPath('data.assigned_admin_id', $eligible->id);

    $this->withHeaders($headers)->patchJson('/api/admin/v1/privacy/requests/'.$privacy->id.'/assignment', [
        'assigned_admin_id' => $ineligible->id, 'reason' => 'Invalid assignment',
    ])->assertStatus(422);
});

test('final privacy requests cannot be silently reopened', function (): void {
    $user = User::factory()->create();
    $privacy = m5Privacy($user, ['status' => 'completed', 'resolution' => 'Completed.', 'completed_at' => now()]);
    $headers = m5AdminHeaders(m5Admin('compliance-officer'));

    $this->withHeaders($headers)->patchJson('/api/admin/v1/privacy/requests/'.$privacy->id, [
        'status' => 'in_progress', 'reason' => 'Attempt reopen',
    ])->assertStatus(409);
});

test('privacy completion requires a resolution and creates contact history', function (): void {
    $user = User::factory()->create();
    $privacy = m5Privacy($user);
    $headers = m5AdminHeaders(m5Admin('compliance-officer'));

    $this->withHeaders($headers)->patchJson('/api/admin/v1/privacy/requests/'.$privacy->id, [
        'status' => 'completed', 'reason' => 'No resolution',
    ])->assertStatus(422);

    $this->withHeaders($headers)->patchJson('/api/admin/v1/privacy/requests/'.$privacy->id, [
        'status' => 'completed', 'resolution' => 'Correction request completed.', 'reason' => 'Verified completion',
    ])->assertOk();

    expect(UserContactEvent::query()->where('user_id', $user->id)->where('kind', 'privacy.request.completed')->exists())->toBeTrue();
});

test('sensitive privacy identity verification requires recent administrator reauthentication', function (): void {
    $privacy = m5Privacy(User::factory()->create(), ['status' => 'verification_required', 'identity_status' => 'pending']);
    $admin = m5Admin('compliance-officer');

    $this->withHeaders(m5AdminHeaders($admin, false))
        ->postJson('/api/admin/v1/privacy/requests/'.$privacy->id.'/identity-verification', [
            'method' => 'support_verified', 'reason' => 'Verified via support process',
        ])->assertStatus(428)->assertJsonPath('code', 'ADMIN_REAUTH_REQUIRED');

    $this->withHeaders(m5AdminHeaders($admin, true))
        ->postJson('/api/admin/v1/privacy/requests/'.$privacy->id.'/identity-verification', [
            'method' => 'support_verified', 'reason' => 'Verified via support process',
        ])->assertOk()
        ->assertJsonPath('data.identity_status', 'verified');
});

test('super administrator does not inherit sensitive compliance permissions by default', function (): void {
    $super = m5Admin('super-administrator');
    $compliance = m5Admin('compliance-officer');

    expect($super->hasPermission('privacy.view'))->toBeTrue()
        ->and($super->hasPermission('privacy.exports.deliver'))->toBeFalse()
        ->and($super->hasPermission('privacy.deletions.manage'))->toBeFalse()
        ->and($super->hasPermission('privacy.identity.verify'))->toBeFalse()
        ->and($compliance->hasPermission('privacy.exports.deliver'))->toBeTrue()
        ->and($compliance->hasPermission('privacy.deletions.manage'))->toBeTrue();
});

test('compliance administrator can generate an export for an authenticated access request', function (): void {
    $user = User::factory()->create();
    $privacy = m5Privacy($user, [
        'type' => 'access',
        'identity_status' => 'account_authenticated',
    ]);

    $this->withHeaders(m5AdminHeaders(m5Admin('compliance-officer')))
        ->postJson('/api/admin/v1/privacy/requests/'.$privacy->id.'/generate-export', [
            'reason' => 'Generate verified access export',
        ])->assertCreated()
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.payload_available', true);
});

test('admin data export views expose availability metadata but never the export payload', function (): void {
    $user = User::factory()->create();
    $export = app(RequestDataExportAction::class)->handle($user);
    $headers = m5AdminHeaders(m5Admin('compliance-officer'));

    $response = $this->withHeaders($headers)
        ->getJson('/api/admin/v1/privacy/data-exports/'.$export->id)
        ->assertOk()
        ->assertJsonPath('data.payload_available', true);

    expect($response->getContent())->not->toContain('"profile"')
        ->and($response->getContent())->not->toContain('privacy_note');
});

test('data export delivery link creation requires recent reauthentication', function (): void {
    $export = app(RequestDataExportAction::class)->handle(User::factory()->create());
    $admin = m5Admin('compliance-officer');

    $this->withHeaders(m5AdminHeaders($admin, false))
        ->postJson('/api/admin/v1/privacy/data-exports/'.$export->id.'/delivery-links', ['reason' => 'Deliver verified export'])
        ->assertStatus(428)
        ->assertJsonPath('code', 'ADMIN_REAUTH_REQUIRED');
});

test('compliance administrator creates an audited time limited export delivery token', function (): void {
    $user = User::factory()->create();
    $export = app(RequestDataExportAction::class)->handle($user);
    $admin = m5Admin('compliance-officer');
    $headers = m5AdminHeaders($admin);

    $response = $this->withHeaders($headers)
        ->postJson('/api/admin/v1/privacy/data-exports/'.$export->id.'/delivery-links', ['reason' => 'Verified export delivery'])
        ->assertCreated();

    $token = $response->json('data.delivery_token');
    expect($token)->toBeString()->not->toBeEmpty()
        ->and(PrivacyExportDeliveryLink::query()->where('token_hash', hash('sha256', $token))->exists())->toBeTrue()
        ->and(AdminAuditLog::query()->where('action', 'admin.privacy.export.delivery_link.created')->exists())->toBeTrue();
});

test('export delivery token is owner scoped and marks successful delivery', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $export = app(RequestDataExportAction::class)->handle($user);
    $admin = m5Admin('compliance-officer');

    $token = $this->withHeaders(m5AdminHeaders($admin))
        ->postJson('/api/admin/v1/privacy/data-exports/'.$export->id.'/delivery-links', ['reason' => 'Verified delivery'])
        ->assertCreated()
        ->json('data.delivery_token');

    $this->withHeaders(m5ConsumerHeaders($other))
        ->getJson('/api/v1/privacy/export-deliveries/'.$token)
        ->assertNotFound();

    $this->withHeaders(m5ConsumerHeaders($user))
        ->getJson('/api/v1/privacy/export-deliveries/'.$token)
        ->assertOk()
        ->assertJsonPath('data.export_id', $export->id);

    expect(PrivacyExportDeliveryLink::query()->where('token_hash', hash('sha256', $token))->firstOrFail()->delivered_at)->not->toBeNull();
});

test('revoked export delivery token can no longer be redeemed', function (): void {
    $user = User::factory()->create();
    $export = app(RequestDataExportAction::class)->handle($user);
    $admin = m5Admin('compliance-officer');
    $headers = m5AdminHeaders($admin);

    $created = $this->withHeaders($headers)
        ->postJson('/api/admin/v1/privacy/data-exports/'.$export->id.'/delivery-links', ['reason' => 'Initial delivery'])
        ->assertCreated();
    $token = $created->json('data.delivery_token');
    $linkId = $created->json('data.id');

    $this->withHeaders($headers)
        ->deleteJson('/api/admin/v1/privacy/data-exports/'.$export->id.'/delivery-links/'.$linkId, ['reason' => 'Revoke link'])
        ->assertOk();

    $this->withHeaders(m5ConsumerHeaders($user))
        ->getJson('/api/v1/privacy/export-deliveries/'.$token)
        ->assertNotFound();
});

test('expired export cleanup redacts payload and expires delivery links', function (): void {
    $user = User::factory()->create();
    $export = app(RequestDataExportAction::class)->handle($user);
    $export->forceFill(['expires_at' => now()->subMinute()])->save();

    PrivacyExportDeliveryLink::query()->create([
        'data_export_request_id' => $export->id,
        'user_id' => $user->id,
        'token_hash' => hash('sha256', 'expired-link'),
        'expires_at' => now()->subMinute(),
    ]);

    $this->artisan('orbit:privacy:purge-expired-exports')->assertSuccessful();

    expect($export->refresh()->payload)->toBeNull()
        ->and($export->status)->toBe('expired')
        ->and(PrivacyExportDeliveryLink::query()->firstOrFail()->revoked_at)->not->toBeNull();
});

test('expired data export can be regenerated without exposing old payload', function (): void {
    $user = User::factory()->create();
    $export = app(RequestDataExportAction::class)->handle($user);
    $export->forceFill(['status' => 'expired', 'payload' => null, 'expires_at' => now()->subMinute()])->save();

    $response = $this->withHeaders(m5AdminHeaders(m5Admin('compliance-officer')))
        ->postJson('/api/admin/v1/privacy/data-exports/'.$export->id.'/regenerate', ['reason' => 'Regenerate failed delivery'])
        ->assertCreated();

    expect($response->json('data.id'))->not->toBe($export->id)
        ->and($response->json('data.payload_available'))->toBeTrue();
});

test('account deletion administration refuses finalization before grace period ends', function (): void {
    $user = User::factory()->create();
    $deletion = app(RequestAccountDeletionAction::class)->handle($user);

    $this->withHeaders(m5AdminHeaders(m5Admin('compliance-officer')))
        ->postJson('/api/admin/v1/privacy/account-deletions/'.$deletion->id.'/finalize', ['reason' => 'Attempt early finalization'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'DELETION_NOT_DUE');
});

test('due account deletion finalization still honors Circle ownership blocker', function (): void {
    $user = User::factory()->create();
    $circle = Circle::query()->create(['created_by' => $user->id, 'name' => 'Owned Circle', 'type' => 'standard']);
    CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $user->id,
        'role' => CircleRole::Owner,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]);

    $deletion = app(RequestAccountDeletionAction::class)->handle($user);
    $deletion->forceFill(['scheduled_for' => now()->subMinute()])->save();

    $this->withHeaders(m5AdminHeaders(m5Admin('compliance-officer')))
        ->postJson('/api/admin/v1/privacy/account-deletions/'.$deletion->id.'/finalize', ['reason' => 'Finalize due deletion'])
        ->assertOk()
        ->assertJsonPath('data.result', 'blocked_owner');

    expect($deletion->refresh()->status)->toBe('blocked');
});

test('administrator cancellation uses the real Identity deletion workflow', function (): void {
    $user = User::factory()->create();
    $deletion = app(RequestAccountDeletionAction::class)->handle($user);

    $this->withHeaders(m5AdminHeaders(m5Admin('compliance-officer')))
        ->postJson('/api/admin/v1/privacy/account-deletions/'.$deletion->id.'/cancel', ['reason' => 'Verified customer cancellation'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($user->refresh()->account_deletion_scheduled_for)->toBeNull()
        ->and(PrivacyRequest::query()->where('linked_deletion_id', $deletion->id)->firstOrFail()->status)->toBe('cancelled');
});

test('consumer can create support ticket and only view own tickets', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();

    $ticketId = $this->withHeaders(m5ConsumerHeaders($one))->postJson('/api/v1/support/tickets', [
        'category' => 'technical',
        'subject' => 'App connection issue',
        'message' => 'The app cannot reconnect after changing network.',
    ])->assertCreated()->json('data.id');

    $this->withHeaders(m5ConsumerHeaders($one))->getJson('/api/v1/support/tickets/'.$ticketId)->assertOk();
    $this->withHeaders(m5ConsumerHeaders($two))->getJson('/api/v1/support/tickets/'.$ticketId)->assertNotFound();
});

test('consumer never sees internal administrator support notes', function (): void {
    $user = User::factory()->create();
    $ticket = SupportTicket::query()->create([
        'user_id' => $user->id, 'category' => 'account', 'priority' => 'normal',
        'status' => 'open', 'subject' => 'Internal note privacy', 'sla_due_at' => now()->addDay(),
    ]);
    SupportMessage::query()->create([
        'support_ticket_id' => $ticket->id, 'actor_type' => 'admin',
        'body' => 'INTERNAL-ONLY-SUPPORT-NOTE', 'internal' => true,
    ]);

    $response = $this->withHeaders(m5ConsumerHeaders($user))
        ->getJson('/api/v1/support/tickets/'.$ticket->id)
        ->assertOk();

    expect($response->getContent())->not->toContain('INTERNAL-ONLY-SUPPORT-NOTE');
});

test('support agent external reply records contact history and notification without exposing internal note', function (): void {
    $user = User::factory()->create();
    $ticket = SupportTicket::query()->create([
        'user_id' => $user->id, 'category' => 'account', 'priority' => 'normal',
        'status' => 'open', 'subject' => 'Reply workflow', 'sla_due_at' => now()->addDay(),
    ]);
    $headers = m5AdminHeaders(m5Admin('support-agent'));

    $this->withHeaders($headers)->postJson('/api/admin/v1/support/tickets/'.$ticket->id.'/messages', [
        'message' => 'We have reviewed your request and need one more detail.',
        'internal' => false,
    ])->assertCreated();

    expect(UserContactEvent::query()->where('user_id', $user->id)->where('kind', 'support.admin_reply')->exists())->toBeTrue();
});

test('support internal note requires note permission and remains private', function (): void {
    $user = User::factory()->create();
    $ticket = SupportTicket::query()->create([
        'user_id' => $user->id, 'category' => 'account', 'priority' => 'normal',
        'status' => 'open', 'subject' => 'Private note', 'sla_due_at' => now()->addDay(),
    ]);
    $headers = m5AdminHeaders(m5Admin('support-agent'));

    $this->withHeaders($headers)->postJson('/api/admin/v1/support/tickets/'.$ticket->id.'/messages', [
        'message' => 'Internal diagnostic context.',
        'internal' => true,
    ])->assertCreated();

    expect(SupportMessage::query()->where('support_ticket_id', $ticket->id)->where('internal', true)->exists())->toBeTrue();
});

test('support assignment only accepts operationally eligible support administrators', function (): void {
    $user = User::factory()->create();
    $ticket = SupportTicket::query()->create([
        'user_id' => $user->id, 'category' => 'account', 'priority' => 'normal',
        'status' => 'new', 'subject' => 'Assignment', 'sla_due_at' => now()->addDay(),
    ]);
    $actor = m5Admin('support-agent');
    $eligible = m5Admin('support-agent');
    $invalid = m5Admin('finance-manager');
    $headers = m5AdminHeaders($actor);

    $this->withHeaders($headers)->patchJson('/api/admin/v1/support/tickets/'.$ticket->id.'/assignment', [
        'assigned_admin_id' => $eligible->id, 'reason' => 'Assign support ticket',
    ])->assertOk();

    $this->withHeaders($headers)->patchJson('/api/admin/v1/support/tickets/'.$ticket->id.'/assignment', [
        'assigned_admin_id' => $invalid->id, 'reason' => 'Invalid assignment',
    ])->assertStatus(422);
});

test('support directory supports unassigned SLA breach and pagination filters', function (): void {
    $user = User::factory()->create();
    SupportTicket::query()->create([
        'user_id' => $user->id, 'category' => 'technical', 'priority' => 'urgent',
        'status' => 'open', 'subject' => 'SLA breach', 'sla_due_at' => now()->subHour(),
    ]);
    SupportTicket::query()->create([
        'user_id' => $user->id, 'category' => 'technical', 'priority' => 'normal',
        'status' => 'resolved', 'subject' => 'Resolved case', 'sla_due_at' => now()->subHour(), 'resolved_at' => now(),
    ]);

    $this->withHeaders(m5AdminHeaders(m5Admin('support-agent')))
        ->getJson('/api/admin/v1/support/tickets?sla_breached=1&unassigned=1&per_page=10')
        ->assertOk()
        ->assertJsonCount(1, 'data.items');
});

test('closed support tickets cannot be administratively reopened or receive replies', function (): void {
    $user = User::factory()->create();
    $ticket = SupportTicket::query()->create([
        'user_id' => $user->id, 'category' => 'account', 'priority' => 'normal',
        'status' => 'closed', 'subject' => 'Closed case', 'sla_due_at' => now()->subDay(), 'resolved_at' => now(),
    ]);
    $headers = m5AdminHeaders(m5Admin('support-agent'));

    $this->withHeaders($headers)->patchJson('/api/admin/v1/support/tickets/'.$ticket->id, [
        'status' => 'open', 'reason' => 'Attempt reopen',
    ])->assertStatus(409);

    $this->withHeaders($headers)->postJson('/api/admin/v1/support/tickets/'.$ticket->id.'/messages', [
        'message' => 'Attempt reply', 'internal' => false,
    ])->assertStatus(409);
});

test('administrator can open a real support case for a selected user', function (): void {
    $user = User::factory()->create();

    $this->withHeaders(m5AdminHeaders(m5Admin('support-agent')))
        ->postJson('/api/admin/v1/support/tickets', [
            'user_id' => $user->id,
            'category' => 'account',
            'priority' => 'high',
            'subject' => 'Proactive account assistance',
            'message' => 'Orbit Support opened this case to help with the account.',
            'reason' => 'Customer assistance',
        ])->assertCreated()
        ->assertJsonPath('data.user_id', $user->id);
});

test('support resource links are idempotent and audited', function (): void {
    $user = User::factory()->create();
    $privacy = m5Privacy($user);
    $ticket = SupportTicket::query()->create([
        'user_id' => $user->id, 'category' => 'privacy', 'priority' => 'normal',
        'status' => 'open', 'subject' => 'Linked privacy request', 'sla_due_at' => now()->addDay(),
    ]);
    $headers = m5AdminHeaders(m5Admin('support-agent'));
    $payload = ['resource_type' => 'privacy_request', 'resource_id' => $privacy->id];

    $this->withHeaders($headers)->postJson('/api/admin/v1/support/tickets/'.$ticket->id.'/links', $payload)->assertCreated();
    $this->withHeaders($headers)->postJson('/api/admin/v1/support/tickets/'.$ticket->id.'/links', $payload)->assertCreated();

    expect(SupportTicketLink::query()->count())->toBe(1);
});

test('consumer reply reopens resolved support case but cannot reply to closed case', function (): void {
    $user = User::factory()->create();
    $resolved = SupportTicket::query()->create([
        'user_id' => $user->id, 'category' => 'technical', 'priority' => 'normal',
        'status' => 'resolved', 'subject' => 'Resolved then reply', 'sla_due_at' => now(), 'resolved_at' => now(),
    ]);

    $this->withHeaders(m5ConsumerHeaders($user))
        ->postJson('/api/v1/support/tickets/'.$resolved->id.'/messages', ['message' => 'The issue returned.'])
        ->assertCreated();

    expect($resolved->refresh()->status)->toBe('open');
});

test('contact history metadata strips secret shaped fields and is immutable', function (): void {
    $user = User::factory()->create();
    $event = app(ContactHistoryService::class)->record(
        (int) $user->id,
        'security.notice',
        'system',
        'outbound',
        'Security notice',
        'Safe summary.',
        'test',
        '1',
        metadata: [
            'access_token' => 'SECRET-TOKEN',
            'safe' => 'visible',
            'nested' => ['private_key_material' => 'PRIVATE', 'ok' => true],
        ],
    );

    expect($event->metadata)->toBe(['safe' => 'visible', 'nested' => ['ok' => true]])
        ->and(fn () => $event->forceFill(['summary' => 'changed'])->save())
        ->toThrow(LogicException::class);
});

test('contact history endpoint is user scoped and contains safe communication metadata', function (): void {
    $user = User::factory()->create();
    app(ContactHistoryService::class)->record(
        (int) $user->id, 'support.reply', 'support', 'outbound',
        'Support reply', 'Safe summary.', 'support_ticket', (string) Str::uuid(),
    );

    $this->withHeaders(m5AdminHeaders(m5Admin('support-agent')))
        ->getJson('/api/admin/v1/users/'.$user->id.'/contact-history')
        ->assertOk()
        ->assertJsonPath('data.items.0.kind', 'support.reply');
});

test('moderation user enforcement automatically enters contact history without storing enforcement reason', function (): void {
    $user = User::factory()->create();
    $admin = m5Admin('moderator');

    $enforcement = ModerationEnforcement::query()->create([
        'target_type' => 'user',
        'target_id' => (string) $user->id,
        'action' => 'warn_user',
        'reason' => 'SENSITIVE-INTERNAL-ENFORCEMENT-REASON',
        'admin_user_id' => $admin->id,
        'status' => 'applied',
        'applied_at' => now(),
    ]);

    $event = UserContactEvent::query()
        ->where('source_type', 'moderation_enforcement')
        ->where('source_id', $enforcement->id)
        ->firstOrFail();

    expect(json_encode($event->toArray(), JSON_THROW_ON_ERROR))->not->toContain('SENSITIVE-INTERNAL-ENFORCEMENT-REASON');
});

test('privacy and support mutations produce immutable admin audit history', function (): void {
    $user = User::factory()->create();
    $privacy = m5Privacy($user);
    $admin = m5Admin('compliance-officer');
    $headers = m5AdminHeaders($admin);

    $this->withHeaders($headers)->patchJson('/api/admin/v1/privacy/requests/'.$privacy->id, [
        'status' => 'in_progress', 'reason' => 'Begin compliance review',
    ])->assertOk();

    expect(AdminAuditLog::query()->where('action', 'admin.privacy.workflow.updated')->exists())->toBeTrue();
});

test('default RBAC grants support and compliance capabilities to their intended roles', function (): void {
    $support = m5Admin('support-agent');
    $compliance = m5Admin('compliance-officer');
    $readOnly = m5Admin('read-only');

    expect($support->hasPermission('support.reply'))->toBeTrue()
        ->and($support->hasPermission('privacy.exports.deliver'))->toBeFalse()
        ->and($compliance->hasPermission('privacy.identity.verify'))->toBeTrue()
        ->and($compliance->hasPermission('privacy.deletions.manage'))->toBeTrue()
        ->and($readOnly->hasPermission('privacy.view'))->toBeTrue()
        ->and($readOnly->hasPermission('support.view'))->toBeTrue()
        ->and($readOnly->hasPermission('support.reply'))->toBeFalse();
});

test('identity privacy synchronization command imports preexisting records idempotently', function (): void {
    $user = User::factory()->create();
    $export = DataExportRequest::withoutEvents(fn () => DataExportRequest::query()->create([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'status' => 'ready',
        'payload' => ['profile' => ['id' => $user->id]],
        'requested_at' => now(),
        'completed_at' => now(),
        'expires_at' => now()->addDays(7),
    ]));

    expect(PrivacyRequest::query()->where('linked_data_export_id', $export->id)->exists())->toBeFalse();

    $this->artisan('orbit:privacy:sync-identity-requests')->assertSuccessful();
    $this->artisan('orbit:privacy:sync-identity-requests')->assertSuccessful();

    expect(PrivacyRequest::query()->where('linked_data_export_id', $export->id)->count())->toBe(1);
});

test('unknown privacy support and contact identifiers return not found without leaking records', function (): void {
    $admin = m5Admin('compliance-officer');
    $headers = m5AdminHeaders($admin);
    $unknown = (string) Str::uuid();

    $this->withHeaders($headers)->getJson('/api/admin/v1/privacy/requests/'.$unknown)->assertNotFound();
    $this->withHeaders($headers)->getJson('/api/admin/v1/privacy/data-exports/'.$unknown)->assertNotFound();
    $this->withHeaders($headers)->getJson('/api/admin/v1/privacy/account-deletions/'.$unknown)->assertNotFound();

    $supportHeaders = m5AdminHeaders(m5Admin('support-agent'));
    $this->withHeaders($supportHeaders)->getJson('/api/admin/v1/support/tickets/'.$unknown)->assertNotFound();
});
