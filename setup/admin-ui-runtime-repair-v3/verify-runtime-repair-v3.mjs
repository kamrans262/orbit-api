import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { discoverCanonicalAdminTransport } from './transport-discovery.mjs';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(scriptDir, '..', '..');
process.chdir(root);
const rel = (...parts) => path.join(...parts);
const exists = (relative) => fs.existsSync(path.join(root, relative));
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const full = process.argv.includes('--full');
const staticOnly = process.argv.includes('--static-only');

function commandSpec(name, args) {
    if (process.platform === 'win32' && name === 'npm') {
        return { file: process.env.ComSpec || process.env.COMSPEC || 'cmd.exe', args: ['/d', '/s', '/c', ['npm', ...args].join(' ')] };
    }
    return { file: name, args };
}

function run(name, args) {
    console.log(`\n== ${name} ${args.join(' ')} ==`);
    const command = commandSpec(name, args);
    const result = spawnSync(command.file, command.args, { cwd: root, stdio: 'inherit', shell: false });
    if (result.error) throw result.error;
    if (result.status !== 0) throw new Error(`${name} ${args.join(' ')} failed with exit code ${result.status}.`);
}

function parseExport(relative, exportName) {
    const source = read(relative);
    const match = source.match(new RegExp(`export\\s+const\\s+${exportName}\\s*=\\s*([^;]+);`));
    if (!match) throw new Error(`${relative} does not export ${exportName}.`);
    return JSON.parse(match[1]);
}

const required = [
    rel('resources', 'js', 'admin-console', 'moderation-m4.js'),
    rel('resources', 'js', 'admin-console', 'moderation-routes.generated.js'),
    rel('resources', 'js', 'admin-console', 'support-m5.js'),
    rel('resources', 'js', 'admin-console', 'support-routes.generated.js'),
    rel('resources', 'js', 'admin-console', 'admin-api-client.js'),
    rel('resources', 'js', 'admin-console', 'admin-auth.generated.js'),
    rel('tests', 'Feature', 'AdminUi', 'AdminRuntimeIntegrationContractTest.php'),
];
for (const file of required) if (!exists(file)) throw new Error(`Runtime repair output is missing: ${file}`);

for (const [file, label] of [
    [rel('resources', 'js', 'admin-console', 'moderation-m4.js'), 'Moderation'],
    [rel('resources', 'js', 'admin-console', 'support-m5.js'), 'Support'],
]) {
    const source = read(file);
    if (!source.includes("import { adminApiRequest } from './admin-api-client.js';")) throw new Error(`${label} is not using the shared admin API client.`);
    if (!source.includes('return adminApiRequest(route, options);')) throw new Error(`${label} API wrapper does not delegate to the shared client.`);
    if (source.includes('function resolveToken(') || /(?:sessionStorage|localStorage)\.getItem\(/.test(source)) throw new Error(`${label} still contains independent credential discovery.`);
}

const moderationRoutes = parseExport(rel('resources', 'js', 'admin-console', 'moderation-routes.generated.js'), 'moderationRoutes');
for (const key of ['reportList', 'reportShow', 'reportAssign', 'reportWorkflow', 'appealList', 'appealShow', 'riskList', 'riskShow', 'reauthenticate']) {
    if (!moderationRoutes[key]?.uri) throw new Error(`Moderation route contract is missing ${key}.`);
}
const supportRoutes = parseExport(rel('resources', 'js', 'admin-console', 'support-routes.generated.js'), 'supportRoutes');
for (const key of ['ticketList', 'ticketShow']) if (!supportRoutes[key]?.uri) throw new Error(`Support route contract is missing ${key}.`);

const installedAuth = parseExport(rel('resources', 'js', 'admin-console', 'admin-auth.generated.js'), 'adminAuthContract');
const liveAuth = discoverCanonicalAdminTransport(root);
if (!['canonical-web-storage', 'canonical-cookie'].includes(installedAuth.strategy)) throw new Error('Installed canonical administrator auth strategy is invalid.');
if (JSON.stringify(installedAuth.storageCandidates || []) !== JSON.stringify(liveAuth.storageCandidates || [])) {
    throw new Error('Canonical administrator auth storage contract drifted after installation. Rerun the installer before using M4/M5.');
}
if (JSON.stringify(installedAuth.sourceRoots || []) !== JSON.stringify(liveAuth.sourceRoots || [])) {
    throw new Error('Canonical administrator auth source roots drifted after installation. Rerun the installer before using M4/M5.');
}

const client = read(rel('resources', 'js', 'admin-console', 'admin-api-client.js'));
if (/Object\.keys\(window\.(?:localStorage|sessionStorage)\)/.test(client)) throw new Error('Shared admin API client scans arbitrary browser storage.');
if (!client.includes("adminAuthContract.strategy === 'canonical-cookie'") || !client.includes('resolveCanonicalTokens()')) {
    throw new Error('Shared admin API client is missing the canonical auth strategy switch.');
}

for (const file of ['moderation-m4.js', 'moderation-routes.generated.js', 'support-m5.js', 'support-routes.generated.js', 'admin-api-client.js', 'admin-auth.generated.js']) {
    run('node', ['--check', rel('resources', 'js', 'admin-console', file)]);
}
console.log(`\nStatic canonical-auth contract passed: ${installedAuth.strategy}; ${installedAuth.sourceRoots.length} authoritative root(s); ${installedAuth.storageCandidates?.length || 0} storage key(s).`);
if (staticOnly) process.exit(0);

run('php', ['artisan', 'optimize:clear']);
run('php', ['vendor/bin/pint', '--test', rel('tests', 'Feature', 'AdminUi', 'AdminRuntimeIntegrationContractTest.php')]);

const targeted = [
    rel('tests', 'Feature', 'AdminUi', 'AdminRuntimeIntegrationContractTest.php'),
    rel('tests', 'Feature', 'AdminUi', 'AdminConsoleConsolidationTest.php'),
    rel('tests', 'Feature', 'AdminUi', 'AdminSosCommandCenterUiTest.php'),
    rel('tests', 'Feature', 'AdminUi', 'AdminModerationRenderingSmokeTest.php'),
    rel('tests', 'Feature', 'AdminUi', 'AdminModerationReportsUiTest.php'),
    rel('tests', 'Feature', 'AdminUi', 'AdminSupportRenderingSmokeTest.php'),
    rel('tests', 'Feature', 'AdminUi', 'AdminSupportManagementUiTest.php'),
    rel('tests', 'Feature', 'Api', 'Admin', 'V1', 'AdminFoundationTest.php'),
    rel('tests', 'Feature', 'Api', 'Admin', 'V1', 'AdminModerationAppealsRiskTest.php'),
    rel('tests', 'Feature', 'Api', 'Admin', 'V1', 'AdminPrivacyComplianceSupportTest.php'),
].filter((file) => exists(file));
for (const test of targeted) run('php', ['artisan', 'test', test]);
run('npm', ['run', 'build']);

if (full) {
    run('php', ['vendor/bin/pint', '--test']);
    run('php', ['artisan', 'test']);
}

console.log('\nOrbit M4 + M5 canonical-auth verification passed.');
if (!full) console.log('For the release gate, rerun with -FullRegression.');
console.log('Final acceptance is the live browser: hard-refresh, then load the real Moderation and Support queues after Foundation MFA sign-in.');
