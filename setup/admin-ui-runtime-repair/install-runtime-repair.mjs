import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(scriptDir, '..', '..');
process.chdir(root);

{
    const selfTest = spawnSync(process.execPath, [path.join(scriptDir, 'self-test-command-runner.mjs')], { cwd: root, stdio: 'inherit' });
    if (selfTest.error) throw selfTest.error;
    if (selfTest.status !== 0) throw new Error(`Runtime repair command-runner self-test failed with exit code ${selfTest.status}.`);
}

const rel = (...parts) => path.join(...parts);
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const exists = (relative) => fs.existsSync(path.join(root, relative));
const write = (relative, content) => {
    const file = path.join(root, relative);
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, content.replace(/\r\n/g, '\n'), 'utf8');
};
const sha256 = (file) => crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');

function commandSpec(name, args, platform = process.platform, env = process.env) {
    if (platform === 'win32' && name === 'npm') {
        const comspec = env.ComSpec || env.COMSPEC || 'cmd.exe';
        return {
            file: comspec,
            args: ['/d', '/s', '/c', ['npm', ...args].join(' ')],
        };
    }

    return { file: name, args };
}

function run(name, args, { allowFailure = false, capture = false } = {}) {
    const display = `${name} ${args.join(' ')}`;
    if (!capture) console.log(`\n== ${display} ==`);
    const command = commandSpec(name, args);
    const result = spawnSync(command.file, command.args, {
        cwd: root,
        encoding: 'utf8',
        stdio: capture ? ['ignore', 'pipe', 'pipe'] : 'inherit',
        shell: false,
    });
    if (result.error) throw result.error;
    if (result.status !== 0 && !allowFailure) {
        if (capture) {
            if (result.stdout) process.stdout.write(result.stdout);
            if (result.stderr) process.stderr.write(result.stderr);
        }
        throw new Error(`${display} failed with exit code ${result.status}.`);
    }
    return result;
}

function routeInventory() {
    const result = run('php', ['artisan', 'route:list', '--json'], { capture: true });
    const output = String(result.stdout || '').trim();
    const start = output.indexOf('[');
    const end = output.lastIndexOf(']');
    if (start < 0 || end < start) throw new Error('Laravel route inventory did not contain a JSON array.');
    const routes = JSON.parse(output.slice(start, end + 1));
    if (!Array.isArray(routes)) throw new Error('Laravel route inventory is not an array.');
    return routes;
}

function methods(route) {
    return String(route.method || '').split('|').filter((method) => method && method !== 'HEAD');
}

function primaryMethod(route) {
    return methods(route)[0] || 'GET';
}

function cleanUri(route) {
    return String(route.uri || '').replace(/^\//, '');
}

function routeByController(routes, suffix) {
    const route = routes.find((item) => String(item.action || '').includes(suffix));
    if (!route) throw new Error(`Required moderation backend route is missing: ${suffix}`);
    return route;
}

function normalizeModerationUri(route, key) {
    let uri = cleanUri(route);
    const placeholders = [...uri.matchAll(/\{[^}]+\}/g)].map((match) => match[0]);
    if (placeholders.length === 0) return `/${uri}`;

    if (key.startsWith('report')) {
        uri = uri.replace(placeholders[0], '{reportId}');
    } else if (key.startsWith('appeal')) {
        uri = uri.replace(placeholders[0], '{appealId}');
    } else if (key === 'riskResolve') {
        if (placeholders.length > 1) {
            uri = uri.replace(placeholders[0], '{profileId}');
            const remaining = [...uri.matchAll(/\{[^}]+\}/g)].map((match) => match[0]);
            const signalPlaceholder = remaining.find((value) => value !== '{profileId}') || remaining.at(-1);
            if (signalPlaceholder) uri = uri.replace(signalPlaceholder, '{signalId}');
        } else {
            uri = uri.replace(placeholders[0], '{signalId}');
        }
    } else if (key.startsWith('risk')) {
        uri = uri.replace(placeholders[0], '{profileId}');
    }

    return `/${uri}`;
}

