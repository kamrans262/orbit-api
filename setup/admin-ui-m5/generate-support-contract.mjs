import fs from 'node:fs';
import path from 'node:path';

const [rootArg, routeJsonArg, outputArg] = process.argv.slice(2);
if (!rootArg || !routeJsonArg || !outputArg) {
    console.error('Usage: node generate-support-contract.mjs <project-root> <route-json> <output-js>');
    process.exit(2);
}

const root = path.resolve(rootArg);
const routes = JSON.parse(fs.readFileSync(path.resolve(routeJsonArg), 'utf8'));
if (!Array.isArray(routes)) throw new Error('Laravel route inventory is not an array.');

const methods = (route) => String(route.method || '').split('|').filter((method) => method !== 'HEAD');
const primaryMethod = (route) => methods(route)[0] || 'GET';
const uri = (route) => String(route.uri || '').replace(/^\//, '');
const isMutation = (route) => methods(route).some((method) => ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method));
const supportRoutes = routes.filter((route) => uri(route).startsWith('api/admin/v1/support'));

if (!supportRoutes.length) throw new Error('No admin support backend routes were discovered.');

function exactTicketResource(route) {
    return /^api\/admin\/v1\/support\/tickets\/\{[^}]+\}$/.test(uri(route));
}

function pick(predicate, label, required = false) {
    const found = supportRoutes.find(predicate) || null;
    if (required && !found) throw new Error(`Required support backend route is missing: ${label}`);
    return found;
}

const selected = {
    ticketList: pick((route) => methods(route).includes('GET') && uri(route) === 'api/admin/v1/support/tickets', 'ticket list', true),
    ticketCreate: pick((route) => methods(route).includes('POST') && uri(route) === 'api/admin/v1/support/tickets', 'ticket create'),
    ticketShow: pick((route) => methods(route).includes('GET') && exactTicketResource(route), 'ticket show', true),
    ticketUpdate: pick((route) => methods(route).some((method) => ['PUT', 'PATCH'].includes(method)) && (exactTicketResource(route) || exactAction(uri(route), /(status|workflow|priority)$/)), 'ticket update'),
    ticketAssign: pick((route) => isMutation(route) && exactAction(uri(route), /(assignment|assign)$/), 'ticket assignment'),
    ticketReply: pick((route) => isMutation(route) && exactAction(uri(route), /(replies|reply|responses|response)$/), 'ticket reply'),
    ticketNote: pick((route) => isMutation(route) && exactAction(uri(route), /(internal-notes|notes|note)$/), 'ticket note'),
    ticketLink: pick((route) => isMutation(route) && exactAction(uri(route), /(resource-links|resources|links|link)$/), 'ticket resource link'),
    ticketEscalate: pick((route) => isMutation(route) && /\/escalat(?:e|ion)?$/.test(uri(route)), 'ticket escalation'),
    ticketResolve: pick((route) => isMutation(route) && exactAction(uri(route), /(resolve|resolution|close|closure)$/), 'ticket resolution'),
};

function exactAction(value, suffix) {
    return /^api\/admin\/v1\/support\/tickets\/\{[^}]+\}\//.test(value) && suffix.test(value);
}

function normalizedRoute(route) {
    if (!route) return null;
    let value = uri(route);
    const placeholders = [...value.matchAll(/\{[^}]+\}/g)];
    if (placeholders.length) value = value.replace(placeholders[0][0], '{ticketId}');
    return { method: primaryMethod(route), uri: `/${value}` };
}

function classFile(action) {
    const className = String(action || '').split('@')[0];
    if (!className.startsWith('App\\')) return null;
    return path.join(root, className.replace(/^App\\/, 'app\\').replaceAll('\\', path.sep) + '.php');
}

