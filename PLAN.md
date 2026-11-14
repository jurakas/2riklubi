# Valgamaa Äriklubi: new WordPress website (implementation plan)

Status: plan, ready for implementation by parallel sub-agents
Legacy source: https://ariklubi.riiska.ee/ (extracted into `legacy-pages/`)
Target hosting: Zone.ee web hosting (virtual server), developed locally first

---

## 1. Goals and guiding principles

1. **Hands-off after launch.** The owner does a one-time setup and then hands over. Club administrators manage the content in the normal WordPress admin, and nobody has to maintain code. That leads to these rules:
   - As few plugins as possible, and only mainstream, well-maintained free ones.
   - No page builder, no build step (npm/webpack), and no paid licences.
   - Auto-updates on for WordPress core, plugins and themes. A real server cron runs them, and the host keeps backups.
   - All custom code lives in a child theme plus one small must-use plugin, both written in plain PHP/CSS.
2. **Reproducible.** One idempotent provisioning script (`scripts/setup.sh` plus PHP import scripts run by WP-CLI) builds the same site locally and on Zone. Nothing has to be migrated between environments.
3. **Bilingual.** Estonian is the default language at `/`, and English lives under `/en/`.
4. **Brand.** Only this palette is used: https://colorhunt.co/palette/0107360d1c4222396ffcf1d0

| Token | Hex | Use |
|---|---|---|
| `navy-900` | `#010736` | Header background, primary text on cream, footer bottom bar |
| `navy-700` | `#0D1C42` | Footer background, headings |
| `navy-500` | `#22396F` | Links, buttons, accents, borders |
| `cream`    | `#FCF1D0` | Page background / text on dark, button text |

Transparent tints of these four colours (for example `rgba(34,57,111,.08)` for table stripes) are allowed. No other hues may be used.

---

## 2. Technology decisions

| Concern | Decision | Why |
|---|---|---|
| CMS | WordPress (latest stable), PHP 8.3, MariaDB | Standard, and supported by Zone |
| Theme | **GeneratePress (free)** as parent, plus the child theme `ariklubi` | Free, light, very stable, and uses classic menus and widgets. Polylang *free* fully supports per-language menus and widgets in classic themes; block-theme template-part translation needs Polylang Pro. |
| Multilingual | **Polylang (free)** | The standard free i18n plugin. It handles URL mode by directory, per-language menus and widgets, the language switcher, and translated pages, posts and categories. |
| Gallery | Core **Gallery block** with "Expand on click" (core lightbox) inside an `Album` custom post type | No gallery plugin, and the admin UX is drag-and-drop in the normal editor |
| Members | `Member` custom post type (name, company, website, optional logo/photo), rendered by a shortcode | Edited once and shown on both language versions |
| News | Core **Posts** in two categories: Uudised/News and Kuulutused/Announcements | This merges the legacy UUDISED and KUULUTUSED |
| History | Page with intro text plus a **synced pattern** holding the meetings table (270 rows) | One shared table for ET and EN, so it is edited only once |
| Contact | Footer widgets, one per language (Polylang widget language filter) | Required: contact info in the footer |
| Security | Small hardening in the mu-plugin plus **Limit Login Attempts Reloaded** (free) | Protects against brute force without upkeep |
| Local dev | Docker Compose (`wordpress`, `mariadb`, `wpcli`, `mailpit`) | Reproducible on Windows (Docker Desktop) |
| Fonts | Self-hosted woff2 (for example **Source Sans 3** for body text and **Source Serif 4** for headings, both OFL, with Estonian glyphs) | Avoids sending visitor data to Google (GDPR) |

Plugins (the complete list): `polylang`, `limit-login-attempts-reloaded`. Nothing else may be added without a written justification in `docs/DECISIONS.md`.

---

## 3. Information architecture

