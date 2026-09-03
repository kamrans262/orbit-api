import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(scriptDir, '..', '..');
process.chdir(root);

const backupArg = process.argv[2];
if (!backupArg) throw new Error('Usage: rollback-runtime-repair.mjs <backup-path>');
const backup = path.resolve(root, backupArg);
const manifestFile = path.join(backup, 'manifest.json');
if (!fs.existsSync(manifestFile)) throw new Error(`Backup manifest not found: ${manifestFile}`);
const manifest = JSON.parse(fs.readFileSync(manifestFile, 'utf8'));
if (!Array.isArray(manifest)) throw new Error('Backup manifest is invalid.');

for (const item of manifest) {
    const relative = String(item.path || '').replaceAll('/', path.sep);
    if (!relative || relative.startsWith('..')) throw new Error(`Unsafe backup path: ${relative}`);
    const target = path.join(root, relative);
    const saved = path.join(backup, relative);
    if (item.existed) {
        if (!fs.existsSync(saved)) throw new Error(`Backup file is missing: ${saved}`);
        fs.mkdirSync(path.dirname(target), { recursive: true });
        fs.copyFileSync(saved, target);
    } else if (fs.existsSync(target)) {
        fs.rmSync(target, { force: true });
    }
}

const result = spawnSync('php', ['artisan', 'optimize:clear'], { cwd: root, stdio: 'inherit', shell: false });
if (result.error) throw result.error;
if (result.status !== 0) throw new Error(`Laravel cache clear failed with exit code ${result.status}.`);
console.log(`Orbit M4/M5 runtime repair rolled back from: ${backup}`);
