// Builds content/members.json from the member table in legacy-pages/liikmed.md.
// Usage: node content/tools/build-members.mjs
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..', '..');
const src = readFileSync(resolve(root, 'legacy-pages/liikmed.md'), 'utf8');
const out = resolve(root, 'content/members.json');

// Results of HEAD checks on 2026-11-14 (see content/CHANGES.md).
const URLS = {
  'http://www.reff.ee': 'https://www.reff.ee',
  'http://www.pindi.ee': 'https://www.pindi.ee',
  'http://www.kmprojekt.ee': 'https://www.kmprojekt.ee',
  'http://www.astelpajutooted.ee': 'http://www.astelpajutooted.ee', // domain does not resolve (http or https)
  'http://www//taimetee.ee': 'https://www.taimetee.ee',
  'http://www.vester.ee': 'https://www.vester.ee',
  'http://www.elektroman.ee': 'https://www.elektroman.ee',
};

const members = [];
for (const line of src.split(/\r?\n/)) {
  const m = line.match(/^\|\s*\d+\s*\|([^|]*)\|([^|]*)\|([^|]*)\|/);
  if (!m) continue;
  const web = m[3].trim();
  if (web && !(web in URLS)) throw new Error(`Unmapped website: ${web}`);
  members.push({ name: m[1].trim(), company: m[2].trim(), website: web ? URLS[web] : '' });
}

writeFileSync(out, JSON.stringify(members, null, 2) + '\n', 'utf8');
console.log(`members: ${members.length}, with website: ${members.filter((x) => x.website).length}`);
