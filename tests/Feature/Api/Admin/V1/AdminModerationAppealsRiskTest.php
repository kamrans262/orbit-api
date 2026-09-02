<?php

declare(strict_types=1);

use App\Models\ActivityEvent;
use App\Models\AdminAuditLog;
use App\Models\AdminCircleControl;
use App\Models\AdminRiskProfile;
use App\Models\AdminRiskSignal;
use App\Models\AdminRole;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\AdminUserControl;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\Device;
use App\Models\Message;
use App\Models\MessageEnvelope;
use App\Models\ModerationAppeal;
use App\Models\ModerationEnforcement;
use App\Models\ModerationReport;
use App\Models\OrbitNotification;
use App\Models\User;
use App\Modules\Activity\Enums\ActivityEventType;
use App\Modules\Admin\Enums\AdminStatus;
use App\Modules\Admin\Moderation\Broadcasts\AdminModerationRealtimeBroadcast;
use App\Modules\Admin\Moderation\Services\AdminRiskService;
use App\Modules\Admin\Moderation\Services\ModerationIntakeService;
use App\Modules\Admin\Services\AdminRbacService;
use App\Modules\Auth\Mail\EmailOtpMail;
use App\Modules\Auth\Services\EmailOtpGenerator;
use App\Modules\Circles\Enums\CircleRole;
use App\Modules\Circles\Enums\LocationMode;
use App\Modules\Messaging\Enums\MessageType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function m4Admin(string $role = 'moderator'): AdminUser
{
    app(AdminRbacService::class)->syncDefaults();

    $admin = AdminUser::query()->create([
        'name' => 'Moderation Administrator',
        'email' => Str::uuid().'@moderation.orbit.test',
        'password' => 'StrongPassword!123',
        'status' => AdminStatus::Active,
        'mfa_confirmed_at' => now(),
        'activated_at' => now(),
    ]);

    $roleModel = AdminRole::query()->where('slug', $role)->firstOrFail();
    $admin->roles()->sync([$roleModel->id]);

    return $admin;
}

function m4AdminHeaders(AdminUser $admin, bool $recentReauth = true): array
{
    app('auth')->forgetGuards();

    $token = $admin->createToken('admin-moderation-test', ['admin'], now()->addHours(2));

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

function m4ConsumerHeaders(User $user): array
{
    app('auth')->forgetGuards();

    return ['Authorization' => 'Bearer '.$user->createToken('m4-consumer')->plainTextToken];
}

function m4Circle(User $owner, ?User $member = null): Circle
{
    $circle = Circle::query()->create([
        'created_by' => $owner->id,
        'name' => 'Moderation Circle',
        'type' => 'standard',
    ]);

    CircleMember::query()->create([
        'circle_id' => $circle->id,
        'user_id' => $owner->id,
        'role' => CircleRole::Owner,
        'location_mode' => LocationMode::Hidden,
        'joined_at' => now(),
    ]);

    if ($member) {
        CircleMember::query()->create([
            'circle_id' => $circle->id,
            'user_id' => $member->id,
            'role' => CircleRole::Member,
            'location_mode' => LocationMode::Hidden,
            'joined_at' => now(),
        ]);
    }

    return $circle;
}

function m4UserReport(User $reporter, User $target, string $reason = 'harassment', array $extra = []): ModerationReport
{
    m4Circle($target, $reporter);

    return app(ModerationIntakeService::class)->create($reporter, array_merge([
        'target_type' => 'user',
        'target_id' => (string) $target->id,
        'reason' => $reason,
        'details' => 'Please review the account behavior.',
    ], $extra));
}

test('moderation administrator APIs require administrator authentication', function (): void {
    $this->getJson('/api/admin/v1/reports')->assertUnauthorized();
    $this->getJson('/api/admin/v1/appeals')->assertUnauthorized();
    $this->getJson('/api/admin/v1/risk')->assertUnauthorized();
});

test('consumer report and appeal endpoints require consumer authentication', function (): void {
    $this->postJson('/api/v1/reports', [])->assertUnauthorized();
    $this->postJson('/api/v1/appeals', [])->assertUnauthorized();
});

test('read only administrators can view moderation queues but cannot mutate cases', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $report = m4UserReport($reporter, $target);
    $admin = m4Admin('read-only');
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->getJson('/api/admin/v1/reports')->assertOk();
    $this->withHeaders($headers)->getJson('/api/admin/v1/reports/'.$report->id)->assertOk();
    $this->withHeaders($headers)->patchJson('/api/admin/v1/reports/'.$report->id, [
        'status' => 'triaged', 'reason' => 'Attempt mutation',
    ])->assertForbidden();
});