| Page | ET (default, `/`) | EN (`/en/`) | Source |
|---|---|---|---|
| Home | `/` "Avaleht" | `/en/` "Home" | `legacy-pages/index.md` |
| Members | `/liikmed/` | `/en/members/` | `legacy-pages/liikmed.md` (46 members) |
| News | `/uudised/` (posts page) | `/en/news/` | `uudised.md` + `kuulutused.md` (1 + 3 items) |
| History | `/ajalugu/` | `/en/history/` | `index.md` intro + `ajalugu.md` (270 meetings) |
| Gallery | `/galerii/` | `/en/gallery/` | 76 legacy album titles (`galerii.md`). Images are uploaded manually. |
| Album (single) | `/album/<slug>/` | same URL (albums are language-neutral) | |
| Privacy policy | `/privaatsus/` | `/en/privacy/` | New, short |

Main menu (per language): Avaleht · Liikmed · Uudised · Ajalugu · Galerii · [EN]
Footer: contact block, a short club description, © year, and a link to the privacy policy.

### Data that is shared across languages (entered once)
- `ak_member` posts: names and company names don't need translating.
- `ak_album` posts: the album title is usually a date and a venue.
- The history meetings table, as a synced pattern with bilingual column headers ("Aasta / Year", and so on).

Polylang is configured so that `ak_member`, `ak_album` and `wp_block` are **not translatable**. They then appear in both languages.

### Data that is translated
Pages, posts (news), categories, menus and footer widgets.

---

## 4. Repository layout and cross-workstream contracts

Every workstream writes **only** inside the paths it owns. The contracts below are fixed so that work can run in parallel. If a contract has to change, the agent updates this section and says so in its final report.

```
/
├─ PLAN.md                         (this file)
├─ legacy-pages/                   (done; read-only source material)
├─ docker-compose.yml              [WS-A]
├─ .env.example                    [WS-A]
├─ scripts/                        [WS-A]
│  ├─ local-up.ps1                 start Docker and run setup (Windows)
│  ├─ setup.sh                     idempotent provisioning (local AND Zone)
│  ├─ import-content.php           run with `wp eval-file`
│  └─ configure-polylang.php       run with `wp eval-file`
├─ wp-content/
│  ├─ mu-plugins/ariklubi-core.php [WS-B]  (may be split into ariklubi-core/*.php)
│  └─ themes/ariklubi/             [WS-C]  child theme of generatepress
├─ brand/                          [WS-D]
├─ content/                        [WS-E]
└─ docs/                           [WS-F]
```

### Contract 1: the mu-plugin API (owned by WS-B)
- CPT `ak_album`: public, `rewrite => ['slug' => 'album']`, `has_archive => false`, supports `title, editor, thumbnail, excerpt`, `show_in_rest => true`, menu icon `dashicons-format-gallery`, admin label "Galerii albumid".
- CPT `ak_member`: `public => false`, `show_ui => true`, supports `title, thumbnail, page-attributes`, admin label "Liikmed". The title holds the person's name ("Eesnimi Perenimi").
  - Meta `_ak_company` (string) and `_ak_website` (URL). Both are edited in a plain PHP meta box (no ACF, no JS build) and registered with `register_post_meta` (`show_in_rest`).
- Shortcode `[ak_members]` renders `<ul class="ak-members">` with `<li class="ak-member">`, which contains `.ak-member__logo` (optional img), `.ak-member__name`, `.ak-member__company` (linked to the website if one is set) and `.ak-member__web`. Members are sorted by **surname** (the last word of the title), then by first name. Labels are localised with `pll_current_language()` (ET/EN map, falling back to ET).
- Shortcode `[ak_albums count="12" pagination="yes"]` renders `<div class="ak-albums">` with `<a class="ak-album-card">`, which contains `.ak-album-card__img` (the featured image, or the first image in the gallery, or a placeholder), `.ak-album-card__title` and `.ak-album-card__date`. Albums are ordered by post date, newest first. `pagination="no"` hides the pagination links (used on the home page with `count="3"`).
- Shortcode `[ak_latest_news count="3"]` returns the latest posts in the current language as `.ak-news-list` > `.ak-news-item` (date, title, excerpt).
- Hardening and upkeep:
  - Enable auto-updates for core (major), plugins, themes and translations (`auto_update_*` filters, `WP_AUTO_UPDATE_CORE`).
  - Disable comments and pingbacks site-wide (and remove them from the admin menu).
  - Disable XML-RPC.
  - Block `?author=N` user enumeration.
  - Remove the WP version from `<head>`.
