<?php

declare(strict_types=1);

use App\Models\CircleNotificationPreference;
use App\Models\Moment;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\OrbitNotification;
use App\Models\SosEvent;
use App\Models\SosNotificationOutbox;
use App\Models\User;
use App\Modules\Moments\Events\MomentPublished;
use App\Modules\Notifications\Actions\ImportSosNotificationOutboxAction;
use App\Modules\Notifications\Actions\RouteNotificationAction;
use App\Modules\Notifications\Enums\NotificationPriority;
use App\Modules\Notifications\Events\NotificationCreated;
use App\Modules\Notifications\Listeners\RecordEncryptedMessageNotification;
use App\Modules\Notifications\Listeners\RecordMomentPublishedNotification;
use App\Modules\Notifications\Listeners\RecordPingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function createNotificationCircle($test, User $owner): string
{
    Sanctum::actingAs($owner);
    $response = $test->postJson('/api/v1/circles', ['name' => 'Notifications Circle'])->assertCreated();
    $circleId = data_get($response->json(), 'data.id') ?? data_get($response->json(), 'data.circle.id') ?? data_get($response->json(), 'id');
    expect($circleId)->not->toBeNull();

    return (string) $circleId;
}

function insertNotificationMember(string $circleId, User $user): void
{
    $columns = array_flip(Schema::getColumnListing('circle_members'));
    $row = [
        'circle_id' => $circleId, 'user_id' => $user->getKey(), 'role' => 'member',
        'location_mode' => 'precise', 'location_fidelity' => 'precise', 'moment_access' => 'full',
        'can_view_moments' => true, 'ping_permission' => 'anyone', 'can_ping' => true,
        'message_permission' => 'full', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ];
    if (isset($columns['id']) && Schema::getColumnType('circle_members', 'id') !== 'integer') {
        $row['id'] = (string) Str::uuid7();
    }
    DB::table('circle_members')->insert(array_intersect_key($row, $columns));
}

function insertPushDevice(User $user, string $platform = 'ios', string $token = 'push-token'): string
{
    $columns = array_flip(Schema::getColumnListing('devices'));
    $id = (string) Str::uuid7();
    $row = [
        'id' => $id,
        'user_id' => $user->getKey(),
        'client_device_id' => 'client-'.$id,
        'platform' => $platform,
        'device_name' => 'Notification Test Device',
        'push_token' => $token,
        'public_identity_key' => 'public-key-'.$id,
        'public_key' => 'public-key-'.$id,
        'last_seen_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ];
    DB::table('devices')->insert(array_intersect_key($row, $columns));

    return $id;
}

test('it requires authentication for notification APIs', function (): void {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    $this->getJson('/api/v1/notifications/preferences')->assertUnauthorized();
});

test('it creates sensible default notification preferences', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $this->getJson('/api/v1/notifications/preferences')
        ->assertOk()
        ->assertJsonPath('data.push_enabled', true)
        ->assertJsonPath('data.messages_enabled', true)
        ->assertJsonPath('data.quiet_hours_enabled', false);
    expect(NotificationPreference::query()->where('user_id', $user->id)->count())->toBe(1);
});

test('it updates notification preferences', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $this->putJson('/api/v1/notifications/preferences', [
        'push_enabled' => false, 'moments_enabled' => false, 'quiet_hours_enabled' => true,
        'quiet_hours_start' => '22:00', 'quiet_hours_end' => '07:00', 'timezone' => 'Asia/Karachi',
    ])->assertOk()->assertJsonPath('data.push_enabled', false)->assertJsonPath('data.timezone', 'Asia/Karachi');
});

test('it lets a member mute a circle but hides non-member circles', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $circleId = createNotificationCircle($this, $owner);
    insertNotificationMember($circleId, $member);

    Sanctum::actingAs($member);
    $this->putJson('/api/v1/notifications/circles/'.$circleId, ['muted_until' => now()->addHour()->toIso8601String()])
        ->assertOk()->assertJsonPath('data.circle_id', $circleId);

    Sanctum::actingAs($outsider);
    $this->putJson('/api/v1/notifications/circles/'.$circleId, ['silent' => true])
        ->assertNotFound()->assertJsonPath('error.code', 'notification_circle_unavailable');
});

