<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('uses one canonical administrator API transport for moderation and support', function (): void {
    $moderation = File::get(resource_path('js/admin-console/moderation-m4.js'));
    $support = File::get(resource_path('js/admin-console/support-m5.js'));
    $client = File::get(resource_path('js/admin-console/admin-api-client.js'));

    expect($moderation)
        ->toContain("import { adminApiRequest } from './admin-api-client.js';")
        ->not->toContain('function resolveToken(')
        ->not->toContain('sessionStorage.getItem(')
        ->not->toContain('localStorage.getItem(');

    expect($support)
        ->toContain("import { adminApiRequest } from './admin-api-client.js';")
        ->not->toContain('function resolveToken(')
        ->not->toContain('sessionStorage.getItem(')
        ->not->toContain('localStorage.getItem(');

    expect($client)
        ->toContain("import { adminAuthContract } from './admin-auth.generated.js';")
        ->toContain('export async function adminApiRequest(')
        ->toContain("credentials: 'same-origin'")
        ->toContain('headers.Authorization = `Bearer ${token}`');
});

it('ships non-empty live moderation and support route contracts', function (): void {
    $moderation = File::get(resource_path('js/admin-console/moderation-routes.generated.js'));
    $support = File::get(resource_path('js/admin-console/support-routes.generated.js'));
    $auth = File::get(resource_path('js/admin-console/admin-auth.generated.js'));

    foreach (['reportList', 'reportShow', 'appealList', 'appealShow', 'riskList', 'riskShow', 'reauthenticate'] as $key) {
        expect($moderation)->toContain('"'.$key.'"');
    }

    foreach (['ticketList', 'ticketShow'] as $key) {
        expect($support)->toContain('"'.$key.'"');
    }

    expect($moderation)->not->toContain('export const moderationRoutes = {};');
    expect($support)->not->toContain('export const supportRoutes = {};');
    expect($auth)->toContain('storageCandidates')->not->toContain('"storageCandidates":[]');
});