- Editor role: users with the `editor` role can manage `ak_album` and `ak_member` (map the capabilities to posts).

### Contract 2: the theme (owned by WS-C)
- Slug `ariklubi` with `Template: generatepress`. It is the active theme.
- Theme support: custom logo. Fonts are self-hosted in `assets/fonts/`.
- Styles for all the shortcode classes listed in Contract 1, the gallery block and lightbox, and the tables (history).
- `functions.php` must **not** register CPTs or business logic. That belongs to WS-B.
- The theme sets GeneratePress colours by registering the palette in `generate_option_defaults`/Customizer defaults **and** in CSS custom properties (`--ak-navy-900`, `--ak-navy-700`, `--ak-navy-500`, `--ak-cream`). It also passes `add_theme_support('editor-color-palette')` with exactly these 4 colours, so editors can only pick brand colours.
- Widget area used for the contact block: GeneratePress `footer-1` (with `footer-2` for the description, if used).

### Contract 3: brand assets (owned by WS-D)
| File | Spec |
|---|---|
| `brand/source/Valgamaa_vapp.svg` | Downloaded from `https://upload.wikimedia.org/wikipedia/commons/e/e2/Valgamaa_vapp.svg` (public domain, PD-EE-exempt) |
| `brand/source/logo-horizontal.svg` | Coat of arms on the left and the text "Valgamaa Äriklubi" on the right. All text is converted to paths. |
| `brand/logo-horizontal-on-dark.png` | Transparent, 1200×300 px, text in `#FCF1D0` (used in the navy header) |
| `brand/logo-horizontal-on-light.png` | Transparent, 1200×300 px, text in `#010736` |
| `brand/logo-stacked-on-light.png` | Transparent, 800×800 px, coat of arms above two-line text |
| `brand/site-icon-512.png` | 512×512 px, coat of arms on a `#010736` rounded square (favicon / site icon) |
| `brand/og-image-1200x630.png` | `#010736` background, centred horizontal logo (social sharing preview) |

### Contract 4: the content files (owned by WS-E, consumed by WS-A)
All content is UTF-8. Page bodies are **Gutenberg block markup** (`<!-- wp:paragraph -->…`).

```
content/
├─ site.json          site title/tagline per language, contact data, category names
├─ pages.json         [{ "key":"home", "front_page":true, "posts_page":false,
│                        "et":{"title":"Avaleht","slug":"avaleht","file":"pages/home.et.html"},
│                        "en":{"title":"Home","slug":"home","file":"pages/home.en.html"} }, ...]
├─ pages/*.et.html, pages/*.en.html
├─ posts.json         [{ "key":"kohtumine-2010-10-04", "date":"2010-10-04 18:00:00",
│                        "category":"uudised|kuulutused",
│                        "et":{"title":"…","slug":"…","file":"posts/<key>.et.html"},
│                        "en":{ … } }]
├─ posts/*.html
├─ members.json       [{ "name":"Hans Heinjärv","company":"Pindi Kinnisvara AS",
│                        "website":"https://www.pindi.ee" }]   (46 entries)
├─ albums.json        [{ "title":"4.11.2024 Valga Raekoda","date":"2024-11-04",
│                        "legacy_id":76 }]   (76 entries; imported as DRAFTS without images)
├─ patterns/history-table.html   synced pattern: intro sentence + Table block, 270 rows
└─ widgets/footer-contact.et.html, footer-contact.en.html, footer-about.et.html, footer-about.en.html
```
The home page and the Gallery/Members/History pages embed the shortcodes and the synced pattern. The pattern is referenced as `<!-- wp:block {"ref":"{{pattern:history-table}}"} /-->`, and the importer replaces that placeholder with the real ID.

