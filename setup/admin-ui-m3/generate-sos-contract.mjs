import fs from 'node:fs';
import path from 'node:path';

const outputPath = process.argv[2];
const projectRoot = path.resolve(process.argv[3] ?? process.cwd());
if (!outputPath) throw new Error('Usage: node generate-sos-contract.mjs <output-path> [project-root]');

let input = '';
for await (const chunk of process.stdin) input += chunk;

let routes;
try {
    routes = JSON.parse(input);
} catch (error) {
    throw new Error(`Laravel route:list --json did not return parseable JSON for admin/v1/sos: ${error.message}`);
}

if (!Array.isArray(routes) || routes.length === 0) {
    throw new Error('No /api/admin/v1/sos backend routes are registered. Install/repair the existing Admin SOS backend before installing the M3 UI.');
}

function methodsFor(route) {
    return String(route?.method ?? '').toUpperCase().split('|').map((value) => value.trim()).filter(Boolean);
}

function hasMethod(route, allowed) {
    const actual = methodsFor(route);
    return allowed.some((method) => actual.includes(method));
}

function routeIntentText(route) {
    return `${route?.uri ?? ''} ${route?.name ?? ''}`.toLowerCase();
}

function actionText(route) {
    return String(route?.action ?? '').toLowerCase();
}

function findSemantic(methods, intentPattern, actionPattern = null, excludePattern = null) {
    return routes.find((route) => {
        if (!hasMethod(route, methods)) return false;
        const intent = routeIntentText(route);
        const action = actionText(route);
        if (excludePattern?.test(intent) || excludePattern?.test(action)) return false;
        return intentPattern.test(intent) || (actionPattern ? actionPattern.test(action) : false);
    }) ?? null;
}

function exactMutationSegment(segment) {
    const pattern = new RegExp(`(?:^|[\\/._-])${segment}(?:[\\/._-]|$)`, 'i');
    return routes.find((route) => hasMethod(route, ['POST', 'PUT', 'PATCH']) && pattern.test(routeIntentText(route))) ?? null;
}

const parameterPattern = /\{[^}]+\}/;
let list = routes.find((route) => hasMethod(route, ['GET']) && !parameterPattern.test(String(route?.uri ?? '')) && /admin\/v1\/sos\/?$/.test(String(route?.uri ?? ''))) ?? null;
if (!list) list = findSemantic(['GET'], /(list|index).*(sos|incident)|(sos|incident).*(list|index)/, /(list|index).*(sos|incident)|(sos|incident).*(list|index)/, /realtime|history|access|export/);

let detail = routes.find((route) => hasMethod(route, ['GET']) && /admin\/v1\/sos\/\{[^}]+\}\/?$/.test(String(route?.uri ?? ''))) ?? null;
if (!detail) detail = findSemantic(['GET'], /(show|detail).*(sos|incident)|(sos|incident).*(show|detail)/, /(show|detail).*(sos|incident)|(sos|incident).*(show|detail)/, /history|access|export|realtime/);

// Route intent is derived from URI/name first. Controller class names are only
// used with semantic operation words. The generic "Controller" suffix is never
// allowed to imply a /controls endpoint.
const controls = exactMutationSegment('controls');
const assignment = findSemantic(['PUT', 'PATCH', 'POST'], /assign(?:ment|ee)?/, /assign(?:ment|ee)?/, /note|export|realtime|sensitive/);
const classification = findSemantic(['PUT', 'PATCH', 'POST'], /classif|triage/, /classif|triage/, /note|export|realtime|sensitive/);
const explicitClosure = findSemantic(['PUT', 'PATCH', 'POST'], /(?:^|[\/._-])(close|closure|resolution|resolve)(?:[\/._-]|$)|operational[\/._-]?status/, /(close|closure|resolution|resolve|operational.*status)/, /consumer|note|export|realtime|sensitive/);
const notes = findSemantic(['POST', 'PUT'], /(?:^|[\/._-])notes?(?:[\/._-]|$)/, /note/, /realtime/);

