import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(process.argv[2] ?? process.cwd());
const rel = (...parts) => path.join(root, ...parts);
const read = (file) => fs.readFileSync(file, 'utf8');
const fail = (message) => { throw new Error(message); };
const expectFile = (file) => { if (!fs.existsSync(file)) fail(`Required file not found: ${file}`); };

const compatibilityClient = rel('resources', 'js', 'admin-console', 'admin-api-client.js');
const canonicalSession = rel('resources', 'js', 'admin-console', 'auth-session.js');
const canonicalClient = rel('resources', 'js', 'admin-console', 'api-client.js');
const generatedContract = rel('resources', 'js', 'admin-console', 'admin-auth.generated.js');
const moderation = rel('resources', 'js', 'admin-console', 'moderation-m4.js');
const support = rel('resources', 'js', 'admin-console', 'support-m5.js');

for (const file of [compatibilityClient, canonicalSession, canonicalClient, generatedContract, moderation, support]) {
    expectFile(file);
}

const compatibilitySource = read(compatibilityClient);
const sessionSource = read(canonicalSession);
const canonicalSource = read(canonicalClient);
const contractSource = read(generatedContract);
const moderationSource = read(moderation);
const supportSource = read(support);

if (!compatibilitySource.includes("import {adminAccessToken} from './auth-session.js';")) {
    fail('M4/M5 compatibility client does not import the canonical adminAccessToken accessor.');
}

const executeIndex = compatibilitySource.indexOf('async function execute(');
if (executeIndex < 0) fail('M4/M5 compatibility client execute() function was not found.');
const executeHead = compatibilitySource.slice(executeIndex, executeIndex + 900);
if (!executeHead.includes('const token = adminAccessToken();')) {
    fail('M4/M5 compatibility client does not resolve its bearer token from the canonical auth-session module inside execute().');
}
if (!executeHead.includes('headers.Authorization = `Bearer ${token}`')) {
    fail('M4/M5 compatibility client no longer applies the resolved bearer token to protected requests.');
}

if (!sessionSource.includes('export function adminAccessToken()')) {
    fail('Canonical auth-session.js no longer exports adminAccessToken().');
}
if (!canonicalSource.includes('const token = adminAccessToken();')) {
    fail('Canonical api-client.js no longer consumes adminAccessToken().');
}

if (!contractSource.includes('"strategy":"canonical-auth-session-module"')) {
    fail('Generated M4/M5 auth metadata is not pinned to canonical-auth-session-module.');
}
if (!contractSource.includes('"storageCandidates":[]')) {
    fail('Generated M4/M5 auth metadata still advertises browser storage as an authentication source.');
}
for (const forbidden of [
    'orbit.admin.console.return_to.v1',
    'orbit.admin.theme.v1',
    'orbit.admin.console.identity.v1',
]) {
    if (contractSource.includes(forbidden)) {
        fail(`Generated M4/M5 auth metadata incorrectly treats ${forbidden} as an authentication candidate.`);
    }
}

if (!moderationSource.includes("./admin-api-client.js") || !supportSource.includes("./admin-api-client.js")) {
    fail('Moderation and Support are not both routed through the repaired M4/M5 compatibility client.');
}

console.log('Orbit M4 + M5 single-auth transport contract passed.');
console.log('Compatibility client: canonical auth-session bearer only.');
console.log('Generated storage candidates: none.');
