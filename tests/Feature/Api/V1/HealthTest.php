<?php

declare(strict_types=1);

it('returns the Orbit API health response', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Orbit API is healthy.')
        ->assertJsonPath('data.service', 'orbit-api')
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.api_version', 'v1');
});
