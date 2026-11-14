<?php
/**
 * Theme supports, assets and editor styles.
 *
 * @package ariklubi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cache-busting version for a theme asset (file modification time).
 *
 * @param string $relative_path Path relative to the child theme directory.
 */
function ariklubi_asset_version( string $relative_path ): string {
	$file  = get_stylesheet_directory() . '/' . ltrim( $relative_path, '/' );
	$mtime = is_readable( $file ) ? filemtime( $file ) : false;

	return false === $mtime ? ARIKLUBI_VERSION : (string) $mtime;
}

/**
 * Theme supports. Runs after GeneratePress' own setup (priority 10) so the
 * child theme's palette and logo settings win.
 */
function ariklubi_setup(): void {
	load_child_theme_textdomain( 'ariklubi', get_stylesheet_directory() . '/languages' );

	add_theme_support(
		'custom-logo',
		array(
			'height'      => 300,
			'width'       => 1200,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);

	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	add_theme_support( 'editor-color-palette', ariklubi_palette() );
	add_theme_support( 'disable-custom-colors' );
	add_theme_support( 'disable-custom-gradients' );
	add_theme_support( 'editor-gradient-presets', array() );

	add_theme_support( 'editor-styles' );
	add_editor_style( array( 'assets/css/fonts.css', 'assets/css/editor.css' ) );
}
add_action( 'after_setup_theme', 'ariklubi_setup', 20 );

/*
 * GeneratePress enqueues style.css of a child theme on its own; the child
 * enqueues it itself below so that it loads after the fonts with filemtime
 * versioning.
 */
add_filter( 'generate_load_child_theme_stylesheet', '__return_false' );

/**
 * Front-end styles.
 */
function ariklubi_enqueue_assets(): void {
	$uri = get_stylesheet_directory_uri();

	wp_dequeue_style( 'generate-child' );

	wp_enqueue_style(
		'ariklubi-fonts',
		$uri . '/assets/css/fonts.css',
		array(),
		ariklubi_asset_version( 'assets/css/fonts.css' )
	);

	$deps = array( 'ariklubi-fonts' );
	if ( wp_style_is( 'generate-style', 'registered' ) ) {
		$deps[] = 'generate-style';
	}

	wp_enqueue_style(
		'ariklubi-style',
		get_stylesheet_uri(),
		$deps,
		ariklubi_asset_version( 'style.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'ariklubi_enqueue_assets', 20 );

/**
 * Never load fonts from Google (GDPR); all fonts are self-hosted.
 */
function ariklubi_dequeue_remote_fonts(): void {
	foreach ( array( 'generate-google-fonts', 'generate-fonts' ) as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}
}
add_action( 'wp_enqueue_scripts', 'ariklubi_dequeue_remote_fonts', 100 );
add_action( 'enqueue_block_editor_assets', 'ariklubi_dequeue_remote_fonts', 100 );

/**
 * Preload the body font (latin subset) so text renders in the brand font
 * without a visible swap.
 */
function ariklubi_preload_fonts(): void {
	printf(
		'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
		esc_url( get_stylesheet_directory_uri() . '/assets/fonts/source-sans-3-latin-400-normal.woff2' )
	);
}
add_action( 'wp_head', 'ariklubi_preload_fonts', 2 );

/**
 * Body classes used by the stylesheet.
 *
 * @param mixed $classes Body classes.
 * @return array<int, string>
 */
function ariklubi_body_classes( $classes ): array {
	$classes = (array) $classes;

	if ( has_custom_logo() ) {
		$classes[] = 'ak-has-logo';
	}

	if ( is_front_page() ) {
		$classes[] = 'ak-front';
	}

	return $classes;
}
add_filter( 'body_class', 'ariklubi_body_classes' );
