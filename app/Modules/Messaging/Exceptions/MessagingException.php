<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Exceptions;

use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class MessagingException extends RuntimeException
{
    /** @param array<string, mixed>|null $errors */
    private function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }

    public static function circleNotFound(): self
    {
        return new self('Circle not found.', 'MESSAGING_CIRCLE_NOT_FOUND', 404);
    }

    public static function messagingDisabled(): self
    {
        return new self('Messaging is disabled for this Circle member.', 'MESSAGING_DISABLED', 403);
    }

    public static function invalidSenderDevice(): self
    {
        return new self('The sender device is invalid or revoked.', 'MESSAGING_INVALID_SENDER_DEVICE', 422);
    }

    /** @param list<string> $expectedDeviceIds */
    public static function recipientDevicesChanged(array $expectedDeviceIds): self
    {
        return new self(
            'Recipient device keys changed. Refresh the Circle message devices and encrypt again.',
            'MESSAGING_RECIPIENT_DEVICES_CHANGED',
            409,
            ['expected_recipient_device_ids' => $expectedDeviceIds],
        );
    }

    public static function noRecipientDevices(): self
    {
        return new self(
            'No encryption-ready recipient devices are available in this Circle.',
            'MESSAGING_NO_RECIPIENT_DEVICES',
            409,
        );
    }

    public static function messageIdConflict(): self
    {
        return new self('This message ID is already owned by another message.', 'MESSAGING_MESSAGE_ID_CONFLICT', 409);
    }

    public static function envelopeIdConflict(): self
    {
        return new self('One or more envelope IDs have already been used.', 'MESSAGING_ENVELOPE_ID_CONFLICT', 409);
    }

    public static function invalidDevice(): self
    {
        return new self('The requested device is invalid or revoked.', 'MESSAGING_INVALID_DEVICE', 422);
    }

    public static function envelopeNotFound(): self
    {
        return new self('Message envelope not found.', 'MESSAGING_ENVELOPE_NOT_FOUND', 404);
    }

    public static function forbidden(): self
    {
        return new self('You do not have permission to perform this messaging action.', 'MESSAGING_FORBIDDEN', 403);
    }

    public static function messageNotFound(): self
    {
        return new self('Message not found.', 'MESSAGING_MESSAGE_NOT_FOUND', 404);
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error(
            message: $this->getMessage(),
            code: $this->errorCode,
            status: $this->status,
            errors: $this->errors,
        );
    }
}
