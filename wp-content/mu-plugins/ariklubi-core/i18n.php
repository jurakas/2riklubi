<?php
/**
 * Localisation helpers: front-end strings follow the Polylang language,
 * admin strings follow the user's admin locale.
 *
 * @package ariklubi-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current front-end language: 'en' or 'et' (default).
 */
function ak_lang(): string {
	if ( function_exists( 'pll_current_language' ) && 'en' === pll_current_language( 'slug' ) ) {
		return 'en';
	}
	return 'et';
}

/**
 * Translated front-end string for a key, falling back to Estonian.
 */
function ak_t( string $key ): string {
	static $strings = array(
		'albums_empty'     => array(
			'et' => 'Albumeid veel ei ole.',
			'en' => 'No albums yet.',
		),
		'albums_nav'       => array(
			'et' => 'Albumite lehed',
			'en' => 'Album pages',
		),
		'prev'             => array(
			'et' => '« Eelmine',
			'en' => '« Previous',
		),
		'next'             => array(
			'et' => 'Järgmine »',
			'en' => 'Next »',
		),
		'news_empty'       => array(
			'et' => 'Uudiseid veel ei ole.',
			'en' => 'No news yet.',
		),
		'news_all'         => array(
			'et' => 'Kõik uudised',
			'en' => 'All news',
		),
		'members_empty'    => array(
			'et' => 'Liikmeid veel ei ole.',
			'en' => 'No members yet.',
		),
		'company'          => array(
			'et' => 'Ettevõte',
			'en' => 'Company',
		),
		'website'          => array(
			'et' => 'Koduleht',
			'en' => 'Website',
		),
	);

	if ( ! isset( $strings[ $key ] ) ) {
		return $key;
	}
	return $strings[ $key ][ ak_lang() ] ?? $strings[ $key ]['et'];
}

/**
 * True when the current user's admin locale is English.
 */
function ak_admin_is_en(): bool {
	$locale = did_action( 'plugins_loaded' ) ? get_user_locale() : get_locale();
	return str_starts_with( (string) $locale, 'en' );
}

/**
 * Admin UI string: Estonian by default, English for English admin locales.
 */
function ak_at( string $et, string $en ): string {
	return ak_admin_is_en() ? $en : $et;
}