test('consumer user reports persist only explicit reporter submitted evidence and create risk', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    m4Circle($target, $reporter);

    $response = $this->withHeaders(m4ConsumerHeaders($reporter))->postJson('/api/v1/reports', [
        'client_report_id' => (string) Str::uuid(),
        'target_type' => 'user',
        'target_id' => (string) $target->id,
        'reason' => 'threats',
        'details' => 'Threatening account behavior.',
        'evidence_text' => 'Text I explicitly chose to submit.',
        'evidence_refs' => ['client-screenshot:1'],
    ])->assertStatus(202);

    $report = ModerationReport::query()->findOrFail($response->json('data.id'));
    expect($report->evidence)->toBe([
        'origin' => 'reporter_submitted',
        'text' => 'Text I explicitly chose to submit.',
        'refs' => ['client-screenshot:1'],
    ])->and($report->target_user_id)->toBe($target->id)
        ->and(AdminRiskProfile::query()->findOrFail($target->id)->score)->toBeGreaterThan(0);
});

test('consumer client report id makes retries idempotent', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    m4Circle($target, $reporter);
    $id = (string) Str::uuid();
    $payload = ['client_report_id' => $id, 'target_type' => 'user', 'target_id' => (string) $target->id, 'reason' => 'spam'];

    $this->withHeaders(m4ConsumerHeaders($reporter))->postJson('/api/v1/reports', $payload)->assertStatus(202);
    $this->withHeaders(m4ConsumerHeaders($reporter))->postJson('/api/v1/reports', $payload)->assertStatus(202);

    expect(ModerationReport::query()->count())->toBe(1);
});

test('consumer cannot enumerate unrelated user report targets', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();

    $this->withHeaders(m4ConsumerHeaders($reporter))->postJson('/api/v1/reports', [
        'target_type' => 'user', 'target_id' => (string) $target->id, 'reason' => 'spam',
    ])->assertNotFound()->assertJsonPath('code', 'REPORT_TARGET_UNAVAILABLE');
});

test('reported encrypted messages expose metadata but never server ciphertext', function (): void {
    $sender = User::factory()->create();
    $reporter = User::factory()->create();
    $circle = m4Circle($sender, $reporter);
    $senderDevice = Device::query()->create([
        'user_id' => $sender->id, 'client_device_id' => 'm4-sender', 'platform' => 'android', 'public_identity_key' => 'sender-key',
    ]);
    $recipientDevice = Device::query()->create([
        'user_id' => $reporter->id, 'client_device_id' => 'm4-recipient', 'platform' => 'ios', 'public_identity_key' => 'recipient-key',
    ]);
    $message = Message::query()->create([
        'id' => (string) Str::uuid(), 'circle_id' => $circle->id, 'sender_user_id' => $sender->id, 'sender_device_id' => $senderDevice->id,
        'type' => MessageType::Text, 'expires_at' => now()->addDays(30),
    ]);
    MessageEnvelope::query()->create([
        'envelope_id' => (string) Str::uuid(), 'message_id' => $message->id, 'recipient_user_id' => $reporter->id,
        'recipient_device_id' => $recipientDevice->id, 'ciphertext' => 'SERVER-CIPHERTEXT-MUST-STAY-PRIVATE',
        'encrypted_preview' => 'OPAQUE-PREVIEW', 'expires_at' => now()->addDays(30),
    ]);

    $response = $this->withHeaders(m4ConsumerHeaders($reporter))->postJson('/api/v1/reports', [
        'target_type' => 'message', 'target_id' => $message->id, 'reason' => 'harassment', 'evidence_text' => 'Reporter supplied context only.',
    ])->assertStatus(202);

    $report = ModerationReport::query()->findOrFail($response->json('data.id'));
    $encoded = json_encode([$report->target_snapshot, $report->evidence], JSON_THROW_ON_ERROR);
    expect($encoded)->not->toContain('SERVER-CIPHERTEXT-MUST-STAY-PRIVATE')
        ->and($encoded)->not->toContain('OPAQUE-PREVIEW')
        ->and($report->target_snapshot['privacy'])->toBe('metadata_only');
});