---

## 5. Workstreams (parallel sub-agent tasks)

**Wave 1** runs fully in parallel: WS-A, WS-B, WS-C, WS-D, WS-E and WS-F.
**Wave 2** runs after wave 1: WS-G (integration and QA).

Every agent prompt must include: "Read `PLAN.md` sections 1–4 and your workstream section. Write only in the paths you own. Follow the contracts exactly."

### WS-A: local environment and provisioning
Owns: `docker-compose.yml`, `.env.example`, `scripts/`.
1. Docker Compose services:
   - `db` (mariadb:11)
   - `wordpress` (wordpress:php8.3-apache, port 8080)
   - `wpcli` (wordpress:cli-php8.3, same volumes, run on demand)
   - `mailpit` (port 8025, wired up as SMTP for local mail)
   - Bind mounts: `wp-content/themes/ariklubi`, `wp-content/mu-plugins`, `content/`, `brand/` and `scripts/`. Uploads go to a named volume.
2. `scripts/setup.sh` (bash, idempotent, works both in the `wpcli` container and on Zone over SSH). It takes `SITE_URL`, `ADMIN_USER`, `ADMIN_EMAIL` and `ADMIN_PASSWORD` from the environment.
   - `wp core install` (if not installed), locale `et`, timezone `Europe/Tallinn`, date format `d.m.Y`, time format `H:i`. Language packs: `et`, `en_US`.
   - Install and activate GeneratePress, Polylang and Limit Login Attempts Reloaded. Activate the `ariklubi` theme.
   - Permalinks `/%postname%/`. "Discourage search engines" on locally, off in production (`WP_ENVIRONMENT_TYPE`).
   - Run `configure-polylang.php`:
     - Languages ET (default, `et`, flag `ee`) and EN (`en_US`, flag `gb`).
     - URL mode "directory", with the default language hidden from the URL.
     - Make `ak_member`, `ak_album` and `wp_block` non-translatable.
     - Assign the existing content to ET.
     - (Use the Polylang PHP API, `PLL()->model->add_language()`, `pll_set_post_language()` and `pll_save_post_translations()`, and check them against the installed version.)
   - Run `import-content.php`. It reads the `content/*.json` files and upserts by a `_ak_key` meta so that re-running does not duplicate anything. It creates:
     - pages, posts, categories and members
     - album drafts
     - the synced pattern
     - the per-language menus with the language switcher
     - the per-language footer widgets
   - Set the front page and posts page (per language through Polylang). Upload `brand/*` and set the custom logo, site icon and the Polylang-translated site title/tagline.
   - Set `upload_max_filesize` guidance. Configure image sizes (keep the defaults; `big_image_size_threshold` = 2560).
   - Print a summary of the created IDs and URLs.
3. `scripts/local-up.ps1`: `docker compose up -d`, wait for the DB, run `setup.sh` in `wpcli`, then print `http://localhost:8080` and the admin credentials.
4. `scripts/reset-local.ps1`: tear down the volumes and rebuild.

### WS-B: mu-plugin `ariklubi-core`
Owns: `wp-content/mu-plugins/`. Implement Contract 1 completely. The code is plain PHP 8.1+, with no Composer and all output escaped. Admin UI labels are in Estonian, with English used when the user's admin locale is English.

