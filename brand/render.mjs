// Regenerates every logo SVG and PNG in brand/ from brand/source/.
// Usage: cd brand; npm install; node render.mjs [--preview <dir>]
//   --preview <dir>  also writes small previews (200 px wide, on backgrounds) for visual checks.

import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import opentype from 'opentype.js';
import { Resvg } from '@resvg/resvg-js';

const ROOT = dirname(fileURLToPath(import.meta.url));
const SRC = join(ROOT, 'source');

const COLORS = {
  navy900: '#010736',
  navy700: '#0D1C42',
  navy500: '#22396F',
  cream: '#FCF1D0',
};

const WORDMARK = 'Valgamaa Äriklubi';
const LINES = ['Valgamaa', 'Äriklubi'];
const CLEAR = 0.25; // minimum clear space around the shield, as a fraction of its width

// ---------------------------------------------------------------------------
// Coat of arms: nested unchanged as an inner <svg> with its own viewBox.
// Only non-rendering editor data (Inkscape/Sodipodi metadata) is dropped.
// ---------------------------------------------------------------------------
const armsSource = readFileSync(join(SRC, 'Valgamaa_vapp.svg'), 'utf8');
const rootTag = armsSource.match(/<svg\b[^>]*>/)[0];
const viewBox = rootTag.match(/\bviewBox="([^"]+)"/)[1];
const [, , VB_W, VB_H] = viewBox.trim().split(/[\s,]+/).map(Number);
const ARMS_RATIO = VB_W / VB_H; // width / height
const armsInner = armsSource
  .slice(armsSource.indexOf(rootTag) + rootTag.length, armsSource.lastIndexOf('</svg>'))
  .replace(/<sodipodi:namedview\b[\s\S]*?(\/>|<\/sodipodi:namedview>)/g, '')
  .replace(/<metadata\b[\s\S]*?<\/metadata>/g, '')
  .replace(/\s(?:sodipodi|inkscape):[\w-]+="[^"]*"/g, '')
  .replace(/^\s*\n/gm, '')
  .trim();

function shield(x, y, height) {
  const width = height * ARMS_RATIO;
  return {
    width,
    height,
    svg:
      `<svg x="${r(x)}" y="${r(y)}" width="${r(width)}" height="${r(height)}" ` +
      `viewBox="${viewBox}" preserveAspectRatio="xMidYMid meet">\n${armsInner}\n</svg>`,
  };
}

// ---------------------------------------------------------------------------
// Wordmark: Source Serif 4 SemiBold, converted to outlines.
// ---------------------------------------------------------------------------
const font = opentype.loadSync(join(SRC, 'fonts', 'SourceSerif4-Semibold.ttf'));
const UPM = font.unitsPerEm;
const CAP = font.tables.os2.sCapHeight / UPM; // cap height in em

for (const ch of new Set(WORDMARK.replace(/\s/g, ''))) {
  if (font.charToGlyph(ch).index === 0) throw new Error(`Font has no glyph for "${ch}"`);
}

// Returns outline data for `text` set at `size` with its baseline at (0, 0).
function measure(text, size) {
  const path = font.getPath(text, 0, 0, size, { kerning: true });
  const bb = path.getBoundingBox();
  return { path, bb, width: bb.x2 - bb.x1 };
}

// Places `text` so that its ink starts at x and its baseline is at y.
function textPath(text, size, x, y, fill) {
  const { bb } = measure(text, size);
  const path = font.getPath(text, x - bb.x1, y, size, { kerning: true });
  return `<path fill="${fill}" d="${path.toPathData(2)}"/>`;
}

function r(n) {
  return Math.round(n * 100) / 100;
}

function doc(width, height, body, { title = WORDMARK } = {}) {
  return (
    `<?xml version="1.0" encoding="UTF-8"?>\n` +
    `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" ` +
    `viewBox="0 0 ${width} ${height}" role="img" aria-label="${title}">\n` +
    `<title>${title}</title>\n${body}\n</svg>\n`
  );
}

// ---------------------------------------------------------------------------
// Layouts
// ---------------------------------------------------------------------------

// Horizontal, 1200x300: shield left, single-line wordmark right, cap height
// vertically centred on the shield.
const H = { W: 1200, H: 300, shieldH: 208, gapFactor: 0.34 };
function horizontal(textColor) {
  const sh = shield(0, 0, H.shieldH);
  const margin = (H.H - H.shieldH) / 2;
  const s = shield(margin, margin, H.shieldH);
  const textX = margin + sh.width + sh.width * H.gapFactor;
  const avail = H.W - margin - textX;
  const size = avail / measure(WORDMARK, 1).width;
  const baseline = H.H / 2 + (CAP * size) / 2;
  const { bb } = measure(WORDMARK, size);
  if (baseline + bb.y1 < 0 || baseline + bb.y2 > H.H) throw new Error('Wordmark overflows vertically');
  const body = `${s.svg}\n${textPath(WORDMARK, size, textX, baseline, textColor)}`;
  return { svg: doc(H.W, H.H, body), info: { margin, shieldW: sh.width, textX, size, baseline } };
}

