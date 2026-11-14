// Validates the content/ folder: JSON syntax and shape, referenced files, counts,
// block comment balance, encoding (UTF-8 without BOM) and LF line endings.
// Usage: node content/tools/validate.mjs   (exit code 1 on any error)
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve, relative, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const base = resolve(here, '..');
const errors = [];
const err = (m) => errors.push(m);

function walk(dir) {
  return readdirSync(dir).flatMap((f) => {
    const p = join(dir, f);
    return statSync(p).isDirectory() ? walk(p) : [p];
  });
}

// Encoding and line endings for every file.
for (const file of walk(base)) {
  const buf = readFileSync(file);
  const rel = relative(base, file);
  if (buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf) err(`${rel}: has UTF-8 BOM`);
  if (buf.includes(0x0d)) err(`${rel}: contains CR (use LF line endings)`);
  const text = buf.toString('utf8');
  if (text.includes('\uFFFD')) err(`${rel}: invalid UTF-8`);
}

const readJson = (rel) => {
  try {
    return JSON.parse(readFileSync(resolve(base, rel), 'utf8'));
  } catch (e) {
    err(`${rel}: ${e.message}`);
    return null;
  }
};

// Block markup balance: every opener needs a matching closer unless self-closing.
function checkBlocks(rel) {
  const html = readFileSync(resolve(base, rel), 'utf8');
  const re = /<!--\s+(\/)?wp:([a-z][a-z0-9_-]*(?:\/[a-z][a-z0-9_-]*)?)(\s+\{.*?\})?\s+(\/)?-->/gs;
  const stack = [];
  let m, count = 0;
  while ((m = re.exec(html))) {
    count++;
    const [, closing, name, attrs, selfClosing] = m;
    if (attrs) {
      try { JSON.parse(attrs.trim()); } catch { err(`${rel}: invalid JSON attributes on wp:${name}: ${attrs.trim()}`); }
    }
    if (selfClosing) continue;
    if (!closing) { stack.push(name); continue; }
    const open = stack.pop();
    if (open !== name) err(`${rel}: closer wp:${name} does not match opener wp:${open ?? '(none)'}`);
  }
  if (stack.length) err(`${rel}: unclosed blocks: ${stack.join(', ')}`);
  const stray = html.replace(re, '').match(/<!--\s*\/?wp:/g);
  if (stray) err(`${rel}: malformed block comment(s)`);
  return count;
}

const lang = (o, key, l) => {
  const v = o?.[l];
  if (!v || typeof v.title !== 'string' || typeof v.slug !== 'string' || typeof v.file !== 'string') {
    err(`${key}.${l}: needs title, slug, file`);
    return;
  }
  if (!existsSync(resolve(base, v.file))) err(`${key}.${l}: missing file ${v.file}`);
  else checkBlocks(v.file);
};

// pages.json
const pages = readJson('pages.json');
const PAGE_KEYS = ['home', 'members', 'news', 'history', 'gallery', 'privacy'];
if (pages) {
  const keys = pages.map((p) => p.key);
  if (JSON.stringify(keys) !== JSON.stringify(PAGE_KEYS)) err(`pages.json keys/order: ${keys.join(',')}`);
  for (const p of pages) {
    if (typeof p.front_page !== 'boolean' || typeof p.posts_page !== 'boolean') err(`page ${p.key}: front_page/posts_page must be boolean`);
    lang(p, `page ${p.key}`, 'et');
    lang(p, `page ${p.key}`, 'en');
  }
  if (pages.filter((p) => p.front_page).length !== 1) err('pages.json: exactly one front_page required');
  if (pages.filter((p) => p.posts_page).length !== 1) err('pages.json: exactly one posts_page required');
}