### WS-C: child theme `ariklubi`
Owns: `wp-content/themes/ariklubi/`. Implement Contract 2 and the design:
- **Header:** `#010736` background, `logo-horizontal-on-dark.png`, cream navigation links with a `#22396F` hover/active underline, and the language switcher on the right. On mobile, a hamburger menu (GeneratePress built-in).
- **Body:** `#FCF1D0` background, `#010736` text, `#0D1C42` headings and `#22396F` links (underlined on hover/focus).
- **Home:** a hero band (`#0D1C42`, with cream headline "Tere tulemast Valgamaa Äriklubi lehele!" or the EN version, and the intro), then a short "Klubist" section, then the 3 latest news items, then the 3 latest albums, then a CTA to the history page. (The layout is built from the page content that WS-E provides; the theme only styles it.)
- **Members:** a responsive card grid (1, 2 or 3 columns) with a navy-500 left border on each card.
- **Gallery:** an album card grid with 4:3 image crops; the single album uses a gallery block grid and the core lightbox.
- **History table:** sticky header, striped rows using a navy-500 tint, and horizontal scroll on mobile.
- **Footer:** a `#0D1C42` background with cream text in 2 columns (contact | about), and a `#010736` bottom bar with © and a link to the privacy page.
- Focus styles must be visible. Contrast must meet WCAG AA, and the theme must work from 320 px to 1920 px wide.
- Include `screenshot.png` and `style.css` headers, and add `readme.txt` with notes on what the child theme overrides.

### WS-D: logo and brand assets
Owns: `brand/`. Implement Contract 3.
- Download the coat of arms SVG. Build the logo as SVG (coat of arms plus the wordmark "Valgamaa Äriklubi" in Source Serif 4 SemiBold or similar, OFL). Convert the text to paths and render the PNGs with a scriptable tool (for example `npx @resvg/resvg-js-cli` or Inkscape CLI). Commit the render script as `brand/render.(ps1|mjs)`.
- The coat of arms must not be altered (no recolouring or distortion). Leave clear space of at least 0.25 × the shield's width.
- Write `brand/README.md` covering usage, colours, fonts, and the licence and attribution of the coat of arms.

### WS-E: content migration and translation
Owns: `content/`. Implement Contract 4 using `legacy-pages/` as the source.
- **Home (ET):** the legacy intro text, reworded only lightly into proper paragraphs (the 1994 founding, the 1995 merger, the 1996 registration, MTÜ since 1999, president Hans Heinjärv, members are leading Valgamaa company executives). Drop the outdated statement "2009 a. lõpuseisuga 49 liiget" and replace it with dynamic wording ("ühendab üle 40 Valgamaa ettevõtte juhi", roughly "brings together more than 40 Valgamaa business leaders"), and flag it for the client to confirm. After that come `[ak_latest_news count="3"]` and `[ak_albums count="3" pagination="no"]`.
- **History:** narrative paragraphs from `index.md`, followed by the synced history table built from `ajalugu.md`. The table has 270 rows with the columns Aasta/Year, Kuu/Month, Koht/Venue and Külalised, teema/Guests, topic. Fix the obvious typos ("Kutuurikeskus" → "Kultuurikeskus", "Vorimehe" → "Voorimehe", "tutvvumine" → "tutvumine", "Eesi" → "Eesti") and list each fix in `content/CHANGES.md`. Newest meetings come first.
- **News:** the 1 news item plus the 3 announcements become posts. Use the **event date** found in the text (not 8.03.2023) as the post date. The signature lines stay. Remove the duplicated paragraphs in `kuul=3`.
- **Members:** 46 entries. Fix the broken URL `http://www//taimetee.ee` → `https://www.taimetee.ee`, and normalise all URLs to https where the site supports it (check with a HEAD request; otherwise keep http).
- **Albums:** 76 draft entries (title, date parsed from the title if present, otherwise empty; `legacy_id`). Administrators fill them with images later, or delete them.
- **Contact:** `site.json` and the footer widgets:
  - Valgamaa Äriklubi MTÜ, Kesk 11, 68203 Valga (verify the postal code)
  - Contact person: Anneli Kattai, +372 528 0802, anneli.kattai@valgamaa.ee
  - General: ariklubi@valgamaa.ee