function moderationContract(routes) {
    const mapping = {
        reportList: 'ListModerationReportsController',
        reportShow: 'ShowModerationReportController',
        reportAssign: 'AssignModerationReportController',
        reportWorkflow: 'UpdateModerationReportWorkflowController',
        reportNote: 'AddModerationCaseNoteController',
        reportEnforce: 'ApplyModerationEnforcementController',
        appealList: 'ListModerationAppealsController',
        appealShow: 'ShowModerationAppealController',
        appealAssign: 'AssignModerationAppealController',
        appealReview: 'ReviewModerationAppealController',
        appealSecondReview: 'SecondReviewModerationAppealController',
        riskList: 'ListRiskProfilesController',
        riskShow: 'ShowRiskProfileController',
        riskCreate: 'CreateRiskSignalController',
        riskResolve: 'ResolveRiskSignalController',
    };
    const contract = {};
    for (const [key, controller] of Object.entries(mapping)) {
        const route = routeByController(routes, controller);
        contract[key] = { method: primaryMethod(route), uri: normalizeModerationUri(route, key) };
    }
    const reauth = routes.find((route) => cleanUri(route) === 'api/admin/v1/auth/reauthenticate'
        || String(route.name || '') === 'api.admin.v1.auth.reauthenticate');
    if (!reauth) throw new Error('Administrator reauthentication route is missing.');
    contract.reauthenticate = { method: primaryMethod(reauth), uri: `/${cleanUri(reauth)}` };
    return contract;
}

function supportContract(routes) {
    const support = routes.filter((route) => cleanUri(route).startsWith('api/admin/v1/support'));
    if (support.length === 0) throw new Error('No administrator support routes were discovered.');

    const ticketResource = (route) => /^api\/admin\/v1\/support\/tickets\/\{[^}]+\}$/.test(cleanUri(route));
    const ticketAction = (route, suffix) => /^api\/admin\/v1\/support\/tickets\/\{[^}]+\}\//.test(cleanUri(route))
        && suffix.test(cleanUri(route));
    const mutation = (route) => methods(route).some((method) => ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method));
    const pick = (predicate, label, required = false) => {
        const route = support.find(predicate) || null;
        if (required && !route) throw new Error(`Required support backend route is missing: ${label}`);
        return route;
    };

    const selected = {
        ticketList: pick((route) => methods(route).includes('GET') && cleanUri(route) === 'api/admin/v1/support/tickets', 'ticket list', true),
        ticketCreate: pick((route) => methods(route).includes('POST') && cleanUri(route) === 'api/admin/v1/support/tickets', 'ticket create'),
        ticketShow: pick((route) => methods(route).includes('GET') && ticketResource(route), 'ticket detail', true),
        ticketUpdate: pick((route) => methods(route).some((method) => ['PUT', 'PATCH'].includes(method))
            && (ticketResource(route) || ticketAction(route, /(status|workflow|priority)$/)), 'ticket update'),
        ticketAssign: pick((route) => mutation(route) && ticketAction(route, /(assignment|assign)$/), 'ticket assignment'),
        ticketReply: pick((route) => mutation(route) && ticketAction(route, /(replies|reply|responses|response)$/), 'ticket reply'),
        ticketNote: pick((route) => mutation(route) && ticketAction(route, /(internal-notes|notes|note)$/), 'ticket note'),
        ticketLink: pick((route) => mutation(route) && ticketAction(route, /(resource-links|resources|links|link)$/), 'ticket link'),
        ticketEscalate: pick((route) => mutation(route) && /\/escalat(?:e|ion)?$/.test(cleanUri(route)), 'ticket escalation'),
        ticketResolve: pick((route) => mutation(route) && ticketAction(route, /(resolve|resolution|close|closure)$/), 'ticket resolution'),
    };

    const contract = {};
    for (const [key, route] of Object.entries(selected)) {
        if (!route) continue;
        let uri = cleanUri(route);
        const firstPlaceholder = uri.match(/\{[^}]+\}/)?.[0];
        if (firstPlaceholder) uri = uri.replace(firstPlaceholder, '{ticketId}');
        contract[key] = { method: primaryMethod(route), uri: `/${uri}` };
    }
    return contract;
}

