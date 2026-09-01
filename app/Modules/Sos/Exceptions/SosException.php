<?php

declare(strict_types=1);

namespace App\Modules\Sos\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class SosException extends HttpException
{
    public function __construct(
        int $statusCode,
        string $message,
        public readonly string $errorCode,
        public readonly array $context = [],
    ) {
        parent::__construct($statusCode, $message);
    }

    public static function circleUnavailable(): self
    {
        return new self(404, 'The SOS circle is unavailable.', 'sos_circle_unavailable');
    }

    public static function eventUnavailable(): self
    {
        return new self(404, 'The SOS event is unavailable.', 'sos_event_unavailable');
    }

    public static function idConflict(): self
    {
        return new self(409, 'The SOS event ID is already in use.', 'sos_id_conflict');
    }

    public static function tooManyActivations(): self
    {
        return new self(
            429,
            'Too many SOS activations were attempted in the last 60 minutes.',
            'sos_activation_rate_limited',
            ['limit' => 3, 'window_minutes' => 60, 'assistance_confirmation_required' => true],
        );
    }

    public static function notActive(): self
    {
        return new self(409, 'The SOS event is no longer active.', 'sos_not_active');
    }

    public static function originatorCannotRespond(): self
    {
        return new self(422, 'The SOS originator cannot respond to their own SOS.', 'sos_originator_cannot_respond');
    }

    public static function locationForbidden(): self
    {
        return new self(403, 'You are not allowed to publish location for this SOS.', 'sos_location_forbidden');
    }

    public static function resolveForbidden(): self
    {
        return new self(403, 'Only the SOS originator can resolve this SOS.', 'sos_resolve_forbidden');
    }

    public static function recordingForbidden(): self
    {
        return new self(403, 'Only the SOS originator can attach its encrypted recording.', 'sos_recording_forbidden');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => [
                'code' => $this->errorCode,
                'context' => $this->context,
            ],
        ], $this->getStatusCode());
    }
}
