import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(process.argv[2] || path.join(import.meta.dirname, '..', '..'));

function read(relative) {
    const file = path.join(root, relative);
    if (!fs.existsSync(file)) throw new Error(`Missing required M5 file: ${relative}`);
    return fs.readFileSync(file, 'utf8');
}

function walk(dir) {
    if (!fs.existsSync(dir)) return [];
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const target = path.join(dir, entry.name);
        return entry.isDirectory() ? walk(target) : [target];
    });
}

const web = read('routes/web.php');
const entry = read('resources/js/admin-console/index.js');
const runtime = read('resources/js/admin-console/support-m5.js');
const contract = read('resources/js/admin-console/support-routes.generated.js');
const indexView = read('resources/views/admin/console/operations/support/index.blade.php');
const showView = read('resources/views/admin/console/operations/support/show.blade.php');

if ((web.match(/admin_ui_m5\.php/g) || []).length !== 1) throw new Error('routes/web.php must include admin_ui_m5.php exactly once.');
if ((entry.match(/import ['"]\.\/support-m5\.js['"];?/g) || []).length !== 1) throw new Error('admin-console/index.js must import support-m5.js exactly once.');
if (!runtime.includes('[data-orbit-view^="support-"]')) throw new Error('M5 runtime is not isolated to support pages.');
if (runtime.includes('.innerHTML') || runtime.includes('document.write') || runtime.includes('eval(')) throw new Error('Unsafe DOM execution primitive found in M5 runtime.');
if (!runtime.includes("close.type = 'button'") || !runtime.includes("cancel.type = 'button'") || !runtime.includes('form.reportValidity()')) throw new Error('M5 dialog safety controls are incomplete.');
if (!contract.includes('ticketList') || !contract.includes('ticketShow')) throw new Error('Generated support route contract is missing required list/show operations.');
if (!indexView.includes('data-orbit-view="support-index"') || !showView.includes('data-orbit-view="support-show"')) throw new Error('M5 Blade view markers are incomplete.');
if (!showView.includes('never consumer-visible')) throw new Error('Internal note privacy warning is missing.');

const bladeFiles = walk(path.join(root, 'resources', 'views')).filter((file) => file.endsWith('.blade.php'));
const placeholderFiles = bladeFiles.filter((file) => /__ORBIT_[A-Z0-9_]+__/.test(fs.readFileSync(file, 'utf8')));
if (placeholderFiles.length) throw new Error(`Installer placeholders remain in live Blade views: ${placeholderFiles.map((file) => path.relative(root, file)).join(', ')}`);

const sidebarCandidates = bladeFiles.filter((file) => {
    const source = fs.readFileSync(file, 'utf8');
    return source.includes('orbit-nav-item') && source.includes('admin.console.operations.support.index');
});
if (sidebarCandidates.length !== 1) throw new Error(`Expected exactly one canonical sidebar with Support navigation, found ${sidebarCandidates.length}.`);
const sidebar = fs.readFileSync(sidebarCandidates[0], 'utf8');
for (const required of ['admin.console.operations.moderation.index', 'Safety / SOS', '<span>Users</span>', '<span>Circles</span>', '<span>Support</span>']) {
    if (!sidebar.includes(required)) throw new Error(`Canonical sidebar lost required previous navigation: ${required}`);
}
if ((sidebar.match(/admin\.console\.operations\.support\.index/g) || []).length !== 1) throw new Error('Support navigation must occur exactly once in the canonical sidebar.');

console.log('Orbit M5 support UI static contract verification passed.');
