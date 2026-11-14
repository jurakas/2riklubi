=== Valgamaa Äriklubi ===
Contributors: Valgamaa Äriklubi MTÜ
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Child theme of GeneratePress (free) for the Valgamaa Äriklubi website.

== Description ==

The child theme only changes presentation. Custom post types (albums,
members), shortcodes and site hardening live in the must-use plugin
`wp-content/mu-plugins/ariklubi-core`. Do not edit the parent theme;
GeneratePress is updated automatically.

Brand palette (no other colours are used anywhere in the theme):

* navy-900 #010736: header, footer bottom bar, body text
* navy-700 #0D1C42: headings, footer, hero band
* navy-500 #22396F: links, buttons, accents, borders
* cream    #FCF1D0: page background, text on dark backgrounds

The same colours are available as CSS custom properties
(--ak-navy-900, --ak-navy-700, --ak-navy-500, --ak-cream) and are the only
colours offered in the block editor colour picker (custom colours and
gradients are disabled).

== What the child theme overrides ==

GeneratePress defaults (filters `generate_option_defaults`,
`generate_color_option_defaults`, `generate_font_option_defaults`):

* Global colours = the four brand colours (slugs navy-900, navy-700,
  navy-500, cream), and every GeneratePress colour setting mapped to them.
* Layout: one container, no sidebars anywhere (also forced with
  `generate_sidebar_layout`), container width 1100 px, navigation floated to
  the right of the logo, two footer widget columns (footer-1 contact,
  footer-2 about), centred footer bar.
* Typography: no Google fonts. GeneratePress font settings are left empty
  and the stylesheet sets the self-hosted fonts.

These are defaults only. If someone saves different values in
Appearance > Customize, the saved values win. To return to the brand
defaults, reset the GeneratePress settings.

Templates and output (GeneratePress hooks):

* Home page: no page title (`generate_show_title`) and no top padding, so
  the hero band starts right under the header.
* Posts: author and comment links hidden; date and categories are shown.
* Footer bar: "© year Valgamaa Äriklubi MTÜ · Privaatsuspoliitika/Privacy
  policy" (`generate_copyright`). The privacy page is the page set in
  Settings > Privacy, in the current Polylang language. This replaces the
  GeneratePress credit.
* Single album: a "Tagasi galeriisse / Back to the gallery" link below the
  content, pointing to the page with slug `galerii` (or its translation).

Assets:

* style.css: all front-end styles. Loaded after the GeneratePress
  stylesheet, versioned by file modification time.
* assets/css/fonts.css: @font-face rules for Source Sans 3 (400, 400
  italic, 600, 700) and Source Serif 4 (600, 700), latin and latin-ext
  subsets (covers õ ä ö ü š ž), font-display: swap.
* assets/fonts/: the woff2 files and their SIL Open Font License texts.
* assets/css/editor.css: block editor styles (palette and fonts).

== CSS classes for page content ==

Add these in the block editor under Block > Advanced > Additional CSS
class(es):

* ak-hero: full-width (alignfull) Group at the top of the home page. Dark
  band with a large serif headline. Buttons inside are cream; use the
  "Outline" button style for a secondary button.
* ak-section: Group with vertical spacing. Add ak-section--muted for a
  lightly tinted background.
* ak-cta: centred call-to-action Group (navy band) with a Buttons block.

Shortcode output from ariklubi-core is styled automatically:
[ak_members], [ak_albums], [ak_latest_news]. Table blocks (the history
table) scroll sideways on small screens, have a sticky header row and
striped rows.

== Theme strings ==

The few theme strings (privacy link label, back link) are chosen by the
current Polylang language (ET default, EN). No .mo files are needed; the
`ariklubi` text domain is still loaded from languages/.

== Credits ==

* GeneratePress, GPLv2 or later, https://generatepress.com/
* Source Sans 3 and Source Serif 4, Adobe, SIL Open Font License 1.1 (via
  the Fontsource packages)