function exportedJson(source, exportName) {
    const match = source.match(new RegExp(`export\\s+const\\s+${exportName}\\s*=\\s*(\\{[^\\r\\n]*\\})\\s*;`));
    if (!match) return {};
    try {
        return JSON.parse(match[1]);
    } catch (_) {
        return {};
    }
}

function stringBindings(source) {
    const bindings = new Map();
    const regex = /\b(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=\s*(['"`])([^'"`\r\n]*)\2\s*;/g;
    for (const match of source.matchAll(regex)) bindings.set(match[1], match[3]);
    return bindings;
}

function candidateScore(candidate) {
    const key = candidate.key.toLowerCase();
    let score = 0;
    if (key.includes('admin')) score += 8;
    if (key.includes('access')) score += 6;
    if (key.includes('token')) score += 6;
    if (key.includes('auth')) score += 4;
    if (key.includes('session')) score += 3;
    if (key.includes('orbit')) score += 2;
    if (candidate.storage === 'sessionStorage') score += 1;
    return score;
}

function addCandidate(map, storage, key, evidence) {
    if (!['sessionStorage', 'localStorage'].includes(storage)) return;
    if (typeof key !== 'string' || !key.trim()) return;
    if (!/(admin|token|auth|session|credential)/i.test(key)) return;
    const normalized = { storage, key: key.trim(), evidence };
    const id = `${normalized.storage}:${normalized.key}`;
    const existing = map.get(id);
    if (!existing || candidateScore(normalized) > candidateScore(existing)) map.set(id, normalized);
}

function storageCandidatesFromSource(source, fileName, target) {
    const bindings = stringBindings(source);
    const aliases = new Map();
    for (const match of source.matchAll(/\b(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=\s*(?:window\.)?(sessionStorage|localStorage)\s*;/g)) {
        aliases.set(match[1], match[2]);
    }

    const direct = /(?:window\.)?(sessionStorage|localStorage)\.(?:getItem|setItem|removeItem)\(\s*(?:(['"])([^'"]+)\2|([A-Za-z_$][\w$]*))/g;
    for (const match of source.matchAll(direct)) {
        const key = match[3] || bindings.get(match[4]);
        if (key) addCandidate(target, match[1], key, `${fileName}:storage-call`);
    }

    for (const [alias, storage] of aliases.entries()) {
        const aliasRegex = new RegExp(`\\b${alias.replace(/[$]/g, '\\$&')}\\.(?:getItem|setItem|removeItem)\\(\\s*(?:(['\"])([^'\"]+)\\1|([A-Za-z_$][\\w$]*))`, 'g');
        for (const match of source.matchAll(aliasRegex)) {
            const key = match[2] || bindings.get(match[3]);
            if (key) addCandidate(target, storage, key, `${fileName}:storage-alias`);
        }
    }

    // Earlier modules already generated a storageCandidates contract. Treat those
    // contracts as strong evidence because they came from the canonical M1-M3 source.
    for (const regex of [/"storageCandidates"\s*:\s*(\[[^\]]*\])/g, /storageCandidates\s*:\s*(\[[^\]]*\])/g]) {
        for (const match of source.matchAll(regex)) {
            try {
                const list = JSON.parse(match[1]);
                for (const candidate of list) addCandidate(target, candidate.storage, candidate.key, `${fileName}:generated-contract`);
            } catch (_) {
                // Ignore malformed historical generated data; the verifier will fail
                // if no trustworthy canonical credential contract can be discovered.
            }
        }
    }

    // Resolve constant-based keys used by helpers such as readSession(AUTH_TOKEN_KEY).
    // We only infer a storage location when that storage is actually referenced in
    // the same canonical file, so runtime code never scans arbitrary browser keys.
    const storages = [];
    if (/\bsessionStorage\b/.test(source)) storages.push('sessionStorage');
    if (/\blocalStorage\b/.test(source)) storages.push('localStorage');
    for (const [name, value] of bindings.entries()) {
        if (!/(admin|auth|session|token|credential)/i.test(name)) continue;
        if (!/(admin|auth|session|token|credential)/i.test(value)) continue;
        if (!new RegExp(`\\b${name.replace(/[$]/g, '\\$&')}\\b`).test(source)) continue;
        for (const storage of storages) addCandidate(target, storage, value, `${fileName}:constant-contract`);
    }
}

function discoverCanonicalAuthCandidates() {
    const dir = path.join(root, 'resources', 'js', 'admin-console');
    if (!fs.existsSync(dir)) throw new Error('Canonical administrator JavaScript directory is missing.');

    const excluded = new Set([
        'moderation-m4.js',
        'moderation-routes.generated.js',
        'support-m5.js',
        'support-routes.generated.js',
        'admin-api-client.js',
        'admin-auth.generated.js',
    ]);
    const candidates = new Map();
    const queue = [dir];
    while (queue.length) {
        const current = queue.shift();
        for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
            const absolute = path.join(current, entry.name);
            if (entry.isDirectory()) {
                queue.push(absolute);
                continue;
            }
            if (!entry.isFile() || !entry.name.endsWith('.js') || excluded.has(entry.name)) continue;
            const relative = path.relative(dir, absolute).replaceAll('\\', '/');
            storageCandidatesFromSource(fs.readFileSync(absolute, 'utf8'), relative, candidates);
        }
    }

    // Preserve a previously generated canonical contract to make reinstalls idempotent.
    const prior = path.join(dir, 'admin-auth.generated.js');
    if (fs.existsSync(prior)) storageCandidatesFromSource(fs.readFileSync(prior, 'utf8'), 'admin-auth.generated.js', candidates);

    return [...candidates.values()]
        .sort((a, b) => candidateScore(b) - candidateScore(a)
            || `${a.storage}:${a.key}`.localeCompare(`${b.storage}:${b.key}`))
        .map(({ storage, key }) => ({ storage, key }));
}

function patchRuntime(relative, kind) {
    let source = read(relative);
    const importLine = "import { adminApiRequest } from './admin-api-client.js';";
    if (!source.includes(importLine)) {
        const imports = [...source.matchAll(/^import .*;\s*$/gm)];
        if (imports.length === 0) throw new Error(`${relative} has no ES module imports; refusing an unsafe patch.`);
        const last = imports.at(-1);
        const insertAt = last.index + last[0].length;
        source = `${source.slice(0, insertAt)}\n${importLine}${source.slice(insertAt)}`;
    }

    if (source.includes('function resolveToken()')) {
        const start = source.indexOf('    function resolveToken()');
        const endMarker = '    function setLoading(active)';
        const end = source.indexOf(endMarker, start);
        if (start < 0 || end < 0) throw new Error(`Could not isolate the legacy ${kind} API transport in ${relative}.`);
        const label = kind === 'moderation' ? 'moderation' : 'support';
        const replacement = `    async function api(route, options = {}) {\n`
            + `        if (!route?.uri) throw new Error('This ${label} operation is not registered by the backend.');\n`
            + `        return adminApiRequest(route, options);\n`
            + `    }\n\n`;
        source = `${source.slice(0, start)}${replacement}${source.slice(end)}`;
    }

    if (!source.includes('return adminApiRequest(route, options);')) {
        throw new Error(`${relative} was not converted to the shared administrator API client.`);
    }
    if (source.includes('function resolveToken()') || /(?:sessionStorage|localStorage)\.getItem\(/.test(source)) {
        throw new Error(`${relative} still contains an independent browser-token reader.`);
    }
    return source;
}

function backupFiles(paths) {
    const stamp = new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14);
    const backup = path.join(root, 'storage', 'app', 'orbit-admin-ui-runtime-repair-backups', stamp);
    fs.mkdirSync(backup, { recursive: true });
    const manifest = [];
    for (const relative of paths) {
        const source = path.join(root, relative);
        const existed = fs.existsSync(source);
        const item = { path: relative.replaceAll('\\', '/'), existed, sha256: existed ? sha256(source) : null };
        manifest.push(item);
        if (!existed) continue;
        const destination = path.join(backup, relative);
        fs.mkdirSync(path.dirname(destination), { recursive: true });
        fs.copyFileSync(source, destination);
    }
    fs.writeFileSync(path.join(backup, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`, 'utf8');
    return { backup, manifest };
}

function restoreBackup(backup, manifest) {
    console.error('\nRepair validation failed. Restoring the pre-repair source checkpoint...');
    for (const item of manifest) {
        const relative = item.path.replaceAll('/', path.sep);
        const target = path.join(root, relative);
        const saved = path.join(backup, relative);
        if (item.existed) {
            fs.mkdirSync(path.dirname(target), { recursive: true });
            fs.copyFileSync(saved, target);
        } else if (fs.existsSync(target)) {
            fs.rmSync(target, { force: true });
        }
    }
    run('php', ['artisan', 'optimize:clear'], { allowFailure: true });
}

const required = [
    rel('artisan'),
    rel('package.json'),
    rel('resources', 'js', 'admin-console', 'index.js'),
    rel('resources', 'js', 'admin-console', 'moderation-m4.js'),
    rel('resources', 'js', 'admin-console', 'moderation-routes.generated.js'),
    rel('resources', 'js', 'admin-console', 'support-m5.js'),
    rel('resources', 'js', 'admin-console', 'support-routes.generated.js'),
    rel('tests', 'Feature', 'AdminUi', 'AdminModerationReportsUiTest.php'),
    rel('tests', 'Feature', 'AdminUi', 'AdminSupportManagementUiTest.php'),
];
for (const file of required) {
    if (!exists(file)) throw new Error(`Runtime repair prerequisite is missing: ${file}`);
}

console.log('Preflight: inspecting live Laravel routes and the canonical administrator session contract...');
const routes = routeInventory();
const moderationRoutes = moderationContract(routes);
const supportRoutes = supportContract(routes);
const authCandidates = discoverCanonicalAuthCandidates();
if (authCandidates.length === 0) {
    throw new Error(
        'No canonical administrator credential storage contract could be proven from M1-M3 source. '
        + 'No files were changed. This guard prevents introducing another guessed authentication mechanism.',
    );
}

const existingModerationConfig = exportedJson(read(rel('resources', 'js', 'admin-console', 'moderation-routes.generated.js')), 'moderationConfig');
const moderationConfig = {
    ...existingModerationConfig,
    enforcementActions: Array.isArray(existingModerationConfig.enforcementActions) && existingModerationConfig.enforcementActions.length
        ? existingModerationConfig.enforcementActions
        : ['warn_user', 'restrict_feature', 'suspend_user_temp', 'suspend_user_indefinite', 'freeze_circle'],
};
delete moderationConfig.storageCandidates;

const existingSupportConfig = exportedJson(read(rel('resources', 'js', 'admin-console', 'support-routes.generated.js')), 'supportConfig');
const supportConfig = { ...existingSupportConfig };
delete supportConfig.storageCandidates;
if (!supportConfig.actionFields || typeof supportConfig.actionFields !== 'object') supportConfig.actionFields = {};

const targets = [
    rel('resources', 'js', 'admin-console', 'moderation-m4.js'),
    rel('resources', 'js', 'admin-console', 'moderation-routes.generated.js'),
    rel('resources', 'js', 'admin-console', 'support-m5.js'),
    rel('resources', 'js', 'admin-console', 'support-routes.generated.js'),
    rel('resources', 'js', 'admin-console', 'admin-api-client.js'),
    rel('resources', 'js', 'admin-console', 'admin-auth.generated.js'),
    rel('tests', 'Feature', 'AdminUi', 'AdminRuntimeIntegrationContractTest.php'),
];
const { backup, manifest } = backupFiles(targets);

try {
    write(rel('resources', 'js', 'admin-console', 'moderation-m4.js'), patchRuntime(rel('resources', 'js', 'admin-console', 'moderation-m4.js'), 'moderation'));
    write(rel('resources', 'js', 'admin-console', 'support-m5.js'), patchRuntime(rel('resources', 'js', 'admin-console', 'support-m5.js'), 'support'));
    write(
        rel('resources', 'js', 'admin-console', 'moderation-routes.generated.js'),
        `export const moderationRoutes = ${JSON.stringify(moderationRoutes)};\nexport const moderationConfig = ${JSON.stringify(moderationConfig)};\n`,
    );
    write(
        rel('resources', 'js', 'admin-console', 'support-routes.generated.js'),
        `export const supportRoutes = ${JSON.stringify(supportRoutes)};\nexport const supportConfig = ${JSON.stringify(supportConfig)};\n`,
    );
    write(
        rel('resources', 'js', 'admin-console', 'admin-auth.generated.js'),
        `// Generated from canonical M1-M3 administrator browser-session source. Do not hand edit.\n`
        + `export const adminAuthContract = ${JSON.stringify({ storageCandidates: authCandidates })};\n`,
    );
    write(
        rel('resources', 'js', 'admin-console', 'admin-api-client.js'),
        fs.readFileSync(path.join(scriptDir, 'templates', 'admin-api-client.js'), 'utf8'),
    );
    write(
        rel('tests', 'Feature', 'AdminUi', 'AdminRuntimeIntegrationContractTest.php'),
        fs.readFileSync(path.join(scriptDir, 'templates', 'AdminRuntimeIntegrationContractTest.php'), 'utf8'),
    );

    for (const file of [
        'moderation-m4.js',
        'moderation-routes.generated.js',
        'support-m5.js',
        'support-routes.generated.js',
        'admin-api-client.js',
        'admin-auth.generated.js',
    ]) {
        run('node', ['--check', rel('resources', 'js', 'admin-console', file)]);
    }
    run('php', ['-l', rel('tests', 'Feature', 'AdminUi', 'AdminRuntimeIntegrationContractTest.php')]);
    run('php', ['vendor/bin/pint', '--test', rel('tests', 'Feature', 'AdminUi', 'AdminRuntimeIntegrationContractTest.php')]);
    run('php', ['artisan', 'optimize:clear']);

    const tests = [
        rel('tests', 'Feature', 'AdminUi', 'AdminRuntimeIntegrationContractTest.php'),
        rel('tests', 'Feature', 'AdminUi', 'AdminModerationRenderingSmokeTest.php'),
        rel('tests', 'Feature', 'AdminUi', 'AdminModerationReportsUiTest.php'),
        rel('tests', 'Feature', 'AdminUi', 'AdminSupportRenderingSmokeTest.php'),
        rel('tests', 'Feature', 'AdminUi', 'AdminSupportManagementUiTest.php'),
        rel('tests', 'Feature', 'Api', 'Admin', 'V1', 'AdminModerationAppealsRiskTest.php'),
        rel('tests', 'Feature', 'Api', 'Admin', 'V1', 'AdminPrivacyComplianceSupportTest.php'),
    ].filter((file) => exists(file));
    for (const test of tests) run('php', ['artisan', 'test', test]);

    run('npm', ['run', 'build']);

    run('node', [path.join(scriptDir, 'verify-runtime-repair.mjs'), '--static-only']);
} catch (error) {
    restoreBackup(backup, manifest);
    throw error;
}

console.log('\nOrbit M4 + M5 runtime integration repair installed and targeted-gate verified.');
console.log(`Backup: ${backup}`);
console.log(`Moderation contract: ${Object.keys(moderationRoutes).length} registered operation(s).`);
console.log(`Support contract: ${Object.keys(supportRoutes).length} registered operation(s).`);
console.log(`Canonical auth contract: ${authCandidates.length} statically proven storage candidate(s).`);
console.log('No migrations or database mutations were performed by this repair.');
console.log('Next release gate:');
console.log('  .\\setup\\admin-ui-runtime-repair\\verify-admin-ui-runtime-repair.ps1 -FullRegression');
console.log('Then hard-refresh the browser and open Moderation & Reports and Support; their queues are the runtime acceptance check.');
