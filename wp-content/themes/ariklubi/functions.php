<?php
/**
 * Valgamaa Äriklubi: GeneratePress child theme.
 *
 * Presentation only. Custom post types, shortcodes and other business logic
 * live in the ariklubi-core must-use plugin.
 *
 * @package ariklubi
 */

defined( 'ABSPATH' ) || exit;

define( 'ARIKLUBI_VERSION', '1.0.0' );

require_once __DIR__ . '/inc/palette.php';
require_once __DIR__ . '/inc/strings.php';
require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/generatepress.php';
require_once __DIR__ . '/inc/template.php';
