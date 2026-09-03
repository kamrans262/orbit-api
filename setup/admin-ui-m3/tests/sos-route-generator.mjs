import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const generator = path.resolve(here, '..', 'generate-sos-contract.mjs');
const root = fs.mkdtempSync(path.join(os.tmpdir(), 'orbit-m3-route-contract-'));

try {
    const testPath = path.join(root, 'tests', 'Feature', 'Api', 'Admin', 'V1', 'AdminSosCommandCenterTest.php');
    fs.mkdirSync(path.dirname(testPath), {recursive: true});
    fs.writeFileSync(testPath, `<?php\n\nit('operational closure requires a resolution and does not silently resolve the consumer SOS event', function (): void {\n    $this->putJson('/api/admin/v1/sos/'.$sosId.'/classification', [\n        'operational_status' => 'closed',\n        'resolution' => 'Operator verified the incident is complete.',\n        'reason' => 'Close command-center workflow after review.',\n    ])->assertOk();\n});\n`, 'utf8');

    const routes = [
        {method: 'GET|HEAD', uri: 'api/admin/v1/sos', name: 'api.admin.v1.sos.index', action: 'App\\Modules\\Admin\\Safety\\Http\\Controllers\\ListAdminSosIncidentsController'},
        {method: 'GET|HEAD', uri: 'api/admin/v1/sos/{sosId}', name: 'api.admin.v1.sos.show', action: 'App\\Modules\\Admin\\Safety\\Http\\Controllers\\ShowAdminSosIncidentController'},
        {method: 'PATCH', uri: 'api/admin/v1/sos/{sosId}/assignment', name: 'api.admin.v1.sos.assignment.update', action: 'App\\Modules\\Admin\\Safety\\Http\\Controllers\\UpdateAdminSosAssignmentController'},
        {method: 'PUT', uri: 'api/admin/v1/sos/{sosId}/classification', name: 'api.admin.v1.sos.classification.update', action: 'App\\Modules\\Admin\\Safety\\Http\\Controllers\\UpdateAdminSosClassificationController'},
        {method: 'POST', uri: 'api/admin/v1/sos/{sosId}/exports', name: 'api.admin.v1.sos.exports.store', action: 'App\\Modules\\Admin\\Safety\\Http\\Controllers\\CreateAdminSosExportController'},
        {method: 'POST', uri: 'api/admin/v1/sos/{sosId}/notes', name: 'api.admin.v1.sos.notes.store', action: 'App\\Modules\\Admin\\Safety\\Http\\Controllers\\AddAdminSosNoteController'},
        {method: 'GET|HEAD', uri: 'api/admin/v1/sos/{sosId}/sensitive-access', name: 'api.admin.v1.sos.sensitive-access.index', action: 'App\\Modules\\Admin\\Safety\\Http\\Controllers\\ListAdminSosSensitiveAccessController'},
        {method: 'POST', uri: 'api/admin/v1/sos/{sosId}/sensitive/location', name: 'api.admin.v1.sos.sensitive.location', action: 'App\\Modules\\Admin\\Safety\\Http\\Controllers\\AccessAdminSosLocationController'},
        {method: 'POST', uri: 'api/admin/v1/sos/{sosId}/sensitive/recording', name: 'api.admin.v1.sos.sensitive.recording', action: 'App\\Modules\\Admin\\Safety\\Http\\Controllers\\AccessAdminSosRecordingController'},
    ];

    const output = path.join(root, 'sos-contract.js');
    const run = spawnSync(process.execPath, [generator, output, root], {input: JSON.stringify(routes), encoding: 'utf8'});
    assert.equal(run.status, 0, run.stderr || run.stdout);
    const contract = fs.readFileSync(output, 'utf8');

    assert.match(contract, /assignment: \{method: "PATCH", uri: "\/api\/admin\/v1\/sos\/\{sosId\}\/assignment"/);
    assert.match(contract, /classification: \{method: "PUT", uri: "\/api\/admin\/v1\/sos\/\{sosId\}\/classification"/);
    assert.match(contract, /closure: \{method: "PUT", uri: "\/api\/admin\/v1\/sos\/\{sosId\}\/classification"/);
    assert.match(contract, /fields: \{"status":"operational_status","resolution":"resolution","reason":"reason"\}/);
    assert.match(contract, /binding: "backend-test"/);
    assert.match(contract, /accessHistory: \{method: "GET", uri: "\/api\/admin\/v1\/sos\/\{sosId\}\/sensitive-access"/);
    assert.match(contract, /controls: null/);
    assert.doesNotMatch(contract, /\/controls"/);

    // Regression: the word "Controller" in action class names must never be
    // interpreted as a generic controls route.
    const incomplete = routes.filter((route) => !route.uri.endsWith('/classification'));
    const negativeOutput = path.join(root, 'negative.js');
    const negative = spawnSync(process.execPath, [generator, negativeOutput, root], {input: JSON.stringify(incomplete), encoding: 'utf8'});
    assert.notEqual(negative.status, 0, 'Incomplete route inventory should be rejected.');
    const error = `${negative.stderr}\n${negative.stdout}`;
    assert.match(error, /classification mutation/);
    assert.match(error, /operational closure mutation with proven backend semantics/);

    console.log('Milestone 3 SOS route-contract resolver self-test passed.');
} finally {
    fs.rmSync(root, {recursive: true, force: true});
}
