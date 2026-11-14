<?php
/**
 * CPT ak_album: gallery albums built from the core Gallery block.
 *
 * @package ariklubi-core
 */

defined( 'ABSPATH' ) || exit;

function ak_register_album_post_type(): void {
	$labels = array(
		'name'                  => ak_at( 'Galerii albumid', 'Gallery albums' ),
		'singular_name'         => ak_at( 'Album', 'Album' ),
		'menu_name'             => ak_at( 'Galerii albumid', 'Gallery albums' ),
		'all_items'             => ak_at( 'Kõik albumid', 'All albums' ),
		'add_new'               => ak_at( 'Lisa uus', 'Add new' ),
		'add_new_item'          => ak_at( 'Lisa uus album', 'Add new album' ),
		'edit_item'             => ak_at( 'Muuda albumit', 'Edit album' ),
		'new_item'              => ak_at( 'Uus album', 'New album' ),
		'view_item'             => ak_at( 'Vaata albumit', 'View album' ),
		'view_items'            => ak_at( 'Vaata albumeid', 'View albums' ),
		'search_items'          => ak_at( 'Otsi albumeid', 'Search albums' ),
		'not_found'             => ak_at( 'Albumeid ei leitud.', 'No albums found.' ),
		'not_found_in_trash'    => ak_at( 'Prügikastis albumeid ei ole.', 'No albums found in Trash.' ),
		'featured_image'        => ak_at( 'Kaanepilt', 'Cover image' ),
		'set_featured_image'    => ak_at( 'Määra kaanepilt', 'Set cover image' ),
		'remove_featured_image' => ak_at( 'Eemalda kaanepilt', 'Remove cover image' ),
		'use_featured_image'    => ak_at( 'Kasuta kaanepildina', 'Use as cover image' ),
		'item_published'        => ak_at( 'Album avaldatud.', 'Album published.' ),
		'item_updated'          => ak_at( 'Album uuendatud.', 'Album updated.' ),
	);

	register_post_type(
		'ak_album',
		array(
			'labels'          => $labels,
			'public'          => true,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => true,
			'menu_position'   => 21,
			'menu_icon'       => 'dashicons-format-gallery',
			'has_archive'     => false,
			'rewrite'         => array(
				'slug'       => 'album',
				'with_front' => false,
			),
			'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			'capability_type' => 'post',
			'map_meta_cap'    => true,
			'template'        => array(
				array(
					'core/gallery',
					array(
						'columns'  => 3,
						'linkTo'   => 'none',
						'sizeSlug' => 'large',
					),
				),
			),
		)
	);
}
add_action( 'init', 'ak_register_album_post_type' );

/**
 * Enable the core image lightbox ("Expand on click") by default; the parent theme has no theme.json.
 *
 * @param WP_Theme_JSON_Data $theme_json Theme origin data.
 */
function ak_enable_image_lightbox( $theme_json ) {
	if ( ! is_object( $theme_json ) || ! method_exists( $theme_json, 'update_with' ) ) {
		return $theme_json;
	}
	$version = class_exists( 'WP_Theme_JSON' ) && defined( 'WP_Theme_JSON::LATEST_SCHEMA' ) ? WP_Theme_JSON::LATEST_SCHEMA : 2;
	return $theme_json->update_with(
		array(
			'version'  => $version,
			'settings' => array(
				'blocks' => array(
					'core/image' => array(
						'lightbox' => array(
							'enabled'      => true,
							'allowEditing' => true,
						),
					),
				),
			),
		)
	);
}
add_filter( 'wp_theme_json_data_theme', 'ak_enable_image_lightbox' );

/**
 * Attachment IDs of the images placed in a post's blocks, in document order.
 *
 * @param WP_Post|int $post Post object or ID.
 * @return int[]
 */
function ak_album_image_ids( $post ): array {
	$post = get_post( $post );
	if ( ! $post || '' === $post->post_content || ! function_exists( 'parse_blocks' ) ) {
		return array();
	}
	$ids = array();
	ak_collect_block_image_ids( parse_blocks( $post->post_content ), $ids );
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * @param array $blocks Parsed blocks.
 * @param int[] $ids    Collected IDs (by reference).
 */
function ak_collect_block_image_ids( array $blocks, array &$ids ): void {
	foreach ( $blocks as $block ) {
		$name  = $block['blockName'] ?? '';
		$attrs = $block['attrs'] ?? array();

		if ( 'core/image' === $name && ! empty( $attrs['id'] ) ) {
			$ids[] = (int) $attrs['id'];
		} elseif ( 'core/gallery' === $name && ! empty( $attrs['ids'] ) && is_array( $attrs['ids'] ) ) {
			foreach ( $attrs['ids'] as $id ) {
				$ids[] = (int) $id;
			}
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			ak_collect_block_image_ids( $block['innerBlocks'], $ids );
		}
	}
}

/**
 * Cover attachment ID: featured image, else first block image, else first attached image; 0 if none.
 *
 * @param WP_Post|int $post Post object or ID.
 */
function ak_album_cover_id( $post ): int {
	$post = get_post( $post );
	if ( ! $post ) {
		return 0;
	}
	$thumb = (int) get_post_thumbnail_id( $post );
	if ( $thumb ) {
		return $thumb;
	}
	foreach ( ak_album_image_ids( $post ) as $id ) {
		if ( wp_attachment_is_image( $id ) ) {
			return $id;
		}
	}
	$children = get_children(
		array(
			'post_parent'    => $post->ID,
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'numberposts'    => 1,
			'orderby'        => 'menu_order ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);
	return $children ? (int) reset( $children ) : 0;
}

function ak_album_admin_columns( array $columns ): array {
	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'title' === $key ) {
			$new['ak_cover'] = ak_at( 'Kaanepilt', 'Cover' );
		}
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['ak_images'] = ak_at( 'Pilte', 'Images' );
		}
	}
	return $new;
}
add_filter( 'manage_ak_album_posts_columns', 'ak_album_admin_columns' );

function ak_album_admin_column_content( string $column, int $post_id ): void {
	if ( 'ak_images' === $column ) {
		echo esc_html( (string) count( ak_album_image_ids( $post_id ) ) );
	} elseif ( 'ak_cover' === $column ) {
		$cover = ak_album_cover_id( $post_id );
		if ( $cover ) {
			echo wp_get_attachment_image( $cover, array( 60, 60 ), false, array( 'style' => 'width:60px;height:auto;' ) );
		} else {
			echo '&mdash;';
		}
	}
}
add_action( 'manage_ak_album_posts_custom_column', 'ak_album_admin_column_content', 10, 2 );

function ak_album_admin_column_styles(): void {
	$screen = get_current_screen();
	if ( $screen && 'edit-ak_album' === $screen->id ) {
		echo '<style>.column-ak_cover{width:72px}.column-ak_images{width:80px}</style>';
	}
}
add_action( 'admin_head', 'ak_album_admin_column_styles' );
