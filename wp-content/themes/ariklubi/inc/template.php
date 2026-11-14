<?php
/**
 * Template tweaks through GeneratePress hooks and filters.
 *
 * @package ariklubi
 */

defined( 'ABSPATH' ) || exit;

/**
 * The home page starts with the hero band, so it has no page title.
 *
 * @param mixed $show Whether GeneratePress shows the title.
 */
function ariklubi_show_title( $show ): bool {
	return is_front_page() ? false : (bool) $show;
}
add_filter( 'generate_show_title', 'ariklubi_show_title' );
add_filter( 'generate_show_entry_header', 'ariklubi_show_title' );

/*
 * Post meta: date and categories only; no author, no comment links.
 */
add_filter( 'generate_post_author', '__return_false' );
add_filter( 'generate_show_comments', '__return_false' );

/**
 * URL of the privacy policy page in the current language, or '' if unset.
 */
function ariklubi_privacy_url(): string {
	$page_id = (int) get_option( 'wp_page_for_privacy_policy' );
	if ( $page_id <= 0 ) {
		return '';
	}

	if ( function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $page_id );
		if ( $translated ) {
			$page_id = (int) $translated;
		}
	}

	if ( 'publish' !== get_post_status( $page_id ) ) {
		return '';
	}

	$url = get_permalink( $page_id );

	return is_string( $url ) ? $url : '';
}

/**
 * Footer bottom bar: "© {year} Valgamaa Äriklubi MTÜ · Privacy policy".
 * Replaces the GeneratePress copyright line and its theme credit.
 */
function ariklubi_copyright(): string {
	$output = sprintf(
		'<span class="copyright">&copy; %1$s %2$s</span>',
		esc_html( wp_date( 'Y' ) ),
		esc_html( ariklubi_t( 'owner' ) )
	);

	$privacy_url = ariklubi_privacy_url();
	if ( '' !== $privacy_url ) {
		$output .= sprintf(
			'<span class="ak-sep" aria-hidden="true">&middot;</span><a class="ak-privacy-link" href="%1$s">%2$s</a>',
			esc_url( $privacy_url ),
			esc_html( ariklubi_t( 'privacy' ) )
		);
	}

	return $output;
}
add_filter( 'generate_copyright', 'ariklubi_copyright' );

/**
 * URL of the gallery page in the current language, or '' if not found.
 */
function ariklubi_gallery_url(): string {
	$page = get_page_by_path( 'galerii' );
	if ( ! $page instanceof WP_Post ) {
		return '';
	}

	$page_id = $page->ID;
	if ( function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $page_id );
		if ( $translated ) {
			$page_id = (int) $translated;
		}
	}

	if ( 'publish' !== get_post_status( $page_id ) ) {
		return '';
	}

	$url = get_permalink( $page_id );

	return is_string( $url ) ? $url : '';
}

/**
 * "Back to the gallery" link below a single album.
 */
function ariklubi_album_back_link(): void {
	if ( ! is_singular( 'ak_album' ) ) {
		return;
	}

	$url = ariklubi_gallery_url();
	if ( '' === $url ) {
		return;
	}

	printf(
		'<p class="ak-back-link"><a href="%1$s"><span aria-hidden="true">&larr;</span> %2$s</a></p>',
		esc_url( $url ),
		esc_html( ariklubi_t( 'back_to_gallery' ) )
	);
}
add_action( 'generate_after_content', 'ariklubi_album_back_link', 20 );

/**
 * The album cover is only for the gallery card; the album page shows the gallery itself.
 */
function ariklubi_album_hide_featured_image(): void {
	if ( is_singular( 'ak_album' ) ) {
		remove_action( 'generate_after_header', 'generate_featured_page_header', 10 );
		remove_action( 'generate_before_content', 'generate_featured_page_header_inside_single', 10 );
	}
}
add_action( 'wp', 'ariklubi_album_hide_featured_image' );

/**
 * On a single album, highlight the gallery menu item instead of the posts page.
 *
 * @param string[] $classes Menu item classes.
 * @param WP_Post  $item    Menu item.
 * @return string[]
 */
function ariklubi_album_menu_classes( $classes, $item ): array {
	$classes = (array) $classes;
	if ( ! is_singular( 'ak_album' ) || 'post_type' !== $item->type ) {
		return $classes;
	}

	$classes = array_diff( $classes, array( 'current_page_parent', 'current-menu-ancestor', 'current-menu-parent' ) );
	$gallery = ariklubi_gallery_url();
	if ( '' !== $gallery && untrailingslashit( (string) get_permalink( (int) $item->object_id ) ) === untrailingslashit( $gallery ) ) {
		$classes[] = 'current-menu-ancestor';
		$classes[] = 'current_page_parent';
	}

	return array_values( $classes );
}
add_filter( 'nav_menu_css_class', 'ariklubi_album_menu_classes', 10, 2 );
