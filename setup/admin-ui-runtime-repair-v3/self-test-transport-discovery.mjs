import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
import { discoverCanonicalAdminTransport } from './transport-discovery.mjs';

function project(files) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'orbit-auth-discovery-'));
    const base = path.join(root, 'resources', 'js', 'admin-console');
    fs.mkdirSync(base, { recursive: true });
    for (const [name, content] of Object.entries(files)) {
        const file = path.join(base, name);
        fs.mkdirSync(path.dirname(file), { recursive: true });
        fs.writeFileSync(file, content, 'utf8');
    }
    return root;
}

{
    const root = project({
        'index.js': "import './foundation/auth.js';\nimport './theme.js';",
        'foundation/auth.js': "const KEY='orbit.console.v7';\nconst token=sessionStorage.getItem(KEY);\nfetch('/api/admin/v1/auth/me',{credentials:'same-origin',headers:{Authorization:`Bearer ${token}`}});",
        'theme.js': "localStorage.setItem('orbit.theme','dark');",
        'moderation-m4.js': "localStorage.getItem('wrong-admin-token');",
    });
    const result = discoverCanonicalAdminTransport(root);
    assert.equal(result.strategy, 'canonical-web-storage');
    assert.deepEqual(result.storageCandidates, [{ storage: 'sessionStorage', key: 'orbit.console.v7' }]);
    assert.ok(!result.storageCandidates.some((item) => item.key === 'wrong-admin-token'));
    fs.rmSync(root, { recursive: true, force: true });
}

{
    const root = project({
        'sos-m3.js': "import {sosConfig} from './sos-routes.generated.js';\nconst root=document.querySelector('[data-orbit-view^=\\\"sos-\\\"]');\nfetch('/api/admin/v1/sos',{credentials:'same-origin',headers:{Authorization:`Bearer ${sessionStorage.getItem(sosConfig.key)}`}});",
        'sos-routes.generated.js': 'export const sosConfig={"storageCandidates":[{"storage":"localStorage","key":"console.state"}]};',
    });
    const result = discoverCanonicalAdminTransport(root);
    assert.equal(result.strategy, 'canonical-web-storage');
    assert.deepEqual(result.storageCandidates, [{ storage: 'localStorage', key: 'console.state' }]);
    fs.rmSync(root, { recursive: true, force: true });
}

{
    const root = project({
        'auth-gate.js': "document.querySelector('[data-auth-gate]');fetch('/api/admin/v1/auth/me',{credentials:'same-origin'});",
    });
    const result = discoverCanonicalAdminTransport(root);
    assert.equal(result.strategy, 'canonical-cookie');
    assert.deepEqual(result.storageCandidates, []);
    fs.rmSync(root, { recursive: true, force: true });
}

console.log('Canonical administrator transport discovery self-test passed.');
