<?php

declare(strict_types=1);

namespace App\Modules\Activity\Http\Middleware;

use App\Models\User;
use App\Modules\Activity\Actions\RecordActivityEventAction;
use App\Modules\Activity\Enums\ActivityEventType;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final readonly class TrackCircleMembershipChanges
{
    public function __construct(private RecordActivityEventAction $record) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $before = $this->snapshot($user, $this->routeCircleId($request));
        $response = $next($request);

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $after = $this->snapshot($user, $this->routeCircleId($request));

        foreach (array_diff_key($after, $before) as $membership) {
            $this->recordMembership(ActivityEventType::MemberJoined, $membership);
        }

        foreach (array_diff_key($before, $after) as $membership) {
            $this->recordMembership(ActivityEventType::MemberLeft, $membership);
        }

        return $response;
    }

    private function snapshot(User $user, ?string $routeCircleId): array
    {
        $rows = DB::table('circle_members')
            ->where('user_id', $user->getKey())
            ->get();

        if ($routeCircleId !== null) {
            $scopedRows = DB::table('circle_members')
                ->where('circle_id', $routeCircleId)
                ->get();

            $rows = $rows->concat($scopedRows);
        }

        return $rows
            ->unique(fn (object $row): string => $row->circle_id.':'.$row->user_id)
            ->mapWithKeys(function (object $row): array {
                $circleId = (string) $row->circle_id;
                $userId = (int) $row->user_id;
                $token = $row->id
                    ?? $row->joined_at
                    ?? $row->created_at
                    ?? $circleId.':'.$userId;

                return [
                    $circleId.':'.$userId => [
                        'circle_id' => $circleId,
                        'user_id' => $userId,
                        'token' => (string) $token,
                    ],
                ];
            })
            ->all();
    }

    private function routeCircleId(Request $request): ?string
    {
        foreach (['circleId', 'circle_id', 'circle'] as $parameter) {
            $value = $request->route($parameter);

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }

            if (is_object($value) && method_exists($value, 'getKey')) {
                return (string) $value->getKey();
            }
        }

        return null;
    }

    private function recordMembership(ActivityEventType $type, array $membership): void
    {
        $verb = $type === ActivityEventType::MemberJoined ? 'joined' : 'left';
        $token = hash('sha256', $membership['token']);

        $this->record->handle(
            $type,
            $membership['circle_id'],
            $membership['user_id'],
            'circle_membership',
            $token,
            "member.{$verb}:{$membership['circle_id']}:{$membership['user_id']}:{$token}",
            [
                'circle_id' => $membership['circle_id'],
                'member_user_id' => $membership['user_id'],
            ],
        );
    }
}