// posts.json
const posts = readJson('posts.json');
if (posts) {
  for (const p of posts) {
    if (!/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(p.date)) err(`post ${p.key}: bad date ${p.date}`);
    if (!['uudised', 'kuulutused'].includes(p.category)) err(`post ${p.key}: bad category ${p.category}`);
    lang(p, `post ${p.key}`, 'et');
    lang(p, `post ${p.key}`, 'en');
  }
}

// members.json
const members = readJson('members.json');
if (members) {
  for (const [i, m] of members.entries()) {
    const keys = Object.keys(m).sort().join(',');
    if (keys !== 'company,name,website') err(`member ${i}: keys ${keys}`);
    if (!m.name || !m.company) err(`member ${i}: empty name/company`);
    if (m.website && !/^https?:\/\/[a-z0-9.-]+\.[a-z]{2,}(\/\S*)?$/i.test(m.website)) err(`member ${i}: bad URL ${m.website}`);
  }
}

// albums.json
const albums = readJson('albums.json');
if (albums) {
  const ids = new Set();
  for (const a of albums) {
    if (typeof a.title !== 'string' || !a.title) err(`album ${a.legacy_id}: empty title`);
    if (a.date !== '' && !/^\d{4}-\d{2}-\d{2}$/.test(a.date)) err(`album ${a.legacy_id}: bad date ${a.date}`);
    if (!Number.isInteger(a.legacy_id)) err(`album: legacy_id not int: ${a.legacy_id}`);
    ids.add(a.legacy_id);
  }
  if (ids.size !== albums.length) err('albums.json: duplicate legacy_id');
}

// site.json
const site = readJson('site.json');
if (site) {
  const need = ['organisation', 'street', 'postcode', 'city', 'person', 'phone', 'phone_e164', 'email_person', 'email_general'];
  for (const k of need) if (!site.contact?.[k]) err(`site.json: contact.${k} missing`);
  for (const l of ['et', 'en']) if (!site[l]?.title || !site[l]?.tagline) err(`site.json: ${l}.title/tagline missing`);
  for (const c of ['uudised', 'kuulutused']) {
    const v = site.categories?.[c];
    if (!v?.et || !v?.en || !v?.en_slug) err(`site.json: categories.${c} incomplete`);
  }
}

// Pattern and widgets.
const pattern = 'patterns/history-table.html';
checkBlocks(pattern);
const tableRows = (readFileSync(resolve(base, pattern), 'utf8').match(/<tr><td>/g) || []).length;
for (const w of ['footer-contact.et', 'footer-contact.en', 'footer-about.et', 'footer-about.en']) {
  const rel = `widgets/${w}.html`;
  if (!existsSync(resolve(base, rel))) err(`missing ${rel}`);
  else checkBlocks(rel);
}
for (const rel of ['widgets/footer-contact.et.html', 'widgets/footer-contact.en.html']) {
  const html = readFileSync(resolve(base, rel), 'utf8');
  for (const needle of ['ak-contact', 'Kesk 11', '68203 Valga', 'Anneli Kattai', 'href="tel:+3725280802"', 'href="mailto:anneli.kattai@valgamaa.ee"', 'href="mailto:ariklubi@valgamaa.ee"']) {
    if (!html.includes(needle)) err(`${rel}: missing ${needle}`);
  }
}

// Expected counts.
const expect = (label, actual, wanted) => {
  console.log(`${label.padEnd(14)} ${actual}${actual === wanted ? '' : `  (expected ${wanted})`}`);
  if (actual !== wanted) err(`${label}: ${actual} != ${wanted}`);
};
expect('pages', pages?.length ?? 0, 6);
expect('posts', posts?.length ?? 0, 4);
expect('members', members?.length ?? 0, 46);
expect('member sites', members?.filter((m) => m.website).length ?? 0, 7);
expect('albums', albums?.length ?? 0, 76);
expect('history rows', tableRows, 270);

if (errors.length) {
  console.error(`\n${errors.length} error(s):`);
  for (const e of errors) console.error('  - ' + e);
  process.exit(1);
}
console.log('\nOK: all content files valid.');