- **English:** a natural, professional translation of every page, post and widget. Proper names and venues are not translated. Mark machine-assisted text in `content/CHANGES.md` for client review.
- **Privacy policy:** a short ET/EN page. It covers the absence of analytics and marketing cookies, the WordPress login cookies used by admins only, the contact data controller (the club), and the server logs at Zone.

### WS-F: documentation (handover)
Owns: `docs/`. Write each guide for its intended reader, with numbered steps and screenshot placeholders (`docs/img/…`, which WS-G fills in).
1. `docs/LOCAL.md` (EN, developer/owner):
   - Prerequisites: Docker Desktop with WSL2, and Git.
   - `scripts/local-up.ps1`, the URLs (site 8080, mail 8025), how to reset, and how to log in.
   - Troubleshooting: ports, line endings (`.gitattributes` with `*.sh text eol=lf`).
2. `docs/DEPLOYMENT-ZONE.md` (EN, owner, one-time):
   1. In the Zone control panel (my.zone.eu), on the virtual server for the domain:
      - Choose PHP 8.3, with `upload_max_filesize` and `post_max_size` = 64M and `memory_limit` = 256M.
      - Create a MariaDB database and user.
      - Enable SSH with a key.
   2. Point the domain's DNS at the virtual server, then issue a free Let's Encrypt certificate and force HTTPS.
   3. SSH in and check `wp --info` (WP-CLI is normally available on Zone; if not, download `wp-cli.phar`). `wp core download --locale=et` into the web root, then `wp config create` and set:
      - `WP_ENVIRONMENT_TYPE=production`
      - `DISALLOW_FILE_EDIT=true`
      - `WP_AUTO_UPDATE_CORE=true`
      - `DISABLE_WP_CRON=true`
   4. Upload the repository's `wp-content/themes/ariklubi`, `wp-content/mu-plugins`, `content/`, `brand/` and `scripts/` (SFTP or `git clone` + `rsync`).
   5. Run `SITE_URL=https://<domain> … bash scripts/setup.sh`.
   6. Add the Zone cron job every 15 min: `cd ~/<domain>/htdocs && wp cron event run --due-now --quiet`. This makes auto-updates run even when traffic is low.
   7. Create the users: 1 Administrator (owner, emergency) and Editors for the club administrators.
   8. Backups: enable or confirm Zone's automatic backups, and do a test restore of a single file.
   9. Smoke test with the go-live checklist (§7). Remove `scripts/` and `content/` from the web root afterwards.
   10. Optional: ask the owner of `riiska.ee` to 301-redirect `ariklubi.riiska.ee` to the new domain.
   11. Handover: transfer the credentials to the club, and record them in `docs/HANDOVER.md` (template without secrets).
3. `docs/ADMIN-GUIDE.et.md` (primary audience, Estonian) and `docs/ADMIN-GUIDE.en.md`:
   - Logging in, the dashboard, and changing your password.
   - **Adding a gallery album:**
     1. Galerii albumid → Lisa uus.
     2. Title: "PP.KK.AAAA Koht" (date and venue).
     3. Set the publish date to the event date, so albums sort chronologically.
     4. Add a "Galerii" block, then Laadi üles (upload) / Meediateek (media library), and select multiple photos at once (drag and drop works).
     5. In the block settings: Link → "Laienda klõpsamisel" (expand on click), and choose 3 or 4 columns.
     6. Set the "Tõstetud pilt" (featured image), which becomes the album cover.
     7. Avalda (publish).
     - Photo size advice: max 2560 px on the long edge, JPG, at most 20 photos per upload batch. Also covers how to reorder images, delete images and edit captions, and how to turn the pre-created legacy album drafts into published albums.
   - **Writing news:** Postitused → Lisa uus, choose the category Uudised or Kuulutused, set the language (Polylang box), and optionally add the English version with the "+" in the language box.
   - **Members:** Liikmed → add, edit or remove a member (name, company, website, optional logo).
   - **History table:** Mustrid (patterns) → "Ajaloo tabel" → add a row at the top.
   - **Footer contact details:** Välimus → Vidinad (widgets). Explains that ET and EN have separate widgets.
   - **Editing pages:** what not to break (the shortcodes in square brackets).
   - **What NOT to do:** install extra plugins, switch the theme, or change Polylang settings.
   - Who to contact if something breaks: the Zone support channels (help desk, phone).
