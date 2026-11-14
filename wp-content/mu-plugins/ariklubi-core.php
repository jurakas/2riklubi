<?php
/**
 * Plugin Name: Ariklubi Core
 * Description: Valgamaa Äriklubi site logic: albums, members, shortcodes, localisation helpers, hardening and Open Graph meta.
 * Version: 1.0.0
 * Author: Valgamaa Äriklubi
 * Text Domain: ariklubi-core
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'AK_CORE_VERSION', '1.0.0' );
define( 'AK_CORE_DIR', __DIR__ . '/ariklubi-core' );

foreach ( array(
	'i18n.php',
	'polylang.php',
	'cpt-album.php',
	'cpt-member.php',
	'shortcodes.php',
	'hardening.php',
	'mail.php',
	'seo.php',
) as $ak_core_file ) {
	require_once AK_CORE_DIR . '/' . $ak_core_file;
}
unset( $ak_core_file );
