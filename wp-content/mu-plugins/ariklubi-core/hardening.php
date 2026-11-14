<?php
/**
 * Hardening and upkeep: auto-updates, no comments, no XML-RPC,
 * no user enumeration, no version disclosure.
 *
 * @package ariklubi-core
 */

defined( 'ABSPATH' ) || exit;

/*
 * Automatic updates (WP_AUTO_UPDATE_CORE is set in wp-config.php).
 */
add_filter( 'auto_update_core', '__return_true' );
add_filter( 'allow_major_auto_core_updates', '__return_true' );
add_filter( 'allow_minor_auto_core_updates', '__return_true' );
add_filter( 'auto_update_plugin', '__return_true' );
add_filter( 'auto_update_theme', '__return_true' );
add_filter( 'auto_update_translation', '__return_true' );
// Core and plugins are never VCS-managed on this site; a stray .git in a parent directory must not block updates.
add_filter( 'automatic_updates_is_vcs_checkout', '__return_false' );

/*
 * Comments and pingbacks: disabled everywhere.
 */
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );
add_filter( 'feed_links_show_comments_feed', '__return_false' );
add_filter( 'show_recent_comments_widget_style', '__return_false' );
add_filter( 'pre_option_default_comment_status', static fn() => 'closed' );
add_filter( 'pre_option_default_ping_status', static fn() => 'closed' );
add_filter( 'pre_option_default_pingback_flag', static fn() => '0' );

function ak_remove_comment_support(): void {
	foreach ( get_post_types() as $post_type ) {
		if ( post_type_supports( $post_type, 'comments' ) ) {
			remove_post_type_support( $post_type, 'comments' );
		}
		if ( post_type_supports( $post_type, 'trackbacks' ) ) {
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}
}
add_action( 'init', 'ak_remove_comment_support', 100 );

function ak_remove_comments_admin_menu(): void {
	remove_menu_page( 'edit-comments.php' );
}
add_action( 'admin_menu', 'ak_remove_comments_admin_menu', 999 );

function ak_redirect_comments_admin(): void {
	global $pagenow;
	if ( 'edit-comments.php' === $pagenow || 'comment.php' === $pagenow ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
add_action( 'admin_init', 'ak_redirect_comments_admin' );

function ak_remove_comments_dashboard_widget(): void {
	remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
}
add_action( 'wp_dashboard_setup', 'ak_remove_comments_dashboard_widget', 999 );

/**
 * @param WP_Admin_Bar $admin_bar Admin bar.
 */
function ak_remove_comments_admin_bar( $admin_bar ): void {
	$admin_bar->remove_node( 'comments' );
}
add_action( 'admin_bar_menu', 'ak_remove_comments_admin_bar', 999 );

function ak_unregister_comment_widgets(): void {
	unregister_widget( 'WP_Widget_Recent_Comments' );
}
add_action( 'widgets_init', 'ak_unregister_comment_widgets', 20 );

/*
 * XML-RPC and pingbacks.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

function ak_block_xmlrpc(): void {
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'Forbidden';
		exit;
	}
}
add_action( 'plugins_loaded', 'ak_block_xmlrpc', 0 );

function ak_remove_pingback_header( $headers ) {
	if ( is_array( $headers ) ) {
		unset( $headers['X-Pingback'] );
	}
	return $headers;
}
add_filter( 'wp_headers', 'ak_remove_pingback_header' );

/*
 * User enumeration and author archives.
 */
function ak_block_author_enumeration(): void {
	if ( is_admin() ) {
		return;
	}
	if ( isset( $_GET['author'] ) || is_author() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'ak_block_author_enumeration', 1 );

add_filter( 'author_rewrite_rules', '__return_empty_array' );

function ak_restrict_user_endpoints( $endpoints ) {
	if ( is_user_logged_in() || ! is_array( $endpoints ) ) {
		return $endpoints;
	}
	foreach ( array_keys( $endpoints ) as $route ) {
		if ( str_starts_with( (string) $route, '/wp/v2/users' ) ) {
			unset( $endpoints[ $route ] );
		}
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'ak_restrict_user_endpoints' );

function ak_remove_users_sitemap( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}
add_filter( 'wp_sitemaps_add_provider', 'ak_remove_users_sitemap', 10, 2 );

function ak_strip_oembed_author( $data ) {
	if ( is_array( $data ) ) {
		unset( $data['author_name'], $data['author_url'] );
	}
	return $data;
}
add_filter( 'oembed_response_data', 'ak_strip_oembed_author' );

/*
 * Version disclosure.
 */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