4. `docs/DECISIONS.md`: a short record of the choices in §2 and their reasons.

### WS-G: integration and QA (wave 2)
1. Run `scripts/reset-local.ps1`. The site must build from scratch with no manual steps. Run `setup.sh` a second time to prove it is idempotent (no duplicates).
2. Fix the integration bugs in the owning workstream's files (note them in the final report).
3. Go through every acceptance criterion in §6 and record pass/fail in `docs/QA-REPORT.md`.
4. Take screenshots: the home page (desktop and mobile), Members, News, History, Gallery, and the album lightbox, all in ET and EN. Also take screenshots of the admin steps for the guides. Save them to `docs/img/` and link them into the guides.
5. Run Lighthouse (mobile) on Home, Gallery and Members and record the scores.

---

## 6. Requirements and acceptance criteria

### R1: WordPress, standard practice, low maintenance
- [ ] The site runs on the latest stable WordPress with PHP 8.3, GeneratePress (parent) and the `ariklubi` child theme. No changes are made to the parent theme or the plugins.
- [ ] Only the plugins Polylang and Limit Login Attempts Reloaded are installed, plus the mu-plugin `ariklubi-core`.
- [ ] Automatic updates are enabled for core, plugins, themes and translations (Dashboard → Updates shows "automatic updates enabled"). WP-Cron runs through the server cron.
- [ ] `DISALLOW_FILE_EDIT` is true, XML-RPC returns 403/405, `/?author=1` does not reveal a username, and comments are off everywhere.
- [ ] Site Health shows no critical issues in production.

### R2: Local first, then Zone
- [ ] On a clean Windows machine with Docker Desktop, `scripts/local-up.ps1` produces a working site at `http://localhost:8080` with all content, and no manual steps are needed.
- [ ] Re-running `setup.sh` creates no duplicate pages, posts, members, albums, menus or widgets.
- [ ] Following `docs/DEPLOYMENT-ZONE.md` step by step produces the same site on Zone over HTTPS. The guide has been tried end-to-end, or every Zone-specific step is marked "verify in panel".
- [ ] Production is indexable (search engines not discouraged), and local/staging is not.

### R3: Logo
- [ ] The PNG logos in `brand/` (as in Contract 3) contain the unaltered Valgamaa coat of arms and the text "Valgamaa Äriklubi" with a correct "Ä".
- [ ] The transparent background and the colours match the palette, and the text is legible at 200 px wide.
- [ ] The logo is used in the header, as the site icon (favicon) and as the og:image.

### R4: Colour palette and template
- [ ] Every colour in the theme CSS is one of `#010736`, `#0D1C42`, `#22396F`, `#FCF1D0`, or a transparent tint of them (checked with a grep for hex/rgb values in the theme).
- [ ] The editor colour picker offers only these 4 colours.
- [ ] Body text and links meet WCAG AA contrast (≥ 4.5:1).
- [ ] The layout is responsive from 320 px to 1920 px with no horizontal page scroll (tables scroll inside their own container).

### R5: Home page
- [ ] It contains the legacy introduction content (founding, merger, registration, MTÜ, president, member profile) in ET, and the EN translation at `/en/`.
- [ ] It shows the 3 latest news items and the 3 latest albums automatically.

### R6: Members
- [ ] All 46 legacy members are imported with their name, company and website (7 websites, with the broken URL fixed).
- [ ] Members are sorted by surname, and the same list appears on `/liikmed/` and `/en/members/`.
- [ ] An Editor can add, edit or delete a member without touching any page content.

