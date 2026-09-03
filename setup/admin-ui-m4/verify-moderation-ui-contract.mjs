import fs from 'node:fs';
import path from 'node:path';
const root=process.cwd();
const js=fs.readFileSync(path.join(root,'resources/js/admin-console/moderation-m4.js'),'utf8');
const routes=fs.readFileSync(path.join(root,'resources/js/admin-console/moderation-routes.generated.js'),'utf8');
const mojibake=/[\u00c2\u00c3\u00e2\u00ef\u00f0\ufffd]/u;
const required=['reportList','reportShow','reportAssign','reportWorkflow','reportNote','reportEnforce','appealList','appealShow','appealAssign','appealReview','appealSecondReview','riskList','riskShow','riskCreate','riskResolve','reauthenticate'];
for(const key of required){if(!routes.includes(`\"${key}\"`)) throw new Error(`Generated M4 browser route contract is missing ${key}.`)}
for(const needle of ["close.type = 'button'","cancel.type = 'button'","submit.type = 'submit'","form.reportValidity()",'textContent','Request ID','reauthenticate']){if(!js.includes(needle)) throw new Error(`M4 JS contract missing: ${needle}`)}
if(js.includes('.innerHTML')) throw new Error('M4 browser runtime must not render API data through innerHTML.');
const viewRoot=path.join(root,'resources/views/admin/console/operations/moderation');
const files=[]; const walk=(d)=>fs.readdirSync(d,{withFileTypes:true}).forEach(e=>e.isDirectory()?walk(path.join(d,e.name)):files.push(path.join(d,e.name))); walk(viewRoot);
for(const file of files){const s=fs.readFileSync(file,'utf8');if(s.includes('__ORBIT_LAYOUT__')||s.includes('__ORBIT_SECTION__')) throw new Error(`Canonical Blade placeholder remains in ${file}`);if(mojibake.test(s)) throw new Error(`Mojibake / broken UTF-8 text detected in ${file}`);for(const forbidden of ['last_latitude','last_longitude','private_key','push_token']) if(s.includes(forbidden)) throw new Error(`Sensitive field token ${forbidden} appears in ${file}`)}
const allViews=[]; const walkViews=(d)=>fs.readdirSync(d,{withFileTypes:true}).forEach(e=>e.isDirectory()?walkViews(path.join(d,e.name)):e.name.endsWith('.blade.php')&&allViews.push(path.join(d,e.name))); walkViews(path.join(root,'resources/views'));
const sidebar=allViews.map(file=>[file,fs.readFileSync(file,'utf8')]).find(([,s])=>s.includes('orbit-nav-item')&&s.includes('admin.console.operations.moderation.index'));
if(!sidebar) throw new Error('Canonical M4 sidebar could not be located.');
const canonicalIcons=[
  ['Dashboard','⌂'],['Users','◎'],['Circles','◌'],['Safety / SOS','✚'],['Moderation & Reports','◇'],['Support','?'],
  ['Subscriptions & Payments','$'],['Advertising','▣'],['Notifications & Announcements','◈'],['Analytics','⌁'],
  ['Privacy & Compliance','⚿'],['Security','◆'],['Content','▤'],['Feature Flags & Configuration','⚙'],
  ['System Operations','◫'],['Audit Logs','≡'],['Administrators','♙'],
];
const decode=s=>s.replaceAll('&amp;','&').replace(/\s+/g,' ').trim();
for(const [label,icon] of canonicalIcons){
  const escaped=label.replace(/[.*+?^${}()|[\]\\]/g,'\\$&').replaceAll(' & ','\\s*&(?:amp;)?\\s*').replaceAll(' / ','\\s*/\\s*').replaceAll(' ','\\s+');
  const pattern=new RegExp(`<span\\s+class=["']orbit-nav-icon["'][^>]*>\\s*([^<]*)\\s*</span>\\s*<span(?:\\s+[^>]*)?>\\s*(${escaped})\\s*</span>`,'is');
  const match=sidebar[1].match(pattern);
  if(!match) throw new Error(`Canonical sidebar item/icon pair missing for ${label}.`);
  if(decode(match[1])!==icon) throw new Error(`Canonical sidebar icon for ${label} is broken: expected ${icon}, found ${decode(match[1]) || '(empty)'}.`);
}
if(mojibake.test([...sidebar[1].matchAll(/<span\s+class=["']orbit-nav-icon["'][^>]*>(.*?)<\/span>/gis)].map(m=>m[1]).join(''))) throw new Error(`Mojibake / broken UTF-8 icon glyphs detected in canonical sidebar: ${sidebar[0]}`);
const m4css=fs.readFileSync(path.join(root,'resources/css/admin-console-m4.css'),'utf8');
if(!m4css.includes('.orbit-sidebar .orbit-nav-icon{font-size:1.12rem')) throw new Error('M4 sidebar icon sizing contract is missing.');
console.log('Milestone 4 moderation UI static contract passed.');
