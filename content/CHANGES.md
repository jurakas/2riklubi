# Content changes and items for client review (WS-E)

All content in this folder was migrated from `legacy-pages/` (crawl of https://ariklubi.riiska.ee/ on 2026-11-14).

Regenerate the generated files and validate:

```
node content/tools/build-history.mjs   # patterns/history-table.html (270 rows)
node content/tools/build-albums.mjs    # albums.json (76 albums)
node content/tools/build-members.mjs   # members.json (46 members)
node content/tools/validate.mjs        # JSON, block markup, counts, UTF-8/LF
```

Counts: 6 pages (ET+EN), 4 posts (1 Uudised + 3 Kuulutused, ET+EN), 46 members (7 with a website), 76 album drafts, 270 history rows. These match the plan.

## Needs client confirmation

1. **Member count wording.** The legacy home page said "2009 a. lõpuseisuga on klubil 49 liiget". This is replaced with "Täna ühendab klubi üle 40 Valgamaa ettevõtte juhi" ("more than 40 business leaders"), based on the 46 names on the legacy members list. Please confirm the current number.
2. **President.** The legacy text (written around 2009) says Hans Heinjärv has been chairman/president "kogu selle aja" (the whole time). The home page keeps that ("kogu klubi tegevuse vältel"). However, the history table records "uue juhatuse valimine" (new board election) in November 2024. Please confirm the current chairman and board. The history page says "has been Hans Heinjärv" and that a new board was elected in 11/2024.
3. **Season wording.** The footer/history texts say the club meets during the season "from September to May, usually once a month". This is derived from the history table, not stated on the legacy site.
4. **English translation.** All English text is **machine-assisted** and should be reviewed by the club. That covers every `*.en.html` file in `pages/`, `posts/` and `widgets/`, the EN titles/slugs in `pages.json` and `posts.json`, and the EN tagline in `site.json`. Proper names, venues and company names are not translated; organisation names are translated with the Estonian name in brackets on first use.
5. **Privacy policy** (`pages/privacy.*.html`) is new text written from the technical setup in PLAN.md. Its assumptions need checking before launch:
   - the host is Zone Media OÜ;
   - Polylang may set the `pll_language` cookie (can be turned off with the `PLL_COOKIE` constant, in which case remove that bullet);
   - Limit Login Attempts Reloaded stores the IP addresses of failed logins;
   - no analytics or third-party embeds are used.
6. **Defunct member website.** `http://www.astelpajutooted.ee` (Pärt Metsar, Pärt Metsar OÜ) no longer resolves (DNS: ENOTFOUND, with or without `www`, http and https). It is kept as `http://…` so the member keeps a link. Recommend removing it or replacing it with the current site.
7. **Additional typos in the history table that were NOT fixed** (only the four listed in the plan were fixed). Suggested corrections the club may want to apply in the synced pattern:
   - 1999 märts "peaminister Mart Siiman": the correct name is Mart Siimann. The history narrative uses the correct spelling.
   - 2002 detsember "prefekt Raimond Trss": the name looks truncated.
   - 2004 veebruar "Euro-Managment": should be "Euro-Management".
   - 2005 veebruar "Unaited Motors": should be "United Motors".
   - 2006 jaanuar "vistlused": should be "võistlused".
   - 2007 detsember "Stenboci maja": should be "Stenbocki maja" (album 5 has the same spelling).
   - 2022 aprill "Merike Metsavaas": should be "Merike Metsavas".
   - 2021 detsember "Grete Motell" versus 2007 oktoober "Greete motell": inconsistent spelling.
8. **Album date inconsistency.** Album 58 "4.10.19 - kohvik "Läti Saatkond" Tõrva" is dated 2019-10-04 from its title, but the history table places the Tõrva "Läti Saatkond" meeting in November 2019. The title date is used.
9. **Legacy items left out:** the home page photo (`fotod/image001-.jpg`), the partner logos (`eurovark.gif`, `partnerlus.gif`), the contact form, the members-only login, and the one member description ("Kalle Kits – Teravilja ja rapsikasvatus"; `members.json` has no description field per Contract 4). See PLAN §8.

## Home page (`pages/home.*.html`)

- The legacy intro was reworded lightly into paragraphs.
- "registreeritud MTÜ-na Äriregistris" became "registreeritud mittetulundusühinguna (MTÜ)". Non-profits are entered in the non-profit associations register, not the commercial register, so the register name was dropped rather than repeated.
- "Valga MV" was expanded to "Valga Maavalitsus" (Valga County Government).
- The CTA says "ligi 250 kohtumist" ("nearly 250 meetings"). The history table has 270 rows, of which about 245 are meetings that took place; empty, cancelled and COVID rows are excluded.

## History (`pages/history.*.html`, `patterns/history-table.html`)

- The narrative combines the `index.md` facts with facts from the meetings table: guest prime ministers, the 2010 business lunch with President Ilves, partner clubs, anniversaries and the 11/2024 board election.
- The table is generated by `tools/build-history.mjs`. Rows are in reverse source order (newest first: 05.2025 at the top, 09.1994 at the bottom). Source order is kept within a month that has two rows (2015 detsember, 2019 november).
- Empty rows and notes ("koroona", "-", "Jäi ära") are kept as in the source.
- Month names stay in Estonian. The table is shared by both languages, and only the column headers are bilingual.
- Typo fixes applied (one occurrence each):

| Row | Before | After |
|---|---|---|
| 2024 september, venue | Valga Kutuurikeskus | Valga Kultuurikeskus |
| 2018 september, venue | Vorimehe pubi | Voorimehe pubi |
| 2007 jaanuar, guests | City Renault - tutvvumine, proovisõidud | City Renault - tutvumine, proovisõidud |
| 2011 mai, guests | Kohtumine Eesi suusakoondise sprinteritega | Kohtumine Eesti suusakoondise sprinteritega |

## Posts (`posts.json`, `posts/*.html`)

Post dates are the event dates from the text, not the legacy list date 8.03.2023. The event time is used where it was given; otherwise 18:00:00.

| Key | Category | Date | Basis |
|---|---|---|---|
| `kohtumine-2010-10-04` | uudised | 2010-10-04 17:30:00 | "04.10.2010 … algusega 17.30" |
| `valismaalaste-seadus-2010-10-01` | kuulutused | 2010-10-01 18:00:00 | the Aliens Act takes effect 01.10.2010 (no time given) |
| `teabepaev-2011-04-12` | kuulutused | 2011-04-12 11:00:00 | "12.aprill 2011", agenda starts 11.00 |
| `teabepaev-2011-11-07` | kuulutused | 2011-11-07 11:00:00 | "07.11. 2011", agenda starts 11.00 |

- Titles in ALL CAPS were changed to sentence case ("EESTI TÖÖTUKASSA TEABEPÄEV …" became "Eesti Töötukassa teabepäev …"). The trailing full stop was removed from the kuul=3 title.
- Signature lines are kept: "Kohtumiseni / Hans" and the Merike Metsavas (Eesti Töötukassa) contact line.
- **kuul=3 (Aliens Act):**
  - Removed the duplicated paragraphs left over from the old editor (the plain-text copies of "Alates 01. oktoober 2010…", "Uues VMS-is sätestatakse… / Välismaalane, kellele…", "Õppeasutus on kohustatud…" and "Kutsuja on kohustatud…").
  - The fully bold intro paragraphs are now plain text with the link kept.
  - The "·" pseudo-bullets are now real lists.
  - Fixed minor spacing ("isikuandmeid ,", "eesmärgil ,"), "Eesti töötamise" → "Eestis töötamise", and "teavitamiskohutuse" → "teavitamiskohustuse".
  - The EN version starts with an added italic note that this is a 2010 archive item with amounts in kroons; the ET version has no such note.
  - The Riigi Teataja links (`/ert/act.jsp?...`) still redirect to the current `/et/akt/…` URLs.
- kuul=7: the duplicated title line at the start of the body was removed.
- kuul=5/7: agendas are paragraphs with line breaks, and time ranges use en dashes.
- uudis=14: "!! !" was normalised to "!".

## Members (`members.json`)

The legacy order is kept (the mu-plugin sorts by surname). Website HEAD checks were run on 2026-11-14 with Node `fetch`, 10 s timeout:

| Legacy URL | Result | Stored as |
|---|---|---|
| http://www.reff.ee | https 200 | https://www.reff.ee |
| http://www.pindi.ee | https 200 | https://www.pindi.ee |
| http://www.kmprojekt.ee | https 200 (redirects to https://kmprojekt.ee/) | https://www.kmprojekt.ee |
| http://www.astelpajutooted.ee | DNS failure on http and https | http://www.astelpajutooted.ee (flagged, see above) |
| http://www//taimetee.ee (broken) | https 200 (redirects to https://taimetee.ee/) | https://www.taimetee.ee |
| http://www.vester.ee | https 200 (redirects to https://vester.ee/) | https://www.vester.ee |
| http://www.elektroman.ee | https 200 | https://www.elektroman.ee |

## Albums (`albums.json`)

There are 76 entries, and the titles are verbatim. Dates are parsed from the title only:

- full dates use that day (for a range, the first day: "16-17.09.2010" gives 2010-09-16, and "14.-15. jaanuar 2010" gives 2010-01-14);
- two-digit years are read as 20xx;
- **month-only titles use the 1st of the month** ("2010 märts" gives 2010-03-01, and "09.2019" gives 2019-09-01);
- year-only or undated titles get an empty date.

There are 12 albums without a date, listed by legacy_id: 75 XXX aastapäeva pidu, 73 Zacman OÜ + Metsis, 60 SIA Pepi Per + Metsis, 59 Ettevõtlus Gala Sangaste lossis 2019, 48 Ettevõtlus Gala 2017, 41 ettevõtlusgala 2016, 35 Kaldavere 2015, 33 2015 Taagepera loss, 29 XX aastapäev, 19 Terje Paes motivatsioonikoolitus Kubija Hotellis, 11 2010 hooaja lõpetus Vidrike Puhkemajas, and 8 Kohtumine Eesti Vabariigi presidendiga. Most of these can be dated from the history table when an administrator publishes the album.

## Site and contact (`site.json`, `widgets/`)

- **Site title:** "Valgamaa Äriklubi" is used in **both** languages, because it is the club's proper name and matches the logo. The EN tagline explains it: "Valga County Business Club since 1994". The EN home heading uses the descriptive form "Welcome to the Valgamaa Business Club!".
- **Postcode verified:** Kesk tn 11, Valga linn, Valga vald is **68203** according to the Maa-amet In-ADS address gazetteer (inaadress.maaamet.ee, queried 2026-11-14). The Omniva postcode search blocked automated requests.
- The phone number "5280802" from the legacy contact page is formatted as "+372 528 0802" (`tel:+3725280802`).
- The contact widget lists the organisation as "Valgamaa Äriklubi MTÜ".
- The footer "about" text is new, short copy based on the home page intro.
