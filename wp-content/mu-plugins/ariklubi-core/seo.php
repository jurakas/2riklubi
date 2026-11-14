<?php
/**
 * Meta description and basic Open Graph tags.
 *
 * @package ariklubi-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Attachment ID of the brand OG image (option ak_og_image_id, or meta _ak_key = 'brand:og-image'), or 0.
 */
function ak_brand_og_image_id(): int {
	$option = (int) get_option( 'ak_og_image_id', 0 );
	if ( $option && wp_attachment_is_image( $option ) ) {
		return $option;
	}
	$cached = wp_cache_get( 'ak_brand_og_image_id', 'ariklubi-core' );
	if ( false !== $cached ) {
		return (int) $cached;
	}
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_ak_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => 'brand:og-image', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'no_found_rows'  => true,
			'lang'           => '',
		)
	);
	$id = $ids ? (int) $ids[0] : 0;
	wp_cache_set( 'ak_brand_og_image_id', $id, 'ariklubi-core', HOUR_IN_SECONDS );
	return $id;
}

/**
 * Image for og:image as [url, width, height], or null.
 *
 * @return array{0:string,1:int,2:int}|null
 */
function ak_og_image(): ?array {
	$id = 0;
	if ( is_singular() && has_post_thumbnail() ) {
		$id = (int) get_post_thumbnail_id();
	}
	if ( ! $id ) {
		$id = ak_brand_og_image_id();
	}
	if ( $id ) {
		$src = wp_get_attachment_image_src( $id, 'full' );
		if ( $src ) {
			return array( (string) $src[0], (int) $src[1], (int) $src[2] );
		}
	}
	$icon = get_site_icon_url( 512 );
	return $icon ? array( $icon, 512, 512 ) : null;
}

function ak_meta_description(): string {
	if ( is_singular() && ! is_front_page() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$text = ak_plain_excerpt( $post, 30 );
			if ( '' !== $text ) {
				return $text;
			}
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = wp_strip_all_tags( term_description() );
		if ( '' !== trim( $text ) ) {
			return trim( $text );
		}
	}
	return (string) get_bloginfo( 'description' );
}

function ak_og_url(): string {
	if ( is_front_page() ) {
		return function_exists( 'pll_home_url' ) ? (string) pll_home_url() : home_url( '/' );
	}
	if ( is_singular() || is_home() ) {
		$id = get_queried_object_id();
		return $id ? (string) get_permalink( $id ) : '';
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$link = get_term_link( get_queried_object() );
		return is_string( $link ) ? $link : '';
	}
	return '';
}

function ak_print_head_meta(): void {
	if ( is_404() || is_feed() ) {
		return;
	}

	$description = ak_meta_description();
	$title       = wp_get_document_title();
	$url         = ak_og_url();
	$image       = ak_og_image();

	if ( '' !== $description ) {
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
	}
	printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:locale" content="%s">' . "\n", esc_attr( 'en' === ak_lang() ? 'en_US' : 'et_EE' ) );
	printf( '<meta property="og:type" content="%s">' . "\n", esc_attr( is_singular( 'post' ) ? 'article' : 'website' ) );
	printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
	if ( '' !== $description ) {
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
	}
	if ( '' !== $url ) {
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $url ) );
	}
	if ( $image ) {
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image[0] ) );
		if ( $image[1] && $image[2] ) {
			printf( '<meta property="og:image:width" content="%d">' . "\n", (int) $image[1] );
			printf( '<meta property="og:image:height" content="%d">' . "\n", (int) $image[2] );
		}
	}
}
add_action( 'wp_head', 'ak_print_head_meta', 5 );
