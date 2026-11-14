<?php
/**
 * Front-end theme strings (ET/EN), chosen by the current Polylang language.
 *
 * The theme has only a handful of strings, so a small map is used instead of
 * compiled .mo files. The `ariklubi` text domain is still loaded from
 * `languages/` in case translations are added later.
 *
 * @package ariklubi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current front-end language slug ('et' or 'en').
 */
function ariklubi_lang(): string {
	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language( 'slug' );
		if ( is_string( $lang ) && '' !== $lang ) {
			return 'en' === $lang ? 'en' : 'et';
		}
	}

	return str_starts_with( determine_locale(), 'en' ) ? 'en' : 'et';
}

/**
 * Localised theme string. Falls back to Estonian, then to the key itself.
 */
function ariklubi_t( string $key ): string {
	static $strings = array(
		'et' => array(
			'owner'           => 'Valgamaa Äriklubi MTÜ',
			'privacy'         => 'Privaatsuspoliitika',
			'back_to_gallery' => 'Tagasi galeriisse',
		),
		'en' => array(
			'owner'           => 'Valgamaa Äriklubi MTÜ',
			'privacy'         => 'Privacy policy',
			'back_to_gallery' => 'Back to the gallery',
		),
	);

	$lang = ariklubi_lang();

	return $strings[ $lang ][ $key ] ?? $strings['et'][ $key ] ?? $key;
}

/**
 * Estonian for the visible GeneratePress front-end strings (GeneratePress ships no `et` pack).
 *
 * @param string $translation Translated text.
 * @param string $text        Original text.
 */
function ariklubi_generatepress_et( $translation, $text ): string {
	static $et = array(
		'Read more'          => 'Loe edasi',
		'Skip to content'    => 'Liigu sisu juurde',
		'Menu'               => 'Menüü',
		'Primary'            => 'Peamenüü',
		'Site'               => 'Sait',
		'Older posts'        => 'Vanemad postitused',
		'Newer posts'        => 'Uuemad postitused',
		'Previous'           => 'Eelmine',
		'Next'               => 'Järgmine',
		'Previous post'      => 'Eelmine postitus',
		'Next post'          => 'Järgmine postitus',
		'Categories'         => 'Kategooriad',
		'Tags'               => 'Sildid',
		'Search'             => 'Otsi',
		'Close Search'       => 'Sulge otsing',
		'Scroll back to top' => 'Tagasi üles',
		'Nothing Found'      => 'Midagi ei leitud',
		'Read more about %s' => 'Loe edasi: %s',
	);

	if ( is_admin() || 'et' !== ariklubi_lang() || ! isset( $et[ $text ] ) ) {
		return (string) $translation;
	}

	return $et[ $text ];
}
add_filter( 'gettext_generatepress', 'ariklubi_generatepress_et', 10, 2 );
add_filter( 'gettext_with_context_generatepress', 'ariklubi_generatepress_et', 10, 2 );