test('existing Activity reports are ingested into the unified moderation queue idempotently', function (): void {
    $actor = User::factory()->create();
    $reporter = User::factory()->create();
    $circle = m4Circle($actor, $reporter);
    $event = ActivityEvent::query()->create([
        'circle_id' => $circle->id, 'actor_user_id' => $actor->id, 'event_type' => ActivityEventType::MomentPublished->value,
        'source_type' => 'test', 'source_id' => (string) Str::uuid(), 'event_key' => 'm4:'.Str::uuid(), 'payload' => ['safe' => true], 'occurred_at' => now(),
    ]);

    $this->withHeaders(m4ConsumerHeaders($reporter))->postJson('/api/v1/activity/'.$event->id.'/report', [
        'reason' => 'safety', 'details' => 'Review this activity.',
    ])->assertStatus(202);
    $this->withHeaders(m4ConsumerHeaders($reporter))->postJson('/api/v1/activity/'.$event->id.'/report', [
        'reason' => 'safety', 'details' => 'Review this activity.',
    ])->assertStatus(202);

    expect(ModerationReport::query()->where('source', 'activity')->count())->toBe(1);
});

test('report directory supports workflow priority target and assignment filters with pagination', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $report = m4UserReport($reporter, $target, 'spam');
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);
    $report->forceFill(['status' => 'triaged', 'priority' => 'high', 'assigned_admin_id' => $admin->id])->save();

    $this->withHeaders($headers)->getJson('/api/admin/v1/reports?status=triaged&priority=high&target_type=user&assigned_admin_id='.$admin->id.'&per_page=1')
        ->assertOk()->assertJsonPath('data.data.0.id', $report->id)->assertJsonPath('data.meta.per_page', 1);
});

test('report assignment only accepts active administrators with review permission', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $report = m4UserReport($reporter, $target);
    $moderator = m4Admin();
    $support = m4Admin('support-agent');
    $headers = m4AdminHeaders($moderator);

    $this->withHeaders($headers)->patchJson('/api/admin/v1/reports/'.$report->id.'/assignment', [
        'assigned_admin_id' => $support->id, 'reason' => 'Assign for review',
    ])->assertStatus(422)->assertJsonPath('code', 'REPORT_ASSIGNEE_INVALID');

    $this->withHeaders($headers)->patchJson('/api/admin/v1/reports/'.$report->id.'/assignment', [
        'assigned_admin_id' => $moderator->id, 'reason' => 'Assign for review',
    ])->assertOk()->assertJsonPath('data.assigned_admin_id', $moderator->id);
});

test('closed moderation reports cannot be silently reopened through invalid workflow transitions', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $report = m4UserReport($reporter, $target);
    $report->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->patchJson('/api/admin/v1/reports/'.$report->id, [
        'status' => 'under_review', 'reason' => 'Try reopen',
    ])->assertStatus(409)->assertJsonPath('code', 'REPORT_TRANSITION_INVALID');
});

