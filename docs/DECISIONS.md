# Decisions

A short record of the technical choices for the Valgamaa Äriklubi website and why they were made. The guiding principle is that the site must run **hands-off after launch**: club administrators edit content in the normal WordPress admin and nobody has to maintain code. See `PLAN.md` §1–2 for the full context.

Any plugin beyond the list below, and any deviation from `PLAN.md`, must be recorded here with a justification.

## Decisions

### D1. CMS: WordPress (latest stable), PHP 8.3, MariaDB
Standard, widely known, and supported by Zone.ee web hosting. Any future helper can take it over.

### D2. Theme: GeneratePress (free) + child theme `ariklubi`
GeneratePress is free, light and very stable, and it uses classic menus and widgets. That matters because **Polylang free** fully supports per-language menus and widgets in classic themes, while translating block-theme template parts needs Polylang Pro. All customisation lives in the child theme (plain PHP/CSS); the parent theme is never modified, so it can auto-update safely.

### D3. Multilingual: Polylang (free)
The standard free i18n plugin. It provides directory URLs (Estonian at `/`, English at `/en/`), per-language menus and widgets, the language switcher, `hreflang` links, and translated pages, posts and categories. Members, albums and synced patterns (`ak_member`, `ak_album`, `wp_block`) are configured as **not translatable**, so they are entered once and shown in both languages.

### D4. Gallery: core Gallery block with "Expand on click" in an `Album` custom post type
No gallery plugin. Editors use drag-and-drop upload in the normal editor, and the core lightbox (keyboard and touch support) shows the images. The mu-plugin enables "Expand on click" by default so editors don't have to set it.

### D5. Members: `Member` custom post type rendered by a shortcode
Name, company, website and an optional logo/photo, edited once and shown on both language versions by `[ak_members]` (sorted by surname). Editors never have to touch page content to update the list.

### D6. News: core Posts in two categories
Uudised/News and Kuulutused/Announcements. This merges the legacy UUDISED and KUULUTUSED sections into one archive with per-category archive URLs.

### D7. History: page text + a synced pattern for the meetings table
The 270-row meetings table has bilingual column headers and is the same in both languages, so it lives in one synced pattern ("Ajaloo tabel") and is edited only once.

### D8. Contact: footer widgets, one per language
Required on every page. Polylang's widget language filter shows the right widget for each language.

### D9. Security: small hardening in the mu-plugin + Limit Login Attempts Reloaded (free)
The mu-plugin disables XML-RPC, comments and pingbacks, blocks `?author=N` user enumeration and removes the WordPress version from `<head>`. `DISALLOW_FILE_EDIT` is set in production. Limit Login Attempts Reloaded protects against brute-force logins with no upkeep.

### D10. Updates and cron: everything auto-updates; a real server cron runs WP-Cron
Auto-updates are on for core (including major versions), plugins, themes and translations. `DISABLE_WP_CRON` is set and a Zone cron job runs `wp cron event run --due-now` every 15 minutes, so updates happen even when traffic is low. Zone's automatic backups are the safety net.

### D11. Custom code: one child theme + one must-use plugin, no build step
The child theme handles presentation only; the mu-plugin `ariklubi-core` holds the CPTs, shortcodes (`[ak_members]`, `[ak_albums]`, `[ak_latest_news]`, `[ak_member_count]`) and hardening. Plain PHP/CSS, no Composer, npm, webpack or page builder, and no paid licences. A must-use plugin cannot be deactivated accidentally from the admin.

### D12. Provisioning: one idempotent script, run locally and on Zone
`scripts/setup.sh` (plus PHP import scripts run with `wp eval-file`) builds the same site from `content/` and `brand/` locally and in production. Content is upserted by a `_ak_key` meta, so re-running creates no duplicates. Nothing is migrated between environments.

### D13. Local development: Docker Compose
Services `wordpress` (php8.3-apache, port 8080), `db` (mariadb:11), `wpcli` and `mailpit` (port 8025). Reproducible on Windows with Docker Desktop.

### D14. Fonts: self-hosted woff2 (Source Sans 3 / Source Serif 4, OFL)
They have full Estonian glyph coverage, and self-hosting avoids sending visitor data to Google (GDPR). The front end makes no third-party requests.

### D15. Brand palette: four colours only
`#010736`, `#0D1C42`, `#22396F`, `#FCF1D0` (plus transparent tints). The editor colour picker is limited to these four colours so editors cannot introduce off-brand colours.

### D16. Plugin list (complete)
`polylang`, `limit-login-attempts-reloaded`. Nothing else may be added without a written justification in this file.

## Deviations

Deviations from `PLAN.md`, added during implementation and integration.

| Date | Deviation | Reason |
|---|---|---|
| 2026-11-14 | Native Windows fallback runner (`scripts/local-up-native.ps1`) added. It downloads portable PHP 8.3 and MariaDB 11 into `.runtime/` (git-ignored) and serves WordPress with PHP's built-in web server at http://localhost:8080 (no mail catcher). | The dev machine had no Docker/WSL. Docker remains the primary path (`scripts/local-up.ps1`). |
| 2026-11-14 | The mu-plugin filters `widget_update_callback` (`ak_pll_keep_widget_language` in `polylang.php`) to keep a widget's Polylang language (`pll_lang`) when it is saved in the block-based widget editor. | Found in QA: saving Välimus → Moodulid once dropped the language from every footer widget, so the Estonian and English contacts appeared together on every page. The block widget editor has no Polylang language field, so the existing language is preserved instead. |
| 2026-11-14 | The child theme header has `Update URI: false`. | Theme auto-updates are on (D10). Without this header WordPress could offer an unrelated wordpress.org theme with the slug `ariklubi` as an "update" and overwrite the custom child theme. |
| 2026-11-14 | Lightbox button colours are set in the child theme with `!important` on the WordPress 7.x classes (`.wp-lightbox-close-button`, `.wp-lightbox-navigation-button`). | Core prints an inline black fill on these buttons; on the navy lightbox background the close and previous/next icons were invisible. |
| 2026-11-14 | Albums are language-neutral (D3), so an album page has no `hreflang` links, its interface is in Estonian, and the language switcher on it leads to the English home page. | Consequence of entering albums once for both languages; accepted. |
| 2026-11-14 | The synced patterns screen ("Mustrid") is not in the admin menu of the GeneratePress (classic) theme. The admin guide sends editors to `wp-admin/edit.php?post_type=wp_block` or to "Muuda originaali" on the History page. | WordPress only lists Patterns under Appearance for block themes. Adding a custom menu item was not worth extra code. |
| 2026-11-14 | The default gallery link stays "Puudub" (None) instead of "Expand on click" (D4); the lightbox is enabled for all images through the theme's `theme.json` filter, so "None" and "Expand on click" both open it. | Verified in QA with a new album; the guide tells editors not to choose the attachment-page or media-file link. |