### R7: News (merged UUDISED + KUULUTUSED)
- [ ] One news archive at `/uudised/` (ET) and `/en/news/` (EN) with the categories Uudised/News and Kuulutused/Announcements, and each category has its own archive URL.
- [ ] The 4 legacy items are imported with their original event dates, ET plus EN.
- [ ] An Editor can publish a new post in ET and add its EN translation.

### R8: History
- [ ] The history narrative appears in ET and EN.
- [ ] The meetings table (270 rows, 09.1994–05.2025, newest first) is identical in both languages, and it can be edited in one place (the synced pattern).

### R9: Gallery
- [ ] The `/galerii/` and `/en/gallery/` pages list the published albums as cards (cover, title, date), newest first, with pagination after 12.
- [ ] An album page shows a grid of images, and clicking an image opens the lightbox with keyboard (Esc/arrows) and touch support.
- [ ] An Editor can create a new album with 20+ photos in one upload, following `ADMIN-GUIDE.et.md` without any other help (checked in QA by following the guide literally).
- [ ] The 76 legacy album titles exist as drafts. No legacy images are copied.

### R10: Contact in the footer
- [ ] Every page shows in the footer: the club name, Kesk 11 Valga, Anneli Kattai, +372 528 0802 (`tel:` link), anneli.kattai@valgamaa.ee and ariklubi@valgamaa.ee (`mailto:` links).
- [ ] The labels are localised in ET and EN.

### R11: Localisation (ET + EN)
- [ ] Estonian is the default language at `/` and English is at `/en/`. The language switcher appears in the header on every page and links to the translated page when one exists (otherwise to the EN home page).
- [ ] The `<html lang>` attribute, menus, footer, dates and theme strings match the active language.
- [ ] `hreflang` alternate links are present (Polylang).

### R12: Legacy content captured
- [x] `legacy-pages/` contains one .md file per legacy page with source front matter (done during planning: 7 page files + README, 133 pages crawled).

### R13: Handover documentation
- [ ] `docs/LOCAL.md`, `docs/DEPLOYMENT-ZONE.md`, `docs/ADMIN-GUIDE.et.md`, `docs/ADMIN-GUIDE.en.md` and `docs/DECISIONS.md` exist, include screenshots, and have been checked by QA.
- [ ] The admin guide covers the gallery, news, members, the history table, the footer contacts, passwords, and the "do not" list.

### Non-functional
- [ ] Lighthouse mobile: Performance ≥ 85 and Accessibility, Best Practices and SEO ≥ 90 on Home, Gallery and Members.
- [ ] No third-party requests on the front end: no Google Fonts, CDNs or trackers.
- [ ] Privacy policy page in ET and EN.

---

## 7. Go-live checklist (production)
1. HTTPS works, and http → https and www/non-www redirect to one canonical host.
2. Settings → Reading: "Discourage search engines" is **off**.
3. Admin email = the club address, and a test password-reset email arrives.
4. The cron job is in place, and the Tools → Site Health cron check passes.
5. Zone backups are confirmed.
6. The Editor accounts are created, and the owner's Administrator account uses a strong password.
7. `scripts/` and `content/` are not in the public web root.
8. A test album with 20 photos has been uploaded successfully and then deleted.

---

## 8. Open questions for the club (do not block implementation)
1. The final domain name on Zone (for example `ariklubi.ee` or `valgamaaariklubi.ee`). The guides use `<domain>` until then.
2. The names of the other two board members, and the current member count, to replace the outdated "49 liiget (2009)".
3. Should the old partner logos (`eurovark.gif`, `partnerlus.gif`) and the home page photo `fotod/image001-.jpg` be kept? The default is to leave them out.
4. Permission or usage rules for the Valgamaa coat of arms in a private organisation's logo. It is public domain under copyright law, but heraldic use may need the consent of the county's municipalities (Valgamaa Omavalitsuste Liit). Confirm this before launch.
5. Is the legacy members-only login area (`?a=7`, newsletter email) still needed? It is out of scope for now.
6. Should the 76 legacy album drafts be kept, or created only on demand?