// Stacked, 800x800: shield on top, two centred lines of text below.
function stacked(textColor) {
  const W = 800;
  const shieldH = 330;
  const shieldW = shieldH * ARMS_RATIO;
  const gap = shieldW * CLEAR; // shield to the ink top of the first line
  const leading = 1.12;
  const maxTextW = W - 2 * 60;
  let size = 132;
  const widest = () => Math.max(...LINES.map((l) => measure(l, size).width));
  while (widest() > maxTextW) size -= 1;

  const m1 = measure(LINES[0], size);
  const m2 = measure(LINES[1], size);
  const base1Rel = -m1.bb.y1; // baseline of line 1 relative to its ink top
  const base2Rel = base1Rel + size * leading;
  const textBlockH = base2Rel + m2.bb.y2;
  const blockH = shieldH + gap + textBlockH;
  const top = (W - blockH) / 2;
  if (top < shieldW * CLEAR) throw new Error('Stacked layout too tall');

  const s = shield((W - shieldW) / 2, top, shieldH);
  const y0 = top + shieldH + gap;
  const lines = [
    textPath(LINES[0], size, (W - m1.width) / 2, y0 + base1Rel, textColor),
    textPath(LINES[1], size, (W - m2.width) / 2, y0 + base2Rel, textColor),
  ];
  return { svg: doc(W, W, `${s.svg}\n${lines.join('\n')}`), info: { shieldH, shieldW, size, top } };
}

// Site icon, 512x512: shield centred on a navy rounded square.
function siteIcon() {
  const W = 512;
  const shieldH = 352;
  const shieldW = shieldH * ARMS_RATIO;
  const s = shield((W - shieldW) / 2, (W - shieldH) / 2, shieldH);
  const body = `<rect width="${W}" height="${W}" rx="96" ry="96" fill="${COLORS.navy900}"/>\n${s.svg}`;
  return doc(W, W, body, { title: `${WORDMARK} – site icon` });
}

// Open Graph image, 1200x630: the on-dark horizontal logo, scaled to 85 %, centred on navy.
function ogImage(horizontalOnDarkSvg) {
  const W = 1200;
  const Hh = 630;
  const scale = 0.85;
  const w = H.W * scale;
  const h = H.H * scale;
  const inner = horizontalOnDarkSvg
    .replace(/<\?xml[^>]*>\s*/, '')
    .replace(/<svg\b[^>]*>/, `<svg x="${r((W - w) / 2)}" y="${r((Hh - h) / 2)}" width="${r(w)}" height="${r(h)}" viewBox="0 0 ${H.W} ${H.H}">`)
    .replace(/<title>[\s\S]*?<\/title>\s*/, '');
  return doc(W, Hh, `<rect width="${W}" height="${Hh}" fill="${COLORS.navy900}"/>\n${inner.trim()}`);
}

// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------
function png(svg, width, outFile, background) {
  const resvg = new Resvg(svg, {
    fitTo: { mode: 'width', value: width },
    background,
    font: { loadSystemFonts: false },
  });
  const img = resvg.render();
  writeFileSync(outFile, img.asPng());
  console.log(`  ${outFile.replace(ROOT, 'brand')}  ${img.width}x${img.height}`);
}

function save(file, contents) {
  writeFileSync(join(ROOT, file), contents);
  console.log(`  brand/${file.replace(/\\/g, '/')}`);
}

const light = horizontal(COLORS.navy900);
const dark = horizontal(COLORS.cream);
const stack = stacked(COLORS.navy900);
const icon = siteIcon();
const og = ogImage(dark.svg);

console.log('Layout:', { horizontal: light.info, stacked: stack.info });
console.log('SVG:');
save('source/logo-horizontal.svg', light.svg);
save('source/logo-stacked.svg', stack.svg);
save('logo-horizontal-on-dark.svg', dark.svg);

console.log('PNG:');
png(dark.svg, 1200, join(ROOT, 'logo-horizontal-on-dark.png'));
png(light.svg, 1200, join(ROOT, 'logo-horizontal-on-light.png'));
png(stack.svg, 800, join(ROOT, 'logo-stacked-on-light.png'));
png(icon, 512, join(ROOT, 'site-icon-512.png'));
png(og, 1200, join(ROOT, 'og-image-1200x630.png'));

const previewIdx = process.argv.indexOf('--preview');
if (previewIdx !== -1) {
  const dir = process.argv[previewIdx + 1];
  mkdirSync(dir, { recursive: true });
  console.log('Previews:');
  png(dark.svg, 200, join(dir, 'horizontal-on-dark-200.png'), COLORS.navy900);
  png(light.svg, 200, join(dir, 'horizontal-on-light-200.png'), COLORS.cream);
  png(dark.svg, 1200, join(dir, 'horizontal-on-dark-bg.png'), COLORS.navy900);
  png(stack.svg, 800, join(dir, 'stacked-on-cream.png'), COLORS.cream);
  png(icon, 32, join(dir, 'site-icon-32.png'));
}