const location = findSemantic(['POST', 'PUT', 'PATCH'], /sensitive[\/._-]?location|(?:^|[\/._-])location(?:[\/._-]|$)/, /(access|reveal).*(sos)?.*location|location.*(access|reveal)/, /realtime|telemetry/);
const recording = findSemantic(['POST', 'PUT', 'PATCH'], /sensitive[\/._-]?recording|(?:^|[\/._-])recording(?:[\/._-]|$)/, /(access|reveal).*(sos)?.*recording|recording.*(access|reveal)/, /realtime/);
const accessHistory = findSemantic(
    ['GET'],
    /(?:^|[\/._-])sensitive[\/._-]?access(?:[\/._-]|$)|access[\/._-]?history|history[\/._-]?access|sensitive[\/._-]?history|accesses/,
    /list.*sos.*sensitive.*access|sensitive.*access.*history|access.*history/,
    /realtime/,
);
const exportRoute = findSemantic(['POST', 'PUT', 'PATCH'], /(?:^|[\/._-])exports?(?:[\/._-]|$)/, /export/, /realtime/);
const realtime = findSemantic(['POST'], /realtime.*auth|auth.*realtime/, /realtime.*auth|auth.*realtime/);

function classSourcePath(action) {
    const className = String(action ?? '').split('@')[0].trim();
    if (!className.startsWith('App\\')) return null;
    return path.join(projectRoot, 'app', ...className.slice(4).split('\\')) + '.php';
}

function readIfFile(filePath) {
    try {
        return filePath && fs.statSync(filePath).isFile() ? fs.readFileSync(filePath, 'utf8') : '';
    } catch {
        return '';
    }
}

function referencedAppSources(source) {
    const chunks = [];
    for (const match of source.matchAll(/^use\s+(App\\[^;]+);/gm)) {
        const file = classSourcePath(match[1]);
        const text = readIfFile(file);
        if (text) chunks.push(text);
    }
    return chunks;
}

function routeSourceEvidence(route) {
    const controller = readIfFile(classSourcePath(route?.action));
    if (!controller) return '';
    return [controller, ...referencedAppSources(controller)].join('\n');
}

function fieldFromRules(source, candidates) {
    for (const candidate of candidates) {
        const escaped = candidate.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const pattern = new RegExp(`["']${escaped}["']\\s*=>`);
        if (pattern.test(source)) return candidate;
    }
    return null;
}

function closureFieldsFrom(source) {
    return {
        status: fieldFromRules(source, ['operational_status', 'status']),
        resolution: fieldFromRules(source, ['operational_resolution', 'resolution', 'resolution_reason']),
        reason: fieldFromRules(source, ['reason', 'audit_reason']),
    };
}

function completeClosureFields(fields) {
    return Boolean(fields?.status && fields?.resolution && fields?.reason);
}

function operationalClosureTestSegment() {
    const testPath = path.join(projectRoot, 'tests', 'Feature', 'Api', 'Admin', 'V1', 'AdminSosCommandCenterTest.php');
    const source = readIfFile(testPath);
    if (!source) return '';

    const lower = source.toLowerCase();
    const marker = 'operational closure requires a resolution';
    const markerIndex = lower.indexOf(marker);
    if (markerIndex < 0) return '';

    const start = Math.max(0, lower.lastIndexOf('\nit(', markerIndex));
    const next = lower.indexOf('\nit(', markerIndex + marker.length);
    const end = next >= 0 ? next : Math.min(source.length, markerIndex + 8000);
    return source.slice(start, end);
}

function routeSuffix(route) {
    return String(route?.uri ?? '').replace(/^.*\{[^}]+\}/, '').replace(/^\/+/, '').toLowerCase();
}

function proveClosureVia(route, testSegment) {
    if (!route) return null;

    const suffix = routeSuffix(route);
    const testText = testSegment.toLowerCase();
    if (testSegment && suffix && testText.includes(`/${suffix}`)) {
        const fields = closureFieldsFrom(testSegment);
        if (completeClosureFields(fields)) {
            return {route, fields, evidence: 'backend-test'};
        }
    }

    const source = routeSourceEvidence(route);
    const fields = closureFieldsFrom(source);
    if (completeClosureFields(fields) && /operational[_-]?status/i.test(source) && /resolution/i.test(source)) {
        return {route, fields, evidence: 'controller-request-source'};
    }

    return null;
}