test('it routes safe in-app and per-device push records idempotently', function (): void {
    Event::fake([NotificationCreated::class]);
    $user = User::factory()->create();
    insertPushDevice($user, 'ios');
    insertPushDevice($user, 'android');

    $route = app(RouteNotificationAction::class);
    $route->handle($user->id, 'ping.received', 'ping:1:user:'.$user->id, [
        'ping_id' => '1', 'sender_user_id' => 99, 'ping_type' => 'hey', 'plaintext' => 'must not persist',
    ], NotificationPriority::High);
    $route->handle($user->id, 'ping.received', 'ping:1:user:'.$user->id, [
        'ping_id' => '1', 'sender_user_id' => 99, 'ping_type' => 'hey',
    ], NotificationPriority::High);

    expect(OrbitNotification::query()->count())->toBe(1)
        ->and(NotificationDelivery::query()->count())->toBe(2)
        ->and(OrbitNotification::query()->first()->payload)->not->toHaveKey('plaintext');
    Event::assertDispatchedTimes(NotificationCreated::class, 1);
});

test('message notifications preserve only encrypted preview metadata', function (): void {
    $user = User::factory()->create();
    $route = app(RouteNotificationAction::class);
    $notification = $route->handle($user->id, 'message.received', 'message:1:user:'.$user->id, [
        'message_id' => '1', 'circle_id' => null, 'sender_user_id' => 2,
        'encrypted_preview' => 'cipher-preview', 'content' => 'private text', 'plaintext' => 'private text',
    ]);
    expect($notification?->summary)->toBe('New message')
        ->and($notification?->payload)->toHaveKey('encrypted_preview', 'cipher-preview')
        ->and($notification?->payload)->not->toHaveKeys(['content', 'plaintext']);
});

test('muted circles produce silent push deliveries instead of suppressing them', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $circleId = createNotificationCircle($this, $owner);
    insertNotificationMember($circleId, $member);
    insertPushDevice($member, 'android');
    CircleNotificationPreference::query()->create(['user_id' => $member->id, 'circle_id' => $circleId, 'muted_until' => now()->addHour()]);

    app(RouteNotificationAction::class)->handle($member->id, 'message.received', 'muted-message', [
        'message_id' => 'm1', 'circle_id' => $circleId, 'sender_user_id' => $owner->id,
    ], circleId: $circleId);

    expect(NotificationDelivery::query()->sole()->silent)->toBeTrue();
});

test('disabled push suppresses device deliveries but retains enabled in-app inbox', function (): void {
    $user = User::factory()->create();
    insertPushDevice($user);
    NotificationPreference::query()->create(['user_id' => $user->id, 'push_enabled' => false, 'in_app_enabled' => true]);
    app(RouteNotificationAction::class)->handle($user->id, 'ping.received', 'push-disabled', ['ping_id' => 'p1'], NotificationPriority::High);
    expect(OrbitNotification::query()->count())->toBe(1)->and(NotificationDelivery::query()->count())->toBe(0);
});

test('disabled in-app preference keeps push routing records out of the inbox', function (): void {
    $user = User::factory()->create();
    insertPushDevice($user);
    NotificationPreference::query()->create(['user_id' => $user->id, 'push_enabled' => true, 'in_app_enabled' => false]);
    app(RouteNotificationAction::class)->handle($user->id, 'ping.received', 'in-app-disabled', ['ping_id' => 'p2'], NotificationPriority::High);
    expect(OrbitNotification::query()->count())->toBe(1)->and(NotificationDelivery::query()->count())->toBe(1);
    Sanctum::actingAs($user);
    $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
});

test('SOS bypasses ordinary notification disables and circle mute', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $circleId = createNotificationCircle($this, $owner);
    insertNotificationMember($circleId, $member);
    insertPushDevice($member, 'ios');
    NotificationPreference::query()->create(['user_id' => $member->id, 'push_enabled' => false, 'in_app_enabled' => false, 'pings_enabled' => false]);
    CircleNotificationPreference::query()->create(['user_id' => $member->id, 'circle_id' => $circleId, 'silent' => true]);

    $notification = app(RouteNotificationAction::class)->handle($member->id, 'sos.activated', 'sos-critical', [
        'sos_id' => 's1', 'circle_id' => $circleId, 'originator_user_id' => $owner->id,
        'latitude' => 31.52, 'longitude' => 74.35,
    ], NotificationPriority::Highest, $circleId);

    $delivery = NotificationDelivery::query()->sole();
    expect($notification?->priority)->toBe('highest')->and($delivery->silent)->toBeFalse()->and($delivery->provider)->toBe('apns')
        ->and(data_get($delivery->payload, 'apns.priority'))->toBe(10)
        ->and(data_get($delivery->payload, 'apns.interruption_level'))->toBe('time-sensitive');
});

test('notification inbox is private to the authenticated user', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();
    app(RouteNotificationAction::class)->handle($one->id, 'ping.received', 'one', ['ping_id' => '1']);
    app(RouteNotificationAction::class)->handle($two->id, 'ping.received', 'two', ['ping_id' => '2']);
    Sanctum::actingAs($one);
    $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.payload.ping_id', '1');
});