test('workflow changes record timestamps rationale and immutable administrator audit history', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $report = m4UserReport($reporter, $target);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->patchJson('/api/admin/v1/reports/'.$report->id, [
        'status' => 'triaged', 'priority' => 'high', 'risk_score' => 72, 'reason' => 'Credible safety concern',
    ])->assertOk()->assertJsonPath('data.status', 'triaged');

    $fresh = $report->refresh();
    expect($fresh->triaged_at)->not->toBeNull()
        ->and(AdminAuditLog::query()->where('action', 'admin.moderation.report.workflow.updated')->where('target_id', $report->id)->exists())->toBeTrue();
});

test('moderation case detail includes prior reports for the same target without unrelated records', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $other = User::factory()->create();
    $circle = m4Circle($target, $reporter);
    CircleMember::query()->create([
        'circle_id' => $circle->id, 'user_id' => $other->id, 'role' => CircleRole::Member, 'location_mode' => LocationMode::Hidden, 'joined_at' => now(),
    ]);
    $older = app(ModerationIntakeService::class)->create($reporter, ['target_type' => 'user', 'target_id' => (string) $target->id, 'reason' => 'spam']);
    $current = app(ModerationIntakeService::class)->create($other, ['target_type' => 'user', 'target_id' => (string) $target->id, 'reason' => 'harassment']);
    m4UserReport(User::factory()->create(), User::factory()->create(), 'spam');

    $admin = m4Admin();
    $this->withHeaders(m4AdminHeaders($admin))->getJson('/api/admin/v1/reports/'.$current->id)
        ->assertOk()->assertJsonPath('data.prior_reports.0.id', $older->id)->assertJsonCount(1, 'data.prior_reports');
});

test('internal moderation notes are private case records and audited', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $report = m4UserReport($reporter, $target);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/reports/'.$report->id.'/notes', [
        'note' => 'Internal moderator note only.',
    ])->assertCreated();

    $this->assertDatabaseHas('moderation_case_notes', ['report_id' => $report->id, 'note' => 'Internal moderator note only.']);
    expect(AdminAuditLog::query()->where('action', 'admin.moderation.report.note.created')->exists())->toBeTrue();
});

test('high risk moderation enforcement requires recent administrator reauthentication', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $report = m4UserReport($reporter, $target);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin, false);

    $this->withHeaders($headers)->postJson('/api/admin/v1/reports/'.$report->id.'/enforcements', [
        'action' => 'warn_user', 'warning' => 'Formal warning', 'reason' => 'Policy breach',
    ])->assertStatus(428)->assertJsonPath('code', 'ADMIN_REAUTH_REQUIRED');
});

test('moderation warning enforcement reuses the real consumer user control system', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $report = m4UserReport($reporter, $target);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/reports/'.$report->id.'/enforcements', [
        'action' => 'warn_user', 'warning' => 'Stop abusive behavior.', 'reason' => 'Confirmed harassment',
    ])->assertCreated();

    expect(AdminUserControl::query()->where('user_id', $target->id)->value('warning'))->toBe('Stop abusive behavior.')
        ->and($report->refresh()->status)->toBe('actioned');
});

test('moderation feature restriction enforcement persists through Milestone 2 controls', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $report = m4UserReport($reporter, $target);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/reports/'.$report->id.'/enforcements', [
        'action' => 'restrict_user_feature', 'feature' => 'messaging', 'reason' => 'Message abuse confirmed',
    ])->assertCreated();

    expect(AdminUserControl::query()->where('user_id', $target->id)->firstOrFail()->feature_restrictions)->toContain('messaging');
});

test('temporary suspension enforcement actually blocks consumer API access', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $report = m4UserReport($reporter, $target);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/reports/'.$report->id.'/enforcements', [
        'action' => 'suspend_user_temp', 'duration_minutes' => 60, 'reason' => 'Serious abuse',
    ])->assertCreated();

    $this->withHeaders(m4ConsumerHeaders($target))->getJson('/api/v1/auth/me')->assertForbidden();
});

