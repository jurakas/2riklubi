# Valgamaa Äriklubi: vana veebilehe sisu (https://ariklubi.riiska.ee/)

Extracted 2026-11-14. The source is a table-based PHP site. Pages are routed by `?a=N`, and detail pages add a second parameter (`liige`, `uudis`, `kuul`, `ga`). The server sends the header `charset=UTF-8` and the bytes really are UTF-8; the content also contains HTML entities (`&otilde;`, `&auml;` …), which have all been decoded. Estonian characters (õ ä ö ü š ž) are correct in every file.

## Files

| File | Source | Content |
|---|---|---|
| `index.md` | `/` (`?a=0`) | Club introduction (history in brief, board, member count in 2009) |
| `liikmed.md` | `?a=1` + 46 × `?a=1&liige=ID` | Member table (name, company, website, photo, source page) |
| `uudised.md` | `?a=2` + `?a=2&uudis=14` | 1 news item, full text |
| `ajalugu.md` | `?a=3` | Table "Valgamaa Äriklubi kohtumised läbi aegade" (club meetings over the years): 270 rows, 1994–2025 |
| `galerii.md` | `?a=4` + 76 × `?a=4&ga=ID` | 76 albums, 1008 image URLs (not downloaded) |
| `kontakt.md` | `?a=5` | Contact details + contact form fields |
| `kuulutused.md` | `?a=6` + `?a=6&kuul=3,5,7` | 3 announcements, full text |
| `_raw/` | — | Raw HTML of all downloaded pages + `extract.js` (conversion script) + `summary.json` (structured data: members, albums, images) |

Pages processed: 7 menu pages + 46 member pages + 1 news item + 3 announcements + 76 gallery albums = **133 pages** (plus 32 probed IDs that turned out to be empty, and `?a=7..10`).

## Summary

- **Members:** 46 (list on `?a=1`). Every member has a name and a company/organisation. 7 have a website link and 2 have a photo (`g/5f.jpg`, `g/29f.jpg`). One has a description ("Kalle Kits – Teravilja ja rapsikasvatus"). The member pages contain **no** phone numbers, emails or job titles. The home page says "2009 a. lõpuseisuga on klubil 49 liiget" (49 members as of end of 2009).
- **News (Uudised):** 1 item, `uudis=14`, "Äriklubi kohtumine 04.10.2010 Tõrva 1, Valga". The list date is 8.03.2023 (apparently a re-save/migration date); the event itself is 04.10.2010. IDs 1–20 were probed and only 14 exists.
- **Announcements (Kuulutused):** 3 items (`kuul=5`, `3`, `7`). All have the list date 8.03.2023. Dates in the content: 01.10.2010 (Välismaalaste seadus, the Aliens Act), 12.04.2011 and 07.11.2011 (Töötukassa, the Unemployment Insurance Fund, information days). Content date range is therefore 2010–2011. IDs 1–12 were probed and only 3, 5 and 7 exist.
- **Gallery:** 76 albums (`ga=1…76`) with 1008 images in total (files `galerii/6.jpg` … `galerii/1125.jpg`; thumbnail = `NNN_.jpg`). Albums date from 2003 (event date) to 4.11.2024. Only albums 1–7 have per-image captions (`title`); none of the albums has a description text. There is no pagination.
- **History:** a meetings table with columns Aasta / Kuu / Koht / Külalised (year / month / venue / guests), 270 rows from 09.1994 to 05.2025.

## Contact details found

- Contact person: **Anneli Kattai**
- Mobile: **5280802** (the "Tel." field is empty)
- E-mail: **anneli.kattai@valgamaa.ee**
- Address: **Kesk 11, Valga**
- Club general e-mail (footer of every page): **ariklubi@valgamaa.ee**
- Board: "Klubi juhatab 3-liikmeline juhatus" (3-member board). Chairman/president: **Hans Heinjärv** ("kogu selle aja", i.e. since 1994; Pindi Kinnisvara AS). The other two board members are **not named** anywhere on the site. The history table mentions "uue juhatuse valimine" (new board election) on 11.2024, but gives no names.
- Legal form: MTÜ (non-profit association), registered in the Äriregister (Business Register) since February 1999. Registered with Valga MV (county government) in January 1996.

## Content that could not be extracted / notes

- **Login-protected area:** the header has a login form (Kasutajatunnus/Salasõna, i.e. username/password) and `?a=7` is "Meenuta salasõna" (password reminder, by the email address that receives the club newsletters). `?a=9` is an admin login (Kasutajanimi/Parool). Members-only content (if any) was not accessible.
- `?a=8` and `?a=10` contain only the text "Menüü" (empty).
- The contact form (`?a=5`) is described in `kontakt.md` as a list of fields; it was not submitted.
- In announcement `kuul=3`, the source HTML contains several paragraphs twice (a second copy without links and emphasis, left over from the old editor). These are kept as they are in the source.
- The history table has some empty rows (e.g. 1996 mai/september, 2015 märts) and notes such as "koroona" (COVID), "-", "Jäi ära" (cancelled). These are kept as they are. The year was originally given only on the first row of each year; in the md file it is repeated on every row. There are typos in the source ("Kutuurikeskus", "Vorimehe", "tutvvumine", "Eesi") that have not been corrected.
- Inge Põder-Männiste's website link in the source is broken: `http://www//taimetee.ee` (presumably `http://www.taimetee.ee`).
- The visitor counter (180576 visits), menu, login box and spacer gifs have been removed. Images were not downloaded; only URLs are given.
