import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const viewRoot = path.join(root, 'resources', 'views');
const files = [];
const walk = (dir) => {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full);
    else if (entry.name.endsWith('.blade.php')) files.push(full);
  }
};
walk(viewRoot);

const sidebarCandidates = files
  .map((file) => [file, fs.readFileSync(file, 'utf8')])
  .filter(([, source]) => source.includes('orbit-sidebar__nav') && source.includes('admin.console.operations.moderation.index'));

if (sidebarCandidates.length !== 1) {
  throw new Error(`Expected one canonical M4 sidebar, found ${sidebarCandidates.length}.`);
}

const [sidebarFile, sidebar] = sidebarCandidates[0];
const canonicalIcons = [
  ['Dashboard', '⌂'], ['Users', '◎'], ['Circles', '◌'], ['Safety / SOS', '✚'],
  ['Moderation & Reports', '◇'], ['Support', '?'], ['Subscriptions & Payments', '$'],
  ['Advertising', '▣'], ['Notifications & Announcements', '◈'], ['Analytics', '⌁'],
  ['Privacy & Compliance', '⚿'], ['Security', '◆'], ['Content', '▤'],
  ['Feature Flags & Configuration', '⚙'], ['System Operations', '◫'],
  ['Audit Logs', '≡'], ['Administrators', '♙'],
];

const escape = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
for (const [label, icon] of canonicalIcons) {
  const labelPattern = escape(label)
    .replaceAll(' & ', '\\s*&(?:amp;)?\\s*')
    .replaceAll(' / ', '\\s*/\\s*')
    .replaceAll(' ', '\\s+');
  const pair = new RegExp(`<span\\s+class=["']orbit-nav-icon["'][^>]*>\\s*([^<]*)\\s*</span>\\s*<span(?:\\s+[^>]*)?>\\s*${labelPattern}\\s*</span>`, 'is');
  const matches = sidebar.match(pair);
  if (!matches) throw new Error(`Sidebar item/icon pair missing for ${label}.`);
  if (matches[1].trim() !== icon) throw new Error(`Sidebar icon mismatch for ${label}: ${matches[1].trim() || '(empty)'}.`);
}

for (const marker of ['Workspace', 'Core operations', 'Platform', 'orbit-sidebar__principle', 'orbit-sidebar__footer']) {
  if (!sidebar.includes(marker)) throw new Error(`Canonical sidebar structure marker missing: ${marker}.`);
}

const moderationLinks = (sidebar.match(/admin\.console\.operations\.moderation\.index/g) || []).length;
if (moderationLinks !== 1) throw new Error(`Expected exactly one Moderation route link, found ${moderationLinks}.`);

const themePair = /<span\s+class=["']orbit-nav-icon["'][^>]*>\s*([^<]*)\s*<\/span>\s*<span\s+data-theme-label[^>]*>\s*Theme\s*:/is.exec(sidebar);
if (!themePair || themePair[1].trim() !== '◐') throw new Error('Theme icon is missing or corrupt.');

const iconText = [...sidebar.matchAll(/<span\s+class=["']orbit-nav-icon["'][^>]*>([^<]*)<\/span>/gis)].map((m) => m[1]).join('');
if (/[\u00c2\u00c3\u00e2\u00ef\u00f0\ufffd]/u.test(iconText)) throw new Error(`Mojibake remains in ${sidebarFile}.`);

const index = fs.readFileSync(path.join(root, 'resources', 'js', 'admin-console', 'index.js'), 'utf8');
const web = fs.readFileSync(path.join(root, 'routes', 'web.php'), 'utf8');
if ((index.match(/moderation-m4\.js/g) || []).length !== 1) throw new Error('Canonical admin entry must contain exactly one M4 import.');
if ((web.match(/admin_ui_m4\.php/g) || []).length !== 1) throw new Error('Canonical web routes must contain exactly one M4 route include.');

const css = fs.readFileSync(path.join(root, 'resources', 'css', 'admin-console-m4.css'), 'utf8');
if (!css.includes('.orbit-sidebar .orbit-nav-icon{font-size:1.12rem')) throw new Error('Expected 1.12rem sidebar icon sizing rule is missing.');

console.log('M4 shared-shell recovery static contract passed.');