test('circle freeze enforcement uses existing circle controls rather than parallel moderation state', function (): void {
    $owner = User::factory()->create();
    $reporter = User::factory()->create();
    $circle = m4Circle($owner, $reporter);
    $report = app(ModerationIntakeService::class)->create($reporter, ['target_type' => 'circle', 'target_id' => $circle->id, 'reason' => 'abuse']);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/reports/'.$report->id.'/enforcements', [
        'action' => 'freeze_circle', 'reason' => 'Coordinated abusive activity',
    ])->assertCreated();

    expect(AdminCircleControl::query()->where('circle_id', $circle->id)->value('status'))->toBe('frozen');
});

test('suspended users can obtain a short lived appeal only token by email OTP', function (): void {
    Mail::fake();
    $this->mock(EmailOtpGenerator::class)
        ->shouldReceive('generate')
        ->once()
        ->andReturn('123456');

    $reporter = User::factory()->create();
    $target = User::factory()->create(['email' => 'appeal-suspended@example.test']);
    $report = m4UserReport($reporter, $target);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $enforcementId = $this->withHeaders($headers)->postJson('/api/admin/v1/reports/'.$report->id.'/enforcements', [
        'action' => 'suspend_user_indefinite',
        'reason' => 'Suspension pending appeal',
    ])->assertCreated()->json('data.id');

    // withHeaders() persists default headers on Laravel's feature-test client.
    // Clear the administrator bearer credential before exercising the public
    // appeal OTP endpoints; otherwise consumer token isolation correctly
    // rejects the leaked admin token with 401.
    $this->withHeaders(['Authorization' => '']);

    $this->postJson('/api/v1/appeals/auth/email-otp/request', [
        'email' => $target->email,
        'enforcement_id' => $enforcementId,
    ])->assertAccepted();

    Mail::assertSent(EmailOtpMail::class, fn (EmailOtpMail $mail): bool => $mail->hasTo($target->email));

    $token = $this->postJson('/api/v1/appeals/auth/email-otp/verify', [
        'email' => $target->email,
        'otp' => '123456',
        'enforcement_id' => $enforcementId,
    ])->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->json('data.access_token');

    $appealHeaders = ['Authorization' => 'Bearer '.$token];

    $this->withHeaders($appealHeaders)->getJson('/api/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('code', 'TOKEN_SCOPE_RESTRICTED');

    $this->withHeaders($appealHeaders)->postJson('/api/v1/reports', [])
        ->assertForbidden()
        ->assertJsonPath('code', 'TOKEN_SCOPE_RESTRICTED');

    $this->withHeaders($appealHeaders)->postJson('/api/v1/appeals', [
        'enforcement_id' => $enforcementId,
        'explanation' => 'Please review this suspension because I want to appeal it.',
    ])->assertStatus(202);
});

test('appeal OTP request does not enumerate users or enforcement identifiers', function (): void {
    Mail::fake();

    $this->postJson('/api/v1/appeals/auth/email-otp/request', [
        'email' => 'unknown@example.test',
        'enforcement_id' => (string) Str::uuid(),
    ])->assertAccepted()
        ->assertJsonPath('success', true);

    Mail::assertNothingSent();
});

test('consumer appeals are limited to enforcement applied to that user', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();
    $enforcement = ModerationEnforcement::query()->create([
        'target_type' => 'user', 'target_id' => (string) $one->id, 'action' => 'warn_user', 'reason' => 'test', 'status' => 'applied', 'applied_at' => now(),
    ]);

    $this->withHeaders(m4ConsumerHeaders($two))->postJson('/api/v1/appeals', [
        'enforcement_id' => $enforcement->id, 'explanation' => 'I want to appeal this enforcement decision.',
    ])->assertNotFound()->assertJsonPath('code', 'APPEAL_ENFORCEMENT_UNAVAILABLE');
});

