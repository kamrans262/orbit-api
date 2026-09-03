import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const read = (file) => fs.readFileSync(file, 'utf8');
const viewRoot = path.join(root, 'resources', 'views');

const bladeFiles = [];
const walk = (dir) => {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full);
    else if (entry.name.endsWith('.blade.php')) bladeFiles.push(full);
  }
};
walk(viewRoot);

const sosCandidates = bladeFiles
  .map((file) => [file, read(file)])
  .filter(([, source]) => /data-orbit-view=["']sos-(?:index|show)["']/.test(source));
if (sosCandidates.length < 1) throw new Error('Canonical M3 SOS view could not be found.');

const sosSource = sosCandidates[0][1];
const layout = sosSource.match(/@extends\(["']([^"']+)["']\)/)?.[1];
const section = sosSource.match(/@section\(["']([^"']+)["']\)/)?.[1];
if (!layout || !section) throw new Error('Could not derive canonical layout/section from M3 SOS.');
if (/__ORBIT_(?:LAYOUT|SECTION)__/.test(sosSource)) throw new Error('Canonical M3 SOS view itself contains an installer placeholder.');

const moderationRoot = path.join(viewRoot, 'admin', 'console', 'operations', 'moderation');
const expected = [
  ['index.blade.php', 'moderation-index'],
  [path.join('reports', 'show.blade.php'), 'moderation-report-show'],
  [path.join('appeals', 'index.blade.php'), 'moderation-appeals-index'],
  [path.join('appeals', 'show.blade.php'), 'moderation-appeal-show'],
  [path.join('risk', 'index.blade.php'), 'moderation-risk-index'],
  [path.join('risk', 'show.blade.php'), 'moderation-risk-show'],
];

for (const [relative, marker] of expected) {
  const file = path.join(moderationRoot, relative);
  if (!fs.existsSync(file)) throw new Error(`Missing installed M4 view: ${relative}`);
  const source = read(file);
  if (/__ORBIT_[A-Z0-9_]+__/.test(source)) throw new Error(`Unresolved installer placeholder in ${relative}.`);
  const extendsValue = source.match(/@extends\(["']([^"']+)["']\)/)?.[1];
  const sectionValue = source.match(/@section\(["']([^"']+)["']\)/)?.[1];
  if (extendsValue !== layout) throw new Error(`${relative} extends ${extendsValue || '(missing)'} instead of canonical ${layout}.`);
  if (sectionValue !== section) throw new Error(`${relative} uses section ${sectionValue || '(missing)'} instead of canonical ${section}.`);
  if (!source.includes(`data-orbit-view="${marker}"`) && !source.includes(`data-orbit-view='${marker}'`)) {
    throw new Error(`Expected runtime marker ${marker} is missing from ${relative}.`);
  }
  if (!/@endsection\b/.test(source)) throw new Error(`Missing @endsection in ${relative}.`);
}

const allProjectPlaceholders = [];
for (const file of bladeFiles) {
  const source = read(file);
  if (/__ORBIT_[A-Z0-9_]+__/.test(source)) allProjectPlaceholders.push(path.relative(root, file));
}
if (allProjectPlaceholders.length) {
  throw new Error(`Installer placeholders remain in live Blade views: ${allProjectPlaceholders.join(', ')}`);
}

console.log(`M4 runtime view contract passed: 6 views, layout=${layout}, section=${section}, zero live __ORBIT_*__ placeholders.`);
