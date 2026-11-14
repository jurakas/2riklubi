// Builds content/patterns/history-table.html from legacy-pages/ajalugu.md.
// Usage: node content/tools/build-history.mjs
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..', '..');
const src = readFileSync(resolve(root, 'legacy-pages/ajalugu.md'), 'utf8');
const out = resolve(root, 'content/patterns/history-table.html');

// Typo fixes; keep in sync with content/CHANGES.md.
const FIXES = [
  [/Kutuurikeskus/g, 'Kultuurikeskus'],
  [/Vorimehe/g, 'Voorimehe'],
  [/tutvvumine/g, 'tutvumine'],
  [/\bEesi\b/g, 'Eesti'],
];

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const rows = [];
const applied = [];
for (const line of src.split(/\r?\n/)) {
  const m = line.match(/^\|\s*(\d{4})\s*\|([^|]*)\|([^|]*)\|([^|]*)\|\s*$/);
  if (!m) continue;
  const cells = [m[1], m[2], m[3], m[4]].map((c) => {
    let v = c.trim();
    for (const [re, to] of FIXES) {
      const fixed = v.replace(re, to);
      if (fixed !== v) applied.push(`${m[1]} ${m[2].trim()}: "${v}" -> "${fixed}"`);
      v = fixed;
    }
    return v;
  });
  rows.push(cells);
}

rows.reverse(); // newest first

const body = rows
  .map((r) => '<tr>' + r.map((c) => `<td>${esc(c)}</td>`).join('') + '</tr>')
  .join('');

const html =
  '<!-- wp:paragraph -->\n' +
  '<p>Klubi kohtumised 1994–2025 / Club meetings 1994–2025</p>\n' +
  '<!-- /wp:paragraph -->\n\n' +
  '<!-- wp:table {"hasFixedLayout":false,"className":"is-style-stripes"} -->\n' +
  '<figure class="wp-block-table is-style-stripes"><table><thead><tr>' +
  '<th>Aasta / Year</th><th>Kuu / Month</th><th>Koht / Venue</th><th>Külalised, teema / Guests, topic</th>' +
  '</tr></thead><tbody>' +
  body +
  '</tbody></table></figure>\n' +
  '<!-- /wp:table -->\n';

writeFileSync(out, html, 'utf8');
console.log(`rows: ${rows.length}`);
console.log(`first: ${rows[0].slice(0, 2).join(' ')} | last: ${rows[rows.length - 1].slice(0, 2).join(' ')}`);
console.log('typo fixes applied:');
for (const a of applied) console.log('  ' + a);
