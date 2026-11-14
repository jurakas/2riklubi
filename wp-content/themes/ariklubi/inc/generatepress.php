<?php
/**
 * GeneratePress defaults: brand colours, layout and typography.
 *
 * These are *defaults*: they apply as long as the matching key has not been
 * saved in the `generate_settings` option through the Customizer.
 *
 * @package ariklubi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Colour settings. Values reference the CSS custom properties GeneratePress
 * prints for each global colour (`--navy-900`, `--navy-700`, `--navy-500`,
 * `--cream`), which come from ariklubi_palette().
 *
 * @return array<string, string>
 */
function ariklubi_gp_color_defaults(): array {
	$n900  = 'var(--navy-900)';
	$n700  = 'var(--navy-700)';
	$n500  = 'var(--navy-500)';
	$cream = 'var(--cream)';

	return array(
		'background_color'                        => $cream,
		'text_color'                              => $n900,
		'link_color'                              => $n500,
		'link_color_hover'                        => $n700,
		'link_color_visited'                      => '',

		'header_background_color'                 => $n900,
		'header_text_color'                       => $cream,
		'header_link_color'                       => $cream,
		'header_link_hover_color'                 => $cream,
		'site_title_color'                        => $cream,
		'site_tagline_color'                      => $cream,

		'navigation_background_color'             => $n900,
		'navigation_text_color'                   => $cream,
		'navigation_background_hover_color'       => $n900,
		'navigation_text_hover_color'             => $cream,
		'navigation_background_current_color'     => $n900,
		'navigation_text_current_color'           => $cream,

		'subnavigation_background_color'          => $n700,
		'subnavigation_text_color'                => $cream,
		'subnavigation_background_hover_color'    => $n500,
		'subnavigation_text_hover_color'          => $cream,
		'subnavigation_background_current_color'  => $n500,
		'subnavigation_text_current_color'        => $cream,

		'navigation_search_background_color'      => $n700,
		'navigation_search_text_color'            => $cream,

		'content_background_color'                => $cream,
		'content_text_color'                      => $n900,
		'content_link_color'                      => $n500,
		'content_link_hover_color'                => $n700,
		'content_title_color'                     => $n700,
		'blog_post_title_color'                   => $n700,
		'blog_post_title_hover_color'             => $n500,
		'entry_meta_text_color'                   => $n500,
		'entry_meta_link_color'                   => $n500,
		'entry_meta_link_color_hover'             => $n900,
		'h1_color'                                => $n700,
		'h2_color'                                => $n700,
		'h3_color'                                => $n700,
		'h4_color'                                => $n700,
		'h5_color'                                => $n700,
		'h6_color'                                => $n700,

		'sidebar_widget_background_color'         => $cream,
		'sidebar_widget_text_color'               => $n900,
		'sidebar_widget_link_color'               => $n500,
		'sidebar_widget_link_hover_color'         => $n900,
		'sidebar_widget_title_color'              => $n700,

		'footer_widget_background_color'          => $n700,
		'footer_widget_text_color'                => $cream,
		'footer_widget_link_color'                => $cream,
		'footer_widget_link_hover_color'          => $cream,
		'footer_widget_title_color'               => $cream,

		'footer_background_color'                 => $n900,
		'footer_text_color'                       => $cream,
		'footer_link_color'                       => $cream,
		'footer_link_hover_color'                 => $cream,

		'form_background_color'                   => $cream,
		'form_text_color'                         => $n900,
		'form_background_color_focus'             => $cream,
		'form_text_color_focus'                   => $n900,
		'form_border_color'                       => 'rgba(34,57,111,0.5)',
		'form_border_color_focus'                 => $n500,
		'form_button_background_color'            => $n500,
		'form_button_background_color_hover'      => $n700,
		'form_button_text_color'                  => $cream,
		'form_button_text_color_hover'            => $cream,

		'back_to_top_background_color'            => $n500,
		'back_to_top_background_color_hover'      => $n700,
		'back_to_top_text_color'                  => $cream,
		'back_to_top_text_color_hover'            => $cream,

		'search_modal_bg_color'                   => $n900,
		'search_modal_text_color'                 => $cream,
		'search_modal_overlay_bg_color'           => 'rgba(1,7,54,0.6)',
	);
}

