# Valgamaa Äriklubi – brand assets

The logo pairs the Valgamaa coat of arms with the wordmark "Valgamaa Äriklubi", set in Source Serif 4 SemiBold. The wordmark is converted to outlines, so no font is needed to display any of the files.

## Files and where they are used

| File | Size | Use |
|---|---|---|
| `logo-horizontal-on-dark.png` | 1200×300, transparent, cream text | **Site header** (navy background). Upload as the WordPress custom logo. |
| `logo-horizontal-on-light.png` | 1200×300, transparent, navy text | Light backgrounds: documents, letters, partner sites. |
| `logo-stacked-on-light.png` | 800×800, transparent, navy text | Square spaces: social media profile, posters. |
| `site-icon-512.png` | 512×512, navy rounded square | **Site icon / favicon** (Customizer → Site Identity → Site Icon). |
| `og-image-1200x630.png` | 1200×630, navy background | **Social sharing preview** (`og:image`). |
| `logo-horizontal-on-dark.svg` | vector | Same as the on-dark PNG, for print or future use. |
| `source/logo-horizontal.svg` | vector | Master horizontal logo (navy text, for light backgrounds). |
| `source/logo-stacked.svg` | vector | Master stacked logo (navy text). |
| `source/Valgamaa_vapp.svg` | vector | The coat of arms, unmodified, as downloaded from Wikimedia Commons. |

Usage rules:
- Do not recolour, stretch or redraw the coat of arms.
- Keep clear space of at least ¼ of the shield's width around the shield. All the files above already include it.
- The horizontal logo is legible down to about 200 px wide. Below that, use the site icon (shield only).
- Use the on-dark version only on `#010736` or `#0D1C42`, and the on-light versions only on cream (`#FCF1D0`) or white.

## Colours

Only this palette is used ([colorhunt](https://colorhunt.co/palette/0107360d1c4222396ffcf1d0)):

| Token | Hex | Use in the logo |
|---|---|---|
| `navy-900` | `#010736` | Wordmark on light backgrounds; site icon and og:image background |
| `navy-700` | `#0D1C42` | – (alternative dark background) |
| `navy-500` | `#22396F` | – |
| `cream` | `#FCF1D0` | Wordmark on dark backgrounds |

The blue and white of the coat of arms are its own heraldic colours and are not part of the site palette.

## Fonts

- **Source Serif 4** (headings and the wordmark; the logo uses the static SemiBold cut) – SIL Open Font License 1.1, © Adobe. The file used is in `source/fonts/` together with its licence (`OFL.txt`). Source: [adobe-fonts/source-serif release 4.005R](https://github.com/adobe-fonts/source-serif/releases/tag/4.005R).
- **Source Sans 3** (body text on the site) – SIL Open Font License 1.1, © Adobe. It is self-hosted by the theme, not used in the logo.

## Coat of arms: licence and attribution

- File: [Valgamaa_vapp.svg on Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Valgamaa_vapp.svg) (original source given there: Riigikantselei, the Estonian Government Office)
- Copyright status: **public domain** in Estonia (`PD-EE-exempt`: official symbols such as coats of arms are not protected by the Estonian Copyright Act).
- The SVG is embedded unchanged. Only non-visual editor metadata (Inkscape/Sodipodi tags) is dropped when it is nested into the logo.

**Caveat (PLAN §8, question 4):** copyright-free does not automatically mean free to use. Using a county coat of arms in a private organisation's logo may need the consent of the county's municipalities (Valgamaa Omavalitsuste Liit). The club must confirm permission or the usage rules **before launch**. If consent is refused, regenerate the logo without the shield or with a different emblem.

## Regenerating

Requires Node.js 18+.

```powershell
cd brand
npm install
node render.mjs                       # or: .\render.ps1
node render.mjs --preview $env:TEMP\brand-preview   # also writes 200 px previews on backgrounds
```

`render.mjs` reads only `source/Valgamaa_vapp.svg` and `source/fonts/SourceSerif4-Semibold.ttf`, and rewrites every SVG and PNG listed above. Layout numbers are at the top of each layout function:

- **Horizontal (1200×300):** shield 208 px high (166 px wide), 46 px margin on all sides (≥ ¼ shield width = 41.6 px), a 57 px gap, then the single-line wordmark scaled to fill the remaining width to a 46 px right margin (≈ 105 px font size). The cap height is vertically centred on the shield.
- **Stacked (800×800):** shield 330 px high, centred; a 66 px gap (¼ shield width); two centred lines "Valgamaa" / "Äriklubi" at 132 px with 1.12 line spacing; the whole block is vertically centred.
- **Site icon (512×512):** navy square with 96 px corner radius; shield 352 px high, centred (≥ ¼ shield width of clear space).
- **og:image (1200×630):** navy background with the on-dark horizontal logo scaled to 85 % and centred.