let closureResolution = null;
if (explicitClosure) {
    const source = routeSourceEvidence(explicitClosure);
    const testSegment = operationalClosureTestSegment();
    const fields = closureFieldsFrom(source);
    const testFields = closureFieldsFrom(testSegment);
    closureResolution = {
        route: explicitClosure,
        fields: completeClosureFields(fields) ? fields : (completeClosureFields(testFields) ? testFields : null),
        evidence: 'explicit-route',
    };
} else {
    const testSegment = operationalClosureTestSegment();
    closureResolution = proveClosureVia(classification, testSegment) ?? proveClosureVia(controls, testSegment);
}

const closure = closureResolution?.route ?? null;
const closureReady = closure && completeClosureFields(closureResolution?.fields) ? closure : null;

const required = new Map([
    ['directory GET', list],
    ['incident detail GET', detail],
    ['assignment mutation', assignment],
    ['classification mutation', classification],
    ['operational closure mutation with proven backend semantics and request fields', closureReady],
    ['internal note mutation', notes],
    ['precise-location access', location],
    ['recording-reference access', recording],
    ['sensitive access history', accessHistory],
    ['authorized export', exportRoute],
]);

const missing = [...required].filter(([, route]) => !route).map(([label]) => label);
if (missing.length) {
    const inventory = routes.map((route) => `  ${route?.method ?? ''} ${route?.uri ?? ''} ${route?.name ?? ''} ${route?.action ?? ''}`).join('\n');
    const closureHint = !closureReady && classification
        ? '\nOperational closure was not guessed from classification: the installer requires direct proof from AdminSosCommandCenterTest.php or the classification controller/request source.'
        : '';
    throw new Error(`Admin SOS backend route contract is incomplete for the M3 UI. Missing: ${missing.join(', ')}.${closureHint}\nRegistered routes:\n${inventory}`);
}

function permissionsFor(route) {
    const raw = Array.isArray(route?.middleware) ? route.middleware : route?.middleware ? [route.middleware] : [];
    const permissions = new Set();

    for (const entry of raw) {
        const text = String(entry);
        for (const match of text.matchAll(/(?:admin[._-]?permission|permission)[:=]([A-Za-z0-9._,|-]+)/gi)) {
            for (const permission of match[1].split(/[,|]/)) {
                const value = permission.trim();
                if (value) permissions.add(value);
            }
        }
    }

    return [...permissions].sort();
}

function routeLiteral(route, metadata = null) {
    if (!route) return 'null';
    const method = methodsFor(route).find((value) => value !== 'HEAD') ?? 'GET';
    const uri = `/${String(route.uri ?? '').replace(/^\/+/, '')}`;
    const fields = metadata?.fields && completeClosureFields(metadata.fields)
        ? `, fields: ${JSON.stringify(metadata.fields)}`
        : '';
    const binding = metadata?.evidence ? `, binding: ${JSON.stringify(metadata.evidence)}` : '';
    return `{method: ${JSON.stringify(method)}, uri: ${JSON.stringify(uri)}, permissions: ${JSON.stringify(permissionsFor(route))}${fields}${binding}}`;
}

const contract = {
    list: {route: list},
    detail: {route: detail},
    controls: {route: controls},
    assignment: {route: assignment},
    classification: {route: classification},
    closure: {route: closure, metadata: closureResolution},
    notes: {route: notes},
    location: {route: location},
    recording: {route: recording},
    accessHistory: {route: accessHistory},
    export: {route: exportRoute},
    realtime: {route: realtime},
};

const lines = [
    "// Generated by setup/admin-ui-m3/generate-sos-contract.mjs from Laravel's registered admin SOS routes.",
    '// Do not hand-edit. Re-run the M3 installer after intentional backend route changes.',
    'export const SOS_API = Object.freeze({',
    ...Object.entries(contract).map(([key, value]) => `    ${key}: ${routeLiteral(value.route, value.metadata)},`),
    '});',
    '',
];

fs.mkdirSync(path.dirname(path.resolve(outputPath)), {recursive: true});
fs.writeFileSync(outputPath, lines.join('\n'), 'utf8');
console.log(`Admin SOS browser route contract generated from ${routes.length} registered backend route(s).`);
if (closureResolution?.evidence) console.log(`Operational closure binding proven via ${closureResolution.evidence}.`);
