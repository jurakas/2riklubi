<?php
/**
 * Polylang integration: shared (language-neutral) content types.
 *
 * @package ariklubi-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Members, albums and synced patterns are entered once and shown in every language.
 *
 * @param array $post_types  Post types keyed by name.
 * @param bool  $is_settings True when building the list for the Polylang settings screen.
 */
function ak_pll_untranslated_post_types( $post_types, $is_settings = false ) {
	if ( ! is_array( $post_types ) ) {
		return $post_types;
	}
	foreach ( array( 'ak_member', 'ak_album', 'wp_block' ) as $type ) {
		unset( $post_types[ $type ] );
	}
	return $post_types;
}
add_filter( 'pll_get_post_types', 'ak_pll_untranslated_post_types', 100, 2 );

/**
 * Keep a widget's Polylang language when it is saved from the block widget editor or the
 * Customizer: those never submit the `pll_lang` field, and Polylang then drops the language,
 * which makes the ET and EN footer widgets show in both languages. The classic widget form
 * always submits the field (empty for "all languages"), so an explicit choice still wins.
 *
 * @param array|false $instance     Instance to be saved.
 * @param array       $new_instance Submitted instance.
 * @param array       $old_instance Stored instance.
 */
function ak_pll_keep_widget_language( $instance, $new_instance, $old_instance ) {
	if ( ! is_array( $instance ) || ! is_array( $old_instance ) || empty( $old_instance['pll_lang'] ) ) {
		return $instance;
	}
	if ( ! is_array( $new_instance ) || ! array_key_exists( 'pll_lang', $new_instance ) ) {
		$instance['pll_lang'] = $old_instance['pll_lang'];
	}
	return $instance;
}
add_filter( 'widget_update_callback', 'ak_pll_keep_widget_language', 20, 3 );

/**
 * URL of the posts (news) page in the current language, or '' when none is set.
 */
function ak_posts_page_url(): string {
	$page_id = (int) get_option( 'page_for_posts' );
	if ( ! $page_id ) {
		return '';
	}
	if ( function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $page_id );
		if ( $translated ) {
			$page_id = (int) $translated;
		}
	}
	$url = get_permalink( $page_id );
	return $url ? $url : '';
}
