import fs from 'node:fs';
import path from 'node:path';

const JS_EXTENSIONS = ['', '.js', '.mjs'];

function normalize(relative) {
    return relative.replaceAll('\\', '/');
}

function localImports(source) {
    const imports = [];
    const regex = /(?:import\s+(?:[^'";]+?\s+from\s+)?|export\s+[^'";]+?\s+from\s+)['"]([^'"]+)['"]/g;
    for (const match of source.matchAll(regex)) {
        if (match[1].startsWith('.')) imports.push(match[1]);
    }
    return imports;
}

function resolveImport(fromFile, specifier) {
    const base = path.resolve(path.dirname(fromFile), specifier);
    for (const extension of JS_EXTENSIONS) {
        const candidate = `${base}${extension}`;
        if (fs.existsSync(candidate) && fs.statSync(candidate).isFile()) return candidate;
    }
    for (const name of ['index.js', 'index.mjs']) {
        const candidate = path.join(base, name);
        if (fs.existsSync(candidate) && fs.statSync(candidate).isFile()) return candidate;
    }
    return null;
}

function excludedRuntimeModule(file) {
    return /(?:moderation-m4|moderation-routes\.generated|support-m5|support-routes\.generated|admin-api-client|admin-auth\.generated)\.(?:js|mjs)$/i.test(path.basename(file));
}

function walkGraph(roots) {
    const visited = new Set();
    const queue = [...roots];
    const ordered = [];
    while (queue.length) {
        const file = queue.shift();
        const absolute = path.resolve(file);
        if (visited.has(absolute) || !fs.existsSync(absolute)) continue;
        visited.add(absolute);
        ordered.push(absolute);
        const source = fs.readFileSync(absolute, 'utf8');
        for (const specifier of localImports(source)) {
            const resolved = resolveImport(absolute, specifier);
            if (resolved && !excludedRuntimeModule(resolved) && !visited.has(resolved)) queue.push(resolved);
        }
    }
    return ordered;
}

function listJavascript(dir) {
    const result = [];
    const queue = [dir];
    while (queue.length) {
        const current = queue.shift();
        if (!fs.existsSync(current)) continue;
        for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
            const absolute = path.join(current, entry.name);
            if (entry.isDirectory()) queue.push(absolute);
            else if (entry.isFile() && /\.(?:js|mjs)$/.test(entry.name)) result.push(absolute);
        }
    }
    return result.sort();
}

function rootScore(file, source) {
    const name = path.basename(file).toLowerCase();
    let score = 0;
    if (/api\/admin\/v1\/auth\/me/.test(source)) score += 100;
    if (/data-auth-gate/.test(source)) score += 80;
    if (/auth(?:enticate|entication)?[^\n]{0,80}(?:administrator|admin)/i.test(source)) score += 35;
    if (/adminMe|authMe|verifyAdminSession|administratorSession/.test(source)) score += 30;
    if (/ADMIN_[A-Z0-9_]*TOKEN|currentAdminToken|adminAccessToken|administratorAccessToken/.test(source)) score += 65;
    if (/sos-routes\.generated|\bsosRoutes\b|data-orbit-view\^?=["']sos/i.test(source)) score += 20;
    if (name.includes('sos')) score += 15;
    if (/\bfetch\s*\(/.test(source)) score += 5;
    if (/moderation|support-m5|admin-api-client|admin-auth\.generated/i.test(name)) score -= 200;
    return score;
}

function canonicalRoots(adminConsoleDir) {
    const files = listJavascript(adminConsoleDir);
    const ranked = files.map((file) => {
        const source = fs.readFileSync(file, 'utf8');
        return { file, source, score: rootScore(file, source) };
    }).filter((item) => item.score > 0)
        .sort((a, b) => b.score - a.score || a.file.localeCompare(b.file));

    if (!ranked.length) return [];
    const best = ranked[0].score;
    // Keep only the tight authoritative group: the best root and peers close enough
    // to represent the same Foundation/M3 session transport, never the whole console.
    return ranked.filter((item) => item.score >= Math.max(15, best - 20)).map((item) => item.file);
}

function stringBindings(source) {
    const values = new Map();
    const regex = /\b(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=\s*(['"`])([^'"`\r\n]*)\2\s*;/g;
    for (const match of source.matchAll(regex)) values.set(match[1], match[3]);
    return values;
}

function pushCandidate(result, seen, storage, key, evidence) {
    if (!['sessionStorage', 'localStorage'].includes(storage)) return;
    if (typeof key !== 'string' || !key.trim()) return;
    const id = `${storage}:${key.trim()}`;
    if (seen.has(id)) return;
    seen.add(id);
    result.push({ storage, key: key.trim(), evidence });
}

function parseGeneratedCandidates(source, relative, result, seen) {
    const patterns = [
        /["']storageCandidates["']\s*:\s*(\[[\s\S]*?\])/g,
        /\bstorageCandidates\s*:\s*(\[[\s\S]*?\])/g,
    ];
    for (const regex of patterns) {
        for (const match of source.matchAll(regex)) {
            try {
                const list = JSON.parse(match[1]);
                if (!Array.isArray(list)) continue;
                for (const candidate of list) {
                    pushCandidate(result, seen, candidate?.storage, candidate?.key, `${relative}:generated-contract`);
                }
            } catch (_) {
                // A non-JSON JavaScript array is handled by direct storage-call analysis below.
            }
        }
    }
}

function storageCalls(source, relative, result, seen) {
    const bindings = stringBindings(source);
    const aliases = new Map();
    for (const match of source.matchAll(/\b(?:const|let|var)\s+([A-Za-z_$][\w$]*)\s*=\s*(?:window\.)?(sessionStorage|localStorage)\s*;/g)) {
        aliases.set(match[1], match[2]);
    }

    const direct = /(?:window\.)?(sessionStorage|localStorage)\.(?:getItem|setItem)\(\s*(?:(['"])([^'"]+)\2|([A-Za-z_$][\w$]*))/g;
    for (const match of source.matchAll(direct)) {
        const key = match[3] || bindings.get(match[4]);
        if (key) pushCandidate(result, seen, match[1], key, `${relative}:storage-call`);
    }

    for (const [alias, storage] of aliases) {
        const escaped = alias.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(`\\b${escaped}\\.(?:getItem|setItem)\\(\\s*(?:(['"])([^'"]+)\\1|([A-Za-z_$][\\w$]*))`, 'g');
        for (const match of source.matchAll(regex)) {
            const key = match[2] || bindings.get(match[3]);
            if (key) pushCandidate(result, seen, storage, key, `${relative}:storage-alias`);
        }
    }
}

function sourceSignals(source) {
    return {
        sendsBearer: /Authorization[^\n]{0,100}Bearer|Bearer[^\n]{0,100}Authorization/i.test(source),
        cookieCredentials: /credentials\s*:\s*['"](?:same-origin|include)['"]/.test(source),
        adminApi: /api\/admin\/v1\//.test(source) || /admin[^\n]{0,50}Routes/.test(source),
        fetch: /\bfetch\s*\(/.test(source),
    };
}

export function discoverCanonicalAdminTransport(projectRoot) {
    const adminConsoleDir = path.join(projectRoot, 'resources', 'js', 'admin-console');
    if (!fs.existsSync(adminConsoleDir)) throw new Error('Canonical administrator JavaScript directory is missing.');

    const roots = canonicalRoots(adminConsoleDir);
    if (!roots.length) {
        throw new Error('Could not identify the canonical Foundation/M3 administrator session transport root.');
    }

    const graph = walkGraph(roots);
    const candidates = [];
    const seen = new Set();
    const evidence = [];
    let sendsBearer = false;
    let cookieCredentials = false;
    let adminApi = false;
    let usesFetch = false;

    for (const file of graph) {
        const relative = normalize(path.relative(projectRoot, file));
        const source = fs.readFileSync(file, 'utf8');
        parseGeneratedCandidates(source, relative, candidates, seen);
        storageCalls(source, relative, candidates, seen);
        const signals = sourceSignals(source);
        sendsBearer ||= signals.sendsBearer;
        cookieCredentials ||= signals.cookieCredentials;
        adminApi ||= signals.adminApi;
        usesFetch ||= signals.fetch;
        if (signals.sendsBearer || signals.cookieCredentials || signals.adminApi || candidates.some((candidate) => candidate.evidence.startsWith(relative))) {
            evidence.push(relative);
        }
    }

    const strategy = candidates.length ? 'canonical-web-storage' : (cookieCredentials && usesFetch && adminApi ? 'canonical-cookie' : 'unproven');
    if (strategy === 'unproven') {
        throw new Error(
            'The canonical Foundation/M3 source was found, but its authenticated admin transport could not be proven. '
            + 'Refusing to guess or introduce a second authentication mechanism.',
        );
    }

    return {
        strategy,
        storageCandidates: candidates.map(({ storage, key }) => ({ storage, key })),
        sourceRoots: roots.map((file) => normalize(path.relative(projectRoot, file))),
        graphFiles: graph.map((file) => normalize(path.relative(projectRoot, file))),
        evidenceFiles: [...new Set(evidence)],
        signals: { sendsBearer, cookieCredentials, adminApi, usesFetch },
    };
}