test('consumer appeal submission is idempotent per enforcement', function (): void {
    $user = User::factory()->create();
    $enforcement = ModerationEnforcement::query()->create([
        'target_type' => 'user', 'target_id' => (string) $user->id, 'action' => 'warn_user', 'reason' => 'test', 'status' => 'applied', 'applied_at' => now(),
    ]);
    $payload = ['enforcement_id' => $enforcement->id, 'explanation' => 'Please review this enforcement because I disagree with it.'];

    $this->withHeaders(m4ConsumerHeaders($user))->postJson('/api/v1/appeals', $payload)->assertStatus(202);
    $this->withHeaders(m4ConsumerHeaders($user))->postJson('/api/v1/appeals', $payload)->assertStatus(202);

    expect(ModerationAppeal::query()->count())->toBe(1);
});

test('overturned suspension appeal restores actual consumer access and reverses enforcement', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    $report = m4UserReport($reporter, $target);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);
    $enforcementId = $this->withHeaders($headers)->postJson('/api/admin/v1/reports/'.$report->id.'/enforcements', [
        'action' => 'suspend_user_indefinite', 'reason' => 'Initial moderation decision',
    ])->assertCreated()->json('data.id');

    $appealId = $this->withHeaders(m4ConsumerHeaders($target))->postJson('/api/v1/appeals', [
        'enforcement_id' => $enforcementId, 'explanation' => 'Please review the full context of this enforcement.',
    ])->assertStatus(202)->json('data.id');

    $this->withHeaders($headers)->postJson('/api/admin/v1/appeals/'.$appealId.'/review', [
        'outcome' => 'overturned', 'decision_reason' => 'New evidence changes the decision.',
    ])->assertOk()->assertJsonPath('data.status', 'second_review');

    expect(AdminUserControl::query()->where('user_id', $target->id)->value('status'))->toBe('suspended');

    $secondAdmin = m4Admin();
    $this->withHeaders(m4AdminHeaders($secondAdmin))->postJson('/api/admin/v1/appeals/'.$appealId.'/second-review', [
        'approved' => true, 'reason' => 'Independent review confirms the proposed reversal.',
    ])->assertOk()->assertJsonPath('data.outcome', 'overturned')->assertJsonPath('data.status', 'decided');

    expect(AdminUserControl::query()->where('user_id', $target->id)->value('status'))->toBe('active')
        ->and(ModerationEnforcement::query()->findOrFail($enforcementId)->status)->toBe('reversed');
});

test('appeal assignment requires an active administrator with appeal review permission', function (): void {
    $user = User::factory()->create();
    $enforcement = ModerationEnforcement::query()->create([
        'target_type' => 'user', 'target_id' => (string) $user->id, 'action' => 'warn_user', 'reason' => 'test', 'status' => 'applied', 'applied_at' => now(),
    ]);
    $appeal = ModerationAppeal::query()->create([
        'enforcement_id' => $enforcement->id, 'user_id' => $user->id, 'explanation' => 'Please review this enforcement.',
        'status' => 'submitted', 'submitted_at' => now(),
    ]);
    $moderator = m4Admin();
    $support = m4Admin('support-agent');
    $headers = m4AdminHeaders($moderator);

    $this->withHeaders($headers)->patchJson('/api/admin/v1/appeals/'.$appeal->id.'/assignment', [
        'assigned_admin_id' => $support->id, 'reason' => 'Attempt invalid assignment',
    ])->assertStatus(422)->assertJsonPath('code', 'APPEAL_ASSIGNEE_INVALID');

    $this->withHeaders($headers)->patchJson('/api/admin/v1/appeals/'.$appeal->id.'/assignment', [
        'assigned_admin_id' => $moderator->id, 'reason' => 'Assign appeal review',
    ])->assertOk()->assertJsonPath('data.assigned_admin_id', $moderator->id)->assertJsonPath('data.status', 'under_review');
});