function importedRequestClasses(controllerSource) {
    const imports = new Map();
    for (const match of controllerSource.matchAll(/^use\s+([^;]+);/gm)) {
        const fqcn = match[1].trim();
        const simple = fqcn.split('\\').pop();
        imports.set(simple, fqcn);
    }
    const used = new Set();
    for (const match of controllerSource.matchAll(/\b([A-Za-z0-9_]+Request)\s+\$request\b/g)) used.add(match[1]);
    return [...used].map((simple) => imports.get(simple)).filter(Boolean);
}

function requestFile(fqcn) {
    if (!fqcn?.startsWith('App\\')) return null;
    return path.join(root, fqcn.replace(/^App\\/, 'app\\').replaceAll('\\', path.sep) + '.php');
}

function parseRequestFields(source) {
    const method = /function\s+rules\s*\([^)]*\)[^{]*\{/.exec(source);
    if (!method) return [];
    const methodStart = method.index + method[0].length;
    const nextMethod = source.slice(methodStart).search(/\n\s*(?:public|protected|private)\s+function\s+/);
    const scope = nextMethod >= 0 ? source.slice(methodStart, methodStart + nextMethod) : source.slice(methodStart);
    const fields = [];
    const keyRegex = /['"]([A-Za-z_][A-Za-z0-9_.-]*)['"]\s*=>/g;
    const matches = [...scope.matchAll(keyRegex)];
    for (let index = 0; index < matches.length; index++) {
        const name = matches[index][1];
        if (name.includes('*')) continue;
        const start = matches[index].index;
        const end = index + 1 < matches.length ? matches[index + 1].index : Math.min(scope.length, start + 700);
        const ruleChunk = scope.slice(start, end);
        const required = /['"]required['"]|\brequired\b/.test(ruleChunk) && !/sometimes/.test(ruleChunk);
        fields.push({ name, required });
    }
    return fields;
}

function fieldsFor(route) {
    if (!route) return [];
    const controllerPath = classFile(route.action);
    if (!controllerPath || !fs.existsSync(controllerPath)) return [];
    const source = fs.readFileSync(controllerPath, 'utf8');
    const collected = [];
    for (const requestClass of importedRequestClasses(source)) {
        const file = requestFile(requestClass);
        if (!file || !fs.existsSync(file)) continue;
        collected.push(...parseRequestFields(fs.readFileSync(file, 'utf8')));
    }
    const seen = new Set();
    return collected.filter((field) => {
        if (seen.has(field.name)) return false;
        seen.add(field.name);
        return true;
    });
}

const actionFields = {};
for (const [key, route] of Object.entries(selected)) {
    if (!route || ['ticketList', 'ticketShow'].includes(key)) continue;
    actionFields[key] = fieldsFor(route);
}

function discoverStorageCandidates() {
    const dir = path.join(root, 'resources', 'js', 'admin-console');
    if (!fs.existsSync(dir)) return [];
    const candidates = [];
    for (const name of fs.readdirSync(dir)) {
        if (!name.endsWith('.js') || name.startsWith('support-')) continue;
        const source = fs.readFileSync(path.join(dir, name), 'utf8');
        for (const match of source.matchAll(/(sessionStorage|localStorage)\.getItem\(\s*['"]([^'"]+)['"]\s*\)/g)) {
            if (/admin|token|auth|session/i.test(match[2])) candidates.push({ storage: match[1], key: match[2] });
        }
    }
    const unique = new Map(candidates.map((candidate) => [`${candidate.storage}:${candidate.key}`, candidate]));
    return [...unique.values()].sort((a, b) => `${a.storage}:${a.key}`.localeCompare(`${b.storage}:${b.key}`));
}

const contract = {};
for (const [key, route] of Object.entries(selected)) {
    if (route) contract[key] = normalizedRoute(route);
}

const output = `export const supportRoutes = ${JSON.stringify(contract)};\nexport const supportConfig = ${JSON.stringify({ actionFields, storageCandidates: discoverStorageCandidates() })};\n`;
fs.mkdirSync(path.dirname(path.resolve(outputArg)), { recursive: true });
fs.writeFileSync(path.resolve(outputArg), output, 'utf8');

console.log(`Generated support contract with ${Object.keys(contract).length} route(s).`);
