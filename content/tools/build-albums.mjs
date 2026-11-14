// Builds content/albums.json from the album list table in legacy-pages/galerii.md.
// Usage: node content/tools/build-albums.mjs
//
// Date rules (from the title only):
//   full day ("4.11.2024", "4.10.19", "16-17.09.2010", "14.-15. jaanuar 2010") -> that day (first day of a range)
//   month only ("09.2019", "2010 märts", "mai 2016", "2008. jaanuar")          -> YYYY-MM-01
//   year only or no date                                                       -> ""
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..', '..');
const src = readFileSync(resolve(root, 'legacy-pages/galerii.md'), 'utf8');
const out = resolve(root, 'content/albums.json');

const MONTHS = {
  jaanuar: 1, veebruar: 2, märts: 3, aprill: 4, mai: 5, juuni: 6,
  juuli: 7, august: 8, september: 9, oktoober: 10, november: 11, detsember: 12,
};
const MN = Object.keys(MONTHS).join('|');

const pad = (n) => String(n).padStart(2, '0');
const year = (y) => (y.length === 2 ? 2000 + Number(y) : Number(y));
const ymd = (y, m, d) => {
  const Y = year(String(y)), M = Number(m), D = Number(d);
  if (M < 1 || M > 12 || D < 1 || D > 31) return '';
  return `${Y}-${pad(M)}-${pad(D)}`;
};

function parseDate(title) {
  const t = title.toLowerCase();
  let m;
  // 16-17.09.2010
  if ((m = t.match(/\b(\d{1,2})-\d{1,2}\.(\d{1,2})\.(\d{4})\b/))) return ymd(m[3], m[2], m[1]);
  // 4.11.2024, 02.03.2024, 4.10.19
  if ((m = t.match(/\b(\d{1,2})\.\s?(\d{1,2})\.\s?(\d{4}|\d{2})\b/))) return ymd(m[3], m[2], m[1]);
  // 14.-15. jaanuar 2010 / 14. jaanuar 2010
  if ((m = t.match(new RegExp(`\\b(\\d{1,2})\\.(?:\\s?-\\s?\\d{1,2}\\.)?\\s*(${MN})\\s+(\\d{4})`))))
    return ymd(m[3], MONTHS[m[2]], m[1]);
  // 09.2019, 02. 2013.
  if ((m = t.match(/\b(\d{1,2})\.\s?(\d{4})\b/))) return ymd(m[2], m[1], 1);
  // 2010 märts, 2008. jaanuar
  if ((m = t.match(new RegExp(`\\b(\\d{4})\\.?\\s+(${MN})\\b`)))) return ymd(m[1], MONTHS[m[2]], 1);
  // mai 2016, september 2009
  if ((m = t.match(new RegExp(`\\b(${MN})\\s+(\\d{4})\\b`)))) return ymd(m[2], MONTHS[m[1]], 1);
  return '';
}

const albums = [];
let inList = false;
for (const line of src.split(/\r?\n/)) {
  if (line.startsWith('## Albumite loend')) { inList = true; continue; }
  if (inList && line.startsWith('## ')) break;
  if (!inList) continue;
  const m = line.match(/^\|\s*(\d+)\s*\|\s*(.+?)\s*\|\s*\d+\s*\|\s*$/);
  if (!m) continue;
  const title = m[2];
  albums.push({ title, date: parseDate(title), legacy_id: Number(m[1]) });
}

writeFileSync(out, JSON.stringify(albums, null, 2) + '\n', 'utf8');
console.log(`albums: ${albums.length}`);
for (const a of albums) console.log(`${String(a.legacy_id).padStart(2)}  ${(a.date || '-').padEnd(10)}  ${a.title}`);