test('required appeal second review cannot be performed by the first reviewer', function (): void {
    $user = User::factory()->create();
    $enforcement = ModerationEnforcement::query()->create([
        'target_type' => 'user', 'target_id' => (string) $user->id, 'action' => 'suspend_user_indefinite', 'reason' => 'test', 'status' => 'applied', 'applied_at' => now(),
    ]);
    $appeal = ModerationAppeal::query()->create([
        'enforcement_id' => $enforcement->id, 'user_id' => $user->id, 'explanation' => 'Please review this suspension.',
        'status' => 'submitted', 'submitted_at' => now(),
    ]);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/appeals/'.$appeal->id.'/review', [
        'outcome' => 'upheld', 'decision_reason' => 'First review proposes upholding.',
    ])->assertOk()->assertJsonPath('data.status', 'second_review');

    $this->withHeaders($headers)->postJson('/api/admin/v1/appeals/'.$appeal->id.'/second-review', [
        'approved' => true, 'reason' => 'Attempt same reviewer approval',
    ])->assertStatus(409)->assertJsonPath('code', 'APPEAL_SECOND_REVIEW_SEPARATION_REQUIRED');
});

test('upheld appeal leaves existing enforcement intact', function (): void {
    $user = User::factory()->create();
    $enforcement = ModerationEnforcement::query()->create([
        'target_type' => 'user', 'target_id' => (string) $user->id, 'action' => 'warn_user', 'reason' => 'test', 'status' => 'applied', 'applied_at' => now(),
    ]);
    $appeal = ModerationAppeal::query()->create([
        'enforcement_id' => $enforcement->id, 'user_id' => $user->id, 'explanation' => 'Please review this decision.',
        'status' => 'submitted', 'submitted_at' => now(),
    ]);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/appeals/'.$appeal->id.'/review', [
        'outcome' => 'upheld', 'decision_reason' => 'The enforcement remains supported.',
    ])->assertOk();

    expect($enforcement->refresh()->status)->toBe('applied');
});

test('appeal decisions emit only safe user notification metadata', function (): void {
    $user = User::factory()->create();
    $enforcement = ModerationEnforcement::query()->create([
        'target_type' => 'user', 'target_id' => (string) $user->id, 'action' => 'warn_user', 'reason' => 'test', 'status' => 'applied', 'applied_at' => now(),
    ]);
    $appeal = ModerationAppeal::query()->create([
        'enforcement_id' => $enforcement->id, 'user_id' => $user->id, 'explanation' => 'Please review this decision.',
        'status' => 'submitted', 'submitted_at' => now(),
    ]);
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/appeals/'.$appeal->id.'/review', [
        'outcome' => 'upheld', 'decision_reason' => 'Policy decision upheld.',
    ])->assertOk();

    $notification = OrbitNotification::query()->where('user_id', $user->id)->where('kind', 'generic.appeal')->sole();
    expect($notification->payload)->toHaveKey('resource_id', (string) $appeal->id)
        ->and(json_encode($notification->payload, JSON_THROW_ON_ERROR))->not->toContain('Policy decision upheld.');
});

test('reports automatically build deterministic abuse risk profiles', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    m4UserReport($reporter, $target, 'threats');

    $profile = AdminRiskProfile::query()->findOrFail($target->id);
    expect($profile->score)->toBe(45)->and($profile->level)->toBe('elevated')
        ->and($profile->triggered_rules)->toContain('report_received');
});

test('SOS misuse reports create dedicated high severity risk signals', function (): void {
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    m4UserReport($reporter, $target, 'sos_misuse');

    $signal = AdminRiskSignal::query()->where('user_id', $target->id)->sole();
    expect($signal->type)->toBe('sos_misuse')->and($signal->severity)->toBe('high');
});

test('authorized risk operators can add sanitized manual signals', function (): void {
    $user = User::factory()->create();
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);

    $this->withHeaders($headers)->postJson('/api/admin/v1/risk/users/'.$user->id.'/signals', [
        'type' => 'auth_anomaly', 'severity' => 'high',
        'metadata' => ['safe' => 'visible', 'token' => 'must-not-persist', 'nested' => ['plaintext' => 'private', 'ok' => true]],
        'reason' => 'Repeated anomalous authentication',
    ])->assertCreated();

    $signal = AdminRiskSignal::query()->sole();
    expect($signal->metadata)->toHaveKey('safe', 'visible')->not->toHaveKey('token')
        ->and($signal->metadata['nested'])->toHaveKey('ok', true)->not->toHaveKey('plaintext');
});

