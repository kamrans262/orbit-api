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
const args = new Set(process.argv.slice(2));
const full = args.has('--full');
const staticOnly = args.has('--static-only');
const rel = (...parts) => path.join(...parts);
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const exists = (relative) => fs.existsSync(path.join(root, relative));

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

function run(name, commandArgs) {
    console.log(`\n== ${name} ${commandArgs.join(' ')} ==`);
    const command = commandSpec(name, commandArgs);
    const result = spawnSync(command.file, command.args, { cwd: root, stdio: 'inherit', shell: false });
    if (result.error) throw result.error;
    if (result.status !== 0) throw new Error(`${name} ${commandArgs.join(' ')} failed with exit code ${result.status}.`);
}

function parseExport(relative, name) {
    const source = read(relative);
    const match = source.match(new RegExp(`export\\s+const\\s+${name}\\s*=\\s*(\\{[^\\r\\n]*\\})\\s*;`));
    if (!match) throw new Error(`Could not parse ${name} from ${relative}.`);
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
for (const file of required) if (!exists(file)) throw new Error(`Runtime repair file is missing: ${file}`);

const moderation = read(rel('resources', 'js', 'admin-console', 'moderation-m4.js'));
const support = read(rel('resources', 'js', 'admin-console', 'support-m5.js'));
for (const [label, source] of [['Moderation', moderation], ['Support', support]]) {
    if (!source.includes("import { adminApiRequest } from './admin-api-client.js';")) throw new Error(`${label} is not using the shared admin API client.`);
    if (source.includes('function resolveToken(')) throw new Error(`${label} still contains a private token resolver.`);
    if (/(?:sessionStorage|localStorage)\.getItem\(/.test(source)) throw new Error(`${label} still reads browser credentials independently.`);
}

const moderationRoutes = parseExport(rel('resources', 'js', 'admin-console', 'moderation-routes.generated.js'), 'moderationRoutes');
for (const key of ['reportList', 'reportShow', 'appealList', 'appealShow', 'riskList', 'riskShow', 'reauthenticate']) {
    if (!moderationRoutes[key]?.uri) throw new Error(`Moderation route contract is missing ${key}.`);
}

const supportRoutes = parseExport(rel('resources', 'js', 'admin-console', 'support-routes.generated.js'), 'supportRoutes');
for (const key of ['ticketList', 'ticketShow']) {
    if (!supportRoutes[key]?.uri) throw new Error(`Support route contract is missing ${key}.`);
}

const auth = parseExport(rel('resources', 'js', 'admin-console', 'admin-auth.generated.js'), 'adminAuthContract');
if (!Array.isArray(auth.storageCandidates) || auth.storageCandidates.length === 0) {
    throw new Error('Canonical administrator auth contract has no proven browser-session candidates.');
}
for (const candidate of auth.storageCandidates) {
    if (!['sessionStorage', 'localStorage'].includes(candidate.storage) || typeof candidate.key !== 'string' || !candidate.key) {
        throw new Error('Canonical administrator auth contract contains an invalid candidate.');
    }
}

for (const file of ['moderation-m4.js', 'moderation-routes.generated.js', 'support-m5.js', 'support-routes.generated.js', 'admin-api-client.js', 'admin-auth.generated.js']) {
    run('node', ['--check', rel('resources', 'js', 'admin-console', file)]);
}

console.log(`\nStatic runtime contract passed: ${Object.keys(moderationRoutes).length} moderation routes, ${Object.keys(supportRoutes).length} support routes, ${auth.storageCandidates.length} canonical auth candidate(s).`);
if (staticOnly) process.exit(0);

run('php', ['artisan', 'optimize:clear']);
run('php', ['vendor/bin/pint', '--test', rel('tests', 'Feature', 'AdminUi', 'AdminRuntimeIntegrationContractTest.php')]);

const targetedTests = [
    rel('tests', 'Feature', 'AdminUi', 'AdminRuntimeIntegrationContractTest.php'),
    rel('tests', 'Feature', 'AdminUi', 'AdminModerationRenderingSmokeTest.php'),
    rel('tests', 'Feature', 'AdminUi', 'AdminModerationReportsUiTest.php'),
    rel('tests', 'Feature', 'AdminUi', 'AdminSupportRenderingSmokeTest.php'),
    rel('tests', 'Feature', 'AdminUi', 'AdminSupportManagementUiTest.php'),
    rel('tests', 'Feature', 'Api', 'Admin', 'V1', 'AdminModerationAppealsRiskTest.php'),
    rel('tests', 'Feature', 'Api', 'Admin', 'V1', 'AdminPrivacyComplianceSupportTest.php'),
].filter((file) => exists(file));
for (const test of targetedTests) run('php', ['artisan', 'test', test]);
run('npm', ['run', 'build']);

if (full) {
    run('php', ['vendor/bin/pint', '--test']);
    run('php', ['artisan', 'test']);
}

console.log('\nOrbit M4 + M5 runtime integration verification passed.');
if (!full) console.log('For the release gate, rerun with -FullRegression.');
console.log('Browser acceptance still requires a hard refresh followed by successful loading of the real Moderation and Support queues.');
