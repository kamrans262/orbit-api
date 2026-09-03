import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(scriptDir, '..', '..');
const backup = path.resolve(process.argv[2] || '');
if (!process.argv[2] || !fs.existsSync(path.join(backup, 'manifest.json'))) {
    throw new Error('Usage: node rollback-runtime-repair-v3.mjs <backup-path>');
}
const manifest = JSON.parse(fs.readFileSync(path.join(backup, 'manifest.json'), 'utf8'));
for (const item of manifest) {
    const relative = item.path.replaceAll('/', path.sep);
    const target = path.join(root, relative);
    const saved = path.join(backup, relative);
    if (item.existed) {
        fs.mkdirSync(path.dirname(target), { recursive: true });
        fs.copyFileSync(saved, target);
    } else if (fs.existsSync(target)) fs.rmSync(target, { force: true });
}
console.log(`Restored Orbit M4/M5 runtime files from ${backup}`);