test('resolving risk signals recalculates the current risk profile and audits the resolution', function (): void {
    $user = User::factory()->create();
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);
    $signal = app(AdminRiskService::class)->record($user->id, 'rate_limit_abuse', 'high', 'test', 'signal-1');

    $this->withHeaders($headers)->postJson('/api/admin/v1/risk/signals/'.$signal->id.'/resolve', [
        'reason' => 'Investigation completed',
    ])->assertOk();

    expect(AdminRiskProfile::query()->findOrFail($user->id)->score)->toBe(0)
        ->and(AdminAuditLog::query()->where('action', 'admin.risk.signal.resolved')->exists())->toBeTrue();
});

test('risk center is separately permissioned from report visibility', function (): void {
    $user = User::factory()->create();
    app(AdminRiskService::class)->record($user->id, 'other', 'low', 'test', 'risk-separate');
    $support = m4Admin('support-agent');
    $this->withHeaders(m4AdminHeaders($support))->getJson('/api/admin/v1/risk')->assertOk();

    $safety = m4Admin('safety-operator');
    $this->withHeaders(m4AdminHeaders($safety))->postJson('/api/admin/v1/risk/users/'.$user->id.'/signals', [
        'type' => 'other', 'severity' => 'low', 'reason' => 'Attempt management',
    ])->assertForbidden();
});

test('moderation realtime event contains only queue metadata and no evidence or encrypted content', function (): void {
    Event::fake([AdminModerationRealtimeBroadcast::class]);
    $reporter = User::factory()->create();
    $target = User::factory()->create();
    m4UserReport($reporter, $target, 'harassment', ['evidence_text' => 'PRIVATE REPORTER EVIDENCE']);

    Event::assertDispatched(AdminModerationRealtimeBroadcast::class, function ($event): bool {
        $encoded = json_encode($event->payload, JSON_THROW_ON_ERROR);

        return ! str_contains($encoded, 'PRIVATE REPORTER EVIDENCE')
            && ! str_contains($encoded, 'ciphertext')
            && isset($event->payload['report_id'],$event->payload['status'],$event->payload['target_type']);
    });
});

test('default moderator RBAC grants moderation actions without changing sensitive SOS permission separation', function (): void {
    app(AdminRbacService::class)->syncDefaults();
    $moderator = m4Admin('moderator');
    $super = m4Admin('super-administrator');

    expect($moderator->hasPermission('reports.enforce'))->toBeTrue()
        ->and($moderator->hasPermission('appeals.review'))->toBeTrue()
        ->and($moderator->hasPermission('appeals.second_review'))->toBeTrue()
        ->and($moderator->hasPermission('risk.manage'))->toBeTrue()
        ->and($super->hasPermission('reports.enforce'))->toBeTrue()
        ->and($super->hasPermission('sos.location.access'))->toBeFalse()
        ->and($super->hasPermission('sos.recordings.access'))->toBeFalse();
});

test('unknown moderation identifiers return not found without leaking other records', function (): void {
    $admin = m4Admin();
    $headers = m4AdminHeaders($admin);
    $this->withHeaders($headers)->getJson('/api/admin/v1/reports/'.Str::uuid())->assertNotFound()->assertJsonPath('code','REPORT_NOT_FOUND');
    $this->withHeaders($headers)->getJson('/api/admin/v1/appeals/'.Str::uuid())->assertNotFound()->assertJsonPath('code','APPEAL_NOT_FOUND');
});

test('moderation realtime authentication endpoint is protected by reports view permission', function (): void {
    $admin = m4Admin('finance-manager');
    $this->withHeaders(m4AdminHeaders($admin))->post('/api/admin/v1/moderation/realtime/auth')->assertForbidden();
});
