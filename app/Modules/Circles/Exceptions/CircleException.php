<?php

declare(strict_types=1);

namespace App\Modules\Circles\Exceptions;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class CircleException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
    ) {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self('Circle not found.', 'CIRCLE_NOT_FOUND', 404);
    }

    public static function forbidden(): self
    {
        return new self('You do not have permission to perform this action.', 'CIRCLE_FORBIDDEN', 403);
    }

    public static function archived(): self
    {
        return new self('This Circle is archived and cannot be modified.', 'CIRCLE_ARCHIVED', 409);
    }

    public static function invalidInvite(): self
    {
        return new self('The invite code is invalid or expired.', 'INVALID_OR_EXPIRED_INVITE', 422);
    }

    public static function memberNotFound(): self
    {
        return new self('Circle member not found.', 'CIRCLE_MEMBER_NOT_FOUND', 404);
    }

    public static function ownerCannotLeave(): self
    {
        return new self('The Circle owner cannot leave until ownership is transferred.', 'OWNER_CANNOT_LEAVE', 409);
    }

    public static function ownerCannotBeRemoved(): self
    {
        return new self('The Circle owner cannot be removed.', 'OWNER_CANNOT_BE_REMOVED', 409);
    }

    public static function invalidRoleChange(): self
    {
        return new self('That Circle role change is not allowed.', 'INVALID_CIRCLE_ROLE_CHANGE', 422);
    }

    public static function privacyBelongsToMember(): self
    {
        return new self('Only the member can change their own privacy settings.', 'CIRCLE_PRIVACY_FORBIDDEN', 403);
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error(
            message: $this->getMessage(),
            code: $this->errorCode,
            status: $this->status,
        );
    }
}