test('it marks one notification read idempotently and cannot read another users item', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();
    $notification = app(RouteNotificationAction::class)->handle($one->id, 'ping.received', 'read-one', ['ping_id' => '1']);
    Sanctum::actingAs($one);
    $this->postJson('/api/v1/notifications/'.$notification->id.'/read')->assertOk();
    $first = OrbitNotification::query()->findOrFail($notification->id)->read_at;
    $this->postJson('/api/v1/notifications/'.$notification->id.'/read')->assertOk();
    expect(OrbitNotification::query()->findOrFail($notification->id)->read_at?->toIso8601String())->toBe($first?->toIso8601String());
    Sanctum::actingAs($two);
    $this->postJson('/api/v1/notifications/'.$notification->id.'/read')->assertNotFound();
});

test('it marks all of the authenticated users notifications read', function (): void {
    $user = User::factory()->create();
    app(RouteNotificationAction::class)->handle($user->id, 'ping.received', 'all-1', ['ping_id' => '1']);
    app(RouteNotificationAction::class)->handle($user->id, 'ping.received', 'all-2', ['ping_id' => '2']);
    Sanctum::actingAs($user);
    $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.updated', 2);
    expect(OrbitNotification::query()->whereNull('read_at')->count())->toBe(0);
});

test('it converts a Moment publication to one safe notification per eligible other member', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $circleId = createNotificationCircle($this, $owner);
    insertNotificationMember($circleId, $member);
    $moment = new Moment;
    $moment->forceFill(['id' => 'moment-n1', 'circle_id' => $circleId, 'author_user_id' => $owner->id, 'media_type' => 'image', 'ciphertext' => 'private']);
    app(RecordMomentPublishedNotification::class)->handle(new MomentPublished($moment));
    $notification = OrbitNotification::query()->sole();
    expect($notification->user_id)->toBe($member->id)->and($notification->payload)->toHaveKey('moment_id', 'moment-n1')->and($notification->payload)->not->toHaveKey('ciphertext');
});

test('generic Ping event adapter routes a high priority notification', function (): void {
    $recipient = User::factory()->create();
    $event = new class($recipient->id)
    {
        public array $realtime;

        public function __construct(int $id)
        {
            $this->realtime = ['payload' => ['ping_id' => 'p9', 'recipient_user_id' => $id, 'sender_user_id' => 8]];
        }
    };
    app(RecordPingNotification::class)->handle($event);
    expect(OrbitNotification::query()->sole()->priority)->toBe('high');
});

test('generic encrypted message adapter never accepts plaintext content', function (): void {
    $recipient = User::factory()->create();
    $event = new class($recipient->id)
    {
        public array $realtime;

        public function __construct(int $id)
        {
            $this->realtime = ['payload' => ['message_id' => 'm9', 'recipient_user_id' => $id, 'sender_user_id' => 7, 'encrypted_preview' => 'encrypted', 'content' => 'secret']];
        }
    };
    app(RecordEncryptedMessageNotification::class)->handle($event);
    $notification = OrbitNotification::query()->sole();
    expect($notification->payload)->toHaveKey('encrypted_preview', 'encrypted')->and($notification->payload)->not->toHaveKey('content');
});

test('it imports pending SOS push outbox rows idempotently into highest priority notifications', function (): void {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $circleId = createNotificationCircle($this, $owner);
    insertNotificationMember($circleId, $member);
    insertPushDevice($member, 'android');
    $sos = SosEvent::query()->create([
        'id' => (string) Str::uuid7(), 'user_id' => $owner->id, 'circle_id' => $circleId,
        'status' => 'active', 'escalation_stage' => 0, 'activated_at' => now(),
    ]);
    $row = SosNotificationOutbox::query()->create([
        'sos_event_id' => $sos->id, 'target_user_id' => $member->id, 'channel' => 'push', 'kind' => 'sos.activated',
        'priority' => 'highest', 'payload' => ['sos_id' => $sos->id, 'circle_id' => $circleId, 'originator_user_id' => $owner->id, 'deep_link' => 'orbit://sos/'.$sos->id],
        'status' => 'pending', 'available_at' => now(), 'attempts' => 0,
    ]);
    $importer = app(ImportSosNotificationOutboxAction::class);
    expect($importer->handle())->toBe(1)->and($importer->handle())->toBe(0);
    expect(OrbitNotification::query()->count())->toBe(1)->and(NotificationDelivery::query()->sole()->priority)->toBe('highest')->and($row->refresh()->status)->toBe('accepted');
});
