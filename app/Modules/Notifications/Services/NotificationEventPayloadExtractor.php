<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

final class NotificationEventPayloadExtractor
{
    /** @return list<array<string, mixed>> */
    public function payloads(object $event): array
    {
        $roots = [];

        foreach (['realtime', 'broadcasts', 'payload', 'data'] as $property) {
            if (property_exists($event, $property) && is_array($event->{$property})) {
                $roots[] = $event->{$property};
            }
        }

        $payloads = [];
        foreach ($roots as $root) {
            $this->collect($root, $payloads);
        }

        return $payloads === [] ? [[]] : $payloads;
    }

    public function payload(object $event): array
    {
        return $this->payloads($event)[0] ?? [];
    }

    public function first(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null) {
                return $payload[$key];
            }
        }

        return null;
    }

    /** @param list<array<string, mixed>> $payloads */
    private function collect(array $value, array &$payloads): void
    {
        $candidate = is_array($value['payload'] ?? null) ? $value['payload'] : $value;
        $keys = array_keys($candidate);

        if (array_intersect($keys, ['message_id', 'ping_id', 'sos_id', 'circle_id', 'recipient_user_id', 'target_user_id']) !== []) {
            $payloads[] = $candidate;
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                $this->collect($child, $payloads);
            }
        }
    }
}
