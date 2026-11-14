<?php
/**
 * Brand palette: the only colours the site may use.
 *
 * @package ariklubi
 */

defined( 'ABSPATH' ) || exit;

/**
 * The four brand colours in the shape used by both GeneratePress
 * (`global_colors`) and `add_theme_support( 'editor-color-palette' )`.
 *
 * @return array<int, array{name: string, slug: string, color: string}>
 */
function ariklubi_palette(): array {
	return array(
		array(
			'name'  => 'Navy 900',
			'slug'  => 'navy-900',
			'color' => '#010736',
		),
		array(
			'name'  => 'Navy 700',
			'slug'  => 'navy-700',
			'color' => '#0D1C42',
		),
		array(
			'name'  => 'Navy 500',
			'slug'  => 'navy-500',
			'color' => '#22396F',
		),
		array(
			'name'  => 'Cream',
			'slug'  => 'cream',
			'color' => '#FCF1D0',
		),
	);
}