/**
 * Layout and general settings.
 *
 * @return array<string, mixed>
 */
function ariklubi_gp_layout_defaults(): array {
	return array(
		'container_width'          => '1100',
		'content_layout_setting'   => 'one-container',
		'layout_setting'           => 'no-sidebar',
		'blog_layout_setting'      => 'no-sidebar',
		'single_layout_setting'    => 'no-sidebar',
		'header_layout_setting'    => 'fluid-header',
		'header_inner_width'       => 'contained',
		'header_alignment_setting' => 'left',
		'nav_layout_setting'       => 'fluid-nav',
		'nav_inner_width'          => 'contained',
		'nav_position_setting'     => 'nav-float-right',
		'nav_alignment_setting'    => 'right',
		'nav_dropdown_type'        => 'hover',
		'nav_search'               => 'disable',
		'footer_layout_setting'    => 'fluid-footer',
		'footer_inner_width'       => 'contained',
		'footer_widget_setting'    => '2',
		'footer_bar_alignment'     => 'center',
		'back_to_top'              => '',
		'post_content'             => 'excerpt',
		'underline_links'          => 'never',
		'structure'                => 'flexbox',
		'icons'                    => 'svg',
		'font_awesome_essentials'  => false,
		'combine_css'              => true,
		'google_font_display'      => 'swap',
		'font_manager'             => array(),
		'typography'               => array(),
	);
}

/**
 * Main GeneratePress option defaults.
 *
 * @param mixed $defaults GeneratePress defaults.
 * @return array<string, mixed>
 */
function ariklubi_gp_option_defaults( $defaults ): array {
	$defaults = (array) $defaults;

	$defaults['global_colors'] = ariklubi_palette();

	return array_merge( $defaults, ariklubi_gp_layout_defaults(), ariklubi_gp_color_defaults() );
}
add_filter( 'generate_option_defaults', 'ariklubi_gp_option_defaults', 20 );

/**
 * Colour defaults (GeneratePress keeps these in a separate defaults array).
 *
 * @param mixed $defaults GeneratePress colour defaults.
 * @return array<string, mixed>
 */
function ariklubi_gp_color_option_defaults( $defaults ): array {
	return array_merge( (array) $defaults, ariklubi_gp_color_defaults() );
}
add_filter( 'generate_color_option_defaults', 'ariklubi_gp_color_option_defaults', 20 );

/**
 * Legacy typography defaults: no Google fonts, the stylesheet sets the fonts.
 *
 * @param mixed $defaults GeneratePress font defaults.
 * @return array<string, mixed>
 */
function ariklubi_gp_font_option_defaults( $defaults ): array {
	$defaults = (array) $defaults;

	foreach ( array( 'font_body', 'font_site_title', 'font_navigation', 'font_buttons', 'font_heading_1', 'font_heading_2', 'font_heading_3', 'font_heading_4', 'font_heading_5', 'font_heading_6', 'font_footer', 'font_widget_title', 'font_site_tagline' ) as $key ) {
		$defaults[ $key ] = 'inherit';
	}

	$defaults['font_body_category'] = '';
	$defaults['font_body_variants'] = '';

	return $defaults;
}
add_filter( 'generate_font_option_defaults', 'ariklubi_gp_font_option_defaults', 20 );

/**
 * Full-width content everywhere: no sidebars.
 */
add_filter(
	'generate_sidebar_layout',
	static function (): string {
		return 'no-sidebar';
	}
);

/**
 * Two footer widget columns: footer-1 (contact) and footer-2 (about).
 */
add_filter(
	'generate_footer_widgets',
	static function (): string {
		return '2';
	}
);
