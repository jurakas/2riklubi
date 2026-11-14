const fs = require('fs');
const path = require('path');
const RAW = __dirname;
const OUT = path.join(RAW, '..');
const BASE = 'https://ariklubi.riiska.ee/';
const read = f => fs.readFileSync(path.join(RAW, f), 'utf8');

const ENT = { nbsp: ' ', amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", otilde: 'õ', Otilde: 'Õ', auml: 'ä', Auml: 'Ä', ouml: 'ö', Ouml: 'Ö', uuml: 'ü', Uuml: 'Ü', scaron: 'š', Scaron: 'Š', zcaron: 'ž', Zcaron: 'Ž', ndash: '–', mdash: '—', bdquo: '„', ldquo: '“', rdquo: '”', laquo: '«', raquo: '»', hellip: '…', euro: '€', bull: '•', copy: '©', eacute: 'é', aring: 'å', rsquo: '’', lsquo: '‘', middot: '·', sect: '§', deg: '°', shy: '' };
const decode = s => s.replace(/&(#x?[0-9a-f]+|\w+);/gi, (m, e) => {
  if (e[0] === '#') return String.fromCodePoint(e[1].toLowerCase() === 'x' ? parseInt(e.slice(2), 16) : parseInt(e.slice(1), 10));
  return ENT[e] !== undefined ? ENT[e] : m;
});
const abs = u => /^(https?:|mailto:)/i.test(u) ? u : BASE + u.replace(/^\.?\//, '');
const text = h => decode(h.replace(/<[^>]+>/g, ' ')).replace(/\s+/g, ' ').trim();

function html2md(h) {
  let s = h.replace(/<script[\s\S]*?<\/script>/gi, '').replace(/<style[\s\S]*?<\/style>/gi, '');
  s = s.replace(/<img[^>]*src=["']?([^"'\s>]+)[^>]*>/gi, (m, u) => ` [pilt](${abs(u)}) `);
  s = s.replace(/<a[^>]*href=["']([^"']+)["'][^>]*>([\s\S]*?)<\/a>/gi, (m, u, t) => {
    const tt = text(t);
    if (!tt) return '';
    if (/^mailto:/i.test(u)) { const e = u.slice(7); return tt === e ? e : `${tt} (${e})`; }
    return `[${tt}](${abs(u)})`;
  });
  s = s.replace(/<(strong|b)\b[^>]*>/gi, '\u0002').replace(/<\/(strong|b)>/gi, '\u0003');
  s = s.replace(/<hr[^>]*>/gi, '\n\n---\n\n');
  s = s.replace(/<li[^>]*>/gi, '\n- ');
  s = s.replace(/<br\s*\/?>/gi, '\n');
  s = s.replace(/<\/?(p|div|tr|table|ul|ol|h\d)\b[^>]*>/gi, '\n\n');
  s = s.replace(/<[^>]+>/g, ' ');
  s = decode(s).replace(/\u00a0/g, ' ');
  let depth = 0;
  s = s.split('\n').map(line => {
    let out = '', bold = depth > 0, buf = '';
    const flush = () => { const t = buf.replace(/[ \t]+/g, ' '); if (bold && t.trim()) { const lead = t.match(/^\s*/)[0], trail = t.match(/\s*$/)[0]; out += `${lead}**${t.trim()}**${trail}`; } else out += t; buf = ''; };
    for (const ch of line) {
      if (ch === '\u0002') { flush(); depth++; bold = true; }
      else if (ch === '\u0003') { flush(); depth = Math.max(0, depth - 1); bold = depth > 0; }
      else buf += ch;
    }
    flush();
    return out.replace(/[ \t]+/g, ' ').trim().replace(/^·\s*/, '- ').replace(/\*\*\s*\*\*/g, ' ').replace(/[ \t]+/g, ' ').trim();
  }).join('\n');
  s = s.replace(/\n{3,}/g, '\n\n').trim();
  return s;
}

const fm = (url, title) => `---\nsource_url: ${url}\ntitle: ${title}\nextracted: 2026-11-14\n---\n\n`;
const cell = s => s.replace(/\|/g, '\\|');
const summary = {};

// ---------- LIIKMED ----------
{
  const list = read('a1.html');
  const members = [...list.matchAll(/href="\?a=1&liige=(\d+)" class="rida">([^<]+)</g)].map(m => ({ id: m[1], listName: m[2].replace(/\s+/g, ' ').trim() }));
  for (const m of members) {
    const h = read(`m${m.id}.html`);
    const blk = h.slice(h.indexOf('<div id="liige"'), h.indexOf('<!-- Row 7 -->'));
    m.name = text((blk.match(/class="liikmenimi">([\s\S]*?)<\/div>/) || [])[1] || m.listName);
    const img = blk.match(/class="foto"><img[^>]*src="([^"]+)"/);
    m.photo = img ? abs(img[1]) : '';
    const fa = blk.match(/(?:<a href="([^"]*)"[^>]*>)?<div class="firmanimi">([\s\S]*?)<\/div>/);
    m.company = fa ? text(fa[2]) : '';
    m.web = fa && fa[1] ? fa[1] : '';
    const j = blk.match(/<div class="jutt">([\s\S]*?)<\/div>\s*<\/td>/);
    m.desc = j ? html2md(j[1]) : '';
    const rest = blk.replace(/<div class="(liikmenimi|firmanimi|jutt|foto)">[\s\S]*?<\/div>/g, '');
    m.extra = text(rest);
  }
  let md = fm(BASE + '?a=1', 'Liikmed') + '# Valgamaa Äriklubi liikmete nimekiri\n\n';
  md += `Liikmeid nimekirjas: ${members.length}\n\n`;
  md += '| # | Nimi | Ettevõte | Koduleht | Foto | Lähteleht |\n|---|---|---|---|---|---|\n';
  members.forEach((m, i) => { md += `| ${i + 1} | ${cell(m.name)} | ${cell(m.company)} | ${m.web} | ${m.photo ? `[foto](${m.photo})` : ''} | [?a=1&liige=${m.id}](${BASE}?a=1&liige=${m.id}) |\n`; });
  const withDesc = members.filter(m => m.desc);
  if (withDesc.length) {
    md += '\n## Liikmete tutvustused\n\n';
    for (const m of withDesc) md += `### ${m.name}\n\n${m.desc}\n\n`;
  }
  md += '\n_Märkus: liikmete järjekord on sama mis algsel lehel (algul uuemad liikmed, seejärel tähestikuline nimekiri)._\n';
  fs.writeFileSync(path.join(OUT, 'liikmed.md'), md);
  summary.members = members;
}

// ---------- UUDISED / KUULUTUSED ----------
function items(listFile, param, prefix, ids) {
  const list = read(listFile);
  const listed = [...list.matchAll(new RegExp(`href="\\?a=\\d&${param}=(\\d+)" class="rida">• ([\\s\\S]*?) <font[^>]*>\\(([^)]+)\\)</font>`, 'g'))].map(m => ({ id: m[1], title: text(m[2]), date: m[3] }));
  for (const it of listed) {
    const h = read(`${prefix}${it.id}.html`);
    const t = h.match(/class="valituduudisepealkiri">([\s\S]*?)<\/div>/);
    it.fullTitle = t ? text(t[1]) : it.title;
    const s = h.indexOf('<div class="valituduudis">');
    let body = h.slice(s + '<div class="valituduudis">'.length);
    body = body.slice(0, body.search(/<\/div>\s*(<a href="\?a=|<\/div><\/td>)/));
    it.body = html2md(body);
  }
  return listed;
}
{
  const news = items('a2.html', 'uudis', 'n');
  let md = fm(BASE + '?a=2', 'Uudised') + '# Uudised\n\n';
  md += '| Pealkiri | Avaldatud (saidil näidatud kuupäev) | Lähteleht |\n|---|---|---|\n';
  news.forEach(n => { md += `| ${cell(n.title)} | ${n.date} | ${BASE}?a=2&uudis=${n.id} |\n`; });
  for (const n of news) md += `\n## ${n.fullTitle}\n\n- Allikas: ${BASE}?a=2&uudis=${n.id}\n- Kuupäev loendis: ${n.date}\n\n${n.body}\n`;
  fs.writeFileSync(path.join(OUT, 'uudised.md'), md);
  summary.news = news;

  const ann = items('a6.html', 'kuul', 'k');
  md = fm(BASE + '?a=6', 'Kuulutused') + '# Kuulutused\n\n';
  md += '| Pealkiri | Avaldatud (saidil näidatud kuupäev) | Lähteleht |\n|---|---|---|\n';
  ann.forEach(n => { md += `| ${cell(n.title)} | ${n.date} | ${BASE}?a=6&kuul=${n.id} |\n`; });
  for (const n of ann) {
    md += `\n## ${n.fullTitle}\n\n- Allikas: ${BASE}?a=6&kuul=${n.id}\n- Kuupäev loendis: ${n.date}\n`;
    if (n.id === '3') md += '\n> Märkus: algse lehe HTML sisaldab mitut lõiku kahekordselt (teine koopia ilma linkide ja rõhutusteta, vana WYSIWYG-redaktori jääk). Need korduvad lõigud on siin säilitatud nii, nagu need lähtekoodis on.\n';
    md += `\n${n.body}\n`;
  }
  fs.writeFileSync(path.join(OUT, 'kuulutused.md'), md);
  summary.ann = ann;
}

// ---------- AJALUGU ----------
{
  const h = read('a3.html');
  const tblStart = h.indexOf('<table width="591"');
  const tbl = h.slice(tblStart, h.indexOf('</table>', tblStart));
  const rows = [...tbl.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/g)].map(r => [...r[1].matchAll(/<td[^>]*>([\s\S]*?)<\/td>/g)].map(c => text(c[1])));
  const header = rows.shift();
  let year = '';
  const data = [];
  for (const r of rows) {
    if (r.every(c => !c)) continue;
    if (r[0]) year = r[0];
    data.push([year, r[1], r[2], r[3]]);
  }
  let md = fm(BASE + '?a=3', 'Ajalugu') + '# Valgamaa Äriklubi kohtumised läbi aegade\n\n';
  md += `Kohtumiste tabel (${data.length} rida, ${data[0][0]}–${data[data.length - 1][0]}). Algses tabelis on aasta märgitud ainult aasta esimesele reale; siin on see korratud igal real.\n\n`;
  md += `| ${header.join(' | ')} |\n|---|---|---|---|\n`;
  for (const r of data) md += `| ${r.map(cell).join(' | ')} |\n`;
  fs.writeFileSync(path.join(OUT, 'ajalugu.md'), md);
  summary.history = data;
}

// ---------- GALERII ----------
{
  const list = read('a4.html');
  const albums = [...list.matchAll(/href='\?a=4&ga=(\d+)' class='rida'>• ([^<]*)</g)].map(m => ({ id: m[1], title: decode(m[2]).replace(/\s+/g, ' ').trim() }));
  let total = 0;
  for (const a of albums) {
    const h = read(`g${a.id}.html`);
    const t = h.match(/class='valituduudisepealkiri'>([\s\S]*?)<\/div>/);
    a.fullTitle = t ? text(t[1]) : a.title;
    const s = h.indexOf("<div class='valituduudis'>");
    const body = s >= 0 ? h.slice(s, h.indexOf("<div class='uudistelist'>", s)) : '';
    a.images = [...body.matchAll(/<a href="([^"]+)"[^>]*title="([^"]*)"[^>]*>\s*<img src="([^"]+)"/g)].map(m => ({ full: abs(m[1]), caption: decode(m[2]).trim(), thumb: abs(m[3]) }));
    const other = body.replace(/<div class="pildivari">[\s\S]*?<\/div>/g, '');
    a.desc = html2md(other);
    total += a.images.length;
  }
  let md = fm(BASE + '?a=4', 'Galerii') + '# Galerii\n\n';
  md += `Algne juhis lehel: „Valige palun altpoolt vaatamiseks mõni galerii...“\n\nAlbumeid: ${albums.length}, pilte kokku: ${total}. Pilte ei ole alla laaditud; viited on algsetele URL-idele (täissuuruses pilt; pisipilt on sama nimega, lõpus „_“, nt \`1125_.jpg\`).\n\n`;
  md += '## Albumite loend\n\n| ga | Albumi nimi | Pilte |\n|---|---|---|\n';
  albums.forEach(a => { md += `| ${a.id} | ${cell(a.title)} | ${a.images.length} |\n`; });
  for (const a of albums) {
    md += `\n## ${a.fullTitle}\n\n- Allikas: ${BASE}?a=4&ga=${a.id}\n- Pilte: ${a.images.length}\n`;
    if (a.desc) md += `\n${a.desc}\n`;
    md += '\n';
    for (const im of a.images) md += `- [${im.full.split('/').pop()}](${im.full})${im.caption ? ` — ${im.caption}` : ''}\n`;
  }
  fs.writeFileSync(path.join(OUT, 'galerii.md'), md);
  summary.albums = albums;
}

fs.writeFileSync(path.join(RAW, 'summary.json'), JSON.stringify(summary, null, 1));
const s = summary;
console.log('members', s.members.length, 'withPhoto', s.members.filter(m => m.photo).length, 'withWeb', s.members.filter(m => m.web).length, 'withDesc', s.members.filter(m => m.desc).length, 'withExtra', s.members.filter(m => m.extra).map(m => m.id + ':' + m.extra));
console.log('noCompany', s.members.filter(m => !m.company).map(m => m.name));
console.log('news', s.news.map(n => n.id + ' ' + n.date), 'ann', s.ann.map(n => n.id + ' ' + n.date));
console.log('history rows', s.history.length);
console.log('albums', s.albums.length, 'imgs', s.albums.reduce((a, b) => a + b.images.length, 0), 'captions', s.albums.reduce((a, b) => a + b.images.filter(i => i.caption).length, 0), 'withDesc', s.albums.filter(a => a.desc).map(a => a.id + ':' + a.desc.slice(0, 60)));
console.log('empty albums', s.albums.filter(a => !a.images.length).map(a => a.id));
