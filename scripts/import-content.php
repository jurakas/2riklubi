<?php
/**
 * Imports content/ (PLAN.md Contract 4) and brand/ into WordPress. Idempotent.
 *
 * Run with: wp eval-file scripts/import-content.php (scripts/setup.sh does this,
 * after configure-polylang.php).
 *
 * Environment:
 *   CONTENT_DIR, BRAND_DIR   default: ../content and ../brand relative to AK_SCRIPTS_DIR
 *   AK_FORCE_UPDATE=1        overwrite imported items even if they were edited in wp-admin
 *   AK_RECREATE_DELETED=1    re-create imported items that were deleted in wp-admin
 *
 * Every imported post carries the meta _ak_key. On re-runs an item is only updated when
 * the source changed AND nobody edited it in wp-admin since the last import (hashes in
 * _ak_src_hash / _ak_db_hash), so re-running on a live site does not clobber edits.
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_CLI' ) ) {
	echo "Run with: wp eval-file import-content.php\n";
	return;
}

if ( ! function_exists( 'ak_ic_flag' ) ) {

	function ak_ic_flag( string $name ): bool {
		$value = getenv( $name );
		return false !== $value && in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
	}

	function ak_ic_dir( string $env, string $fallback_name ): string {
		$dir = (string) getenv( $env );
		if ( '' === $dir ) {
			$scripts = (string) getenv( 'AK_SCRIPTS_DIR' );
			$dir     = ( '' !== $scripts ? dirname( $scripts ) : getcwd() ) . '/' . $fallback_name;
		}
		return rtrim( str_replace( '\\', '/', $dir ), '/' );
	}

	function ak_ic_stat( string $what ): void {
		if ( ! isset( $GLOBALS['ak_ic_stats'][ $what ] ) ) {
			$GLOBALS['ak_ic_stats'][ $what ] = 0;
		}
		++$GLOBALS['ak_ic_stats'][ $what ];
	}

	function ak_ic_strip_bom( string $text ): string {
		return 0 === strpos( $text, "\xEF\xBB\xBF" ) ? substr( $text, 3 ) : $text;
	}

	/** @return array|null */
	function ak_ic_read_json( string $dir, string $rel, bool $required = true ) {
		$path = $dir . '/' . $rel;
		if ( ! is_readable( $path ) ) {
			if ( $required ) {
				WP_CLI::warning( "Missing {$rel}; skipping it." );
			}
			return null;
		}
		$data = json_decode( ak_ic_strip_bom( (string) file_get_contents( $path ) ), true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			WP_CLI::warning( "Invalid JSON in {$rel}: " . json_last_error_msg() );
			return null;
		}
		return is_array( $data ) ? $data : null;
	}

	function ak_ic_read_file( string $dir, string $rel ): ?string {
		$path = $dir . '/' . ltrim( str_replace( '\\', '/', $rel ), '/' );
		if ( ! is_readable( $path ) ) {
			WP_CLI::warning( "Missing content file {$rel}." );
			return null;
		}
		return trim( str_replace( "\r\n", "\n", ak_ic_strip_bom( (string) file_get_contents( $path ) ) ) );
	}

	/** "2024-11-04" or "2024-11-04 18:00[:00]" to "Y-m-d H:i:s"; '' when empty or invalid. */
	function ak_ic_date( $value ): string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			$value .= ' 12:00:00';
		} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $value ) ) {
			$value .= ':00';
		}
		$value = str_replace( 'T', ' ', $value );
		$dt    = DateTime::createFromFormat( 'Y-m-d H:i:s', $value );
		if ( ! $dt || $dt->format( 'Y-m-d H:i:s' ) !== $value ) {
			WP_CLI::warning( "Ignoring invalid date '{$value}'." );
			return '';
		}
		return $value;
	}

	function ak_ic_find_by_key( string $post_type, string $key ): int {
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash', 'inherit' ),
				'meta_key'         => '_ak_key', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'posts_per_page'   => 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'lang'             => '',
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/** Hash of the editable state as stored, used to detect edits made in wp-admin. */
	function ak_ic_db_hash( int $id, array $meta_keys ): string {
		clean_post_cache( $id );
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}
		$data = array( $post->post_title, $post->post_content, $post->post_name );
		foreach ( $meta_keys as $meta_key ) {
			$data[] = (string) get_post_meta( $id, $meta_key, true );
		}
		return md5( (string) wp_json_encode( $data ) );
	}

	/**
	 * Creates or updates the post identified by $key.
	 *
	 * @param array $fields      Post fields applied on create and update (must include post_type).
	 * @param array $meta        Post meta applied on create and update.
	 * @param array $create_only Post fields applied only on create (status, dates...).
	 * @return int Post ID, or 0 when skipped.
	 */
	function ak_ic_upsert( string $key, array $fields, array $meta = array(), array $create_only = array() ): int {
		$type     = $fields['post_type'];
		$src_hash = md5( (string) wp_json_encode( array( $fields, $meta, $create_only ) ) );
		$registry = get_option( 'ak_import_registry', array() );
		if ( ! is_array( $registry ) ) {
			$registry = array();
		}
		$id = ak_ic_find_by_key( $type, $key );

		if ( $id && 'trash' === get_post_status( $id ) ) {
			WP_CLI::log( "  - {$key}: in the trash, left alone" );
			ak_ic_stat( 'skipped' );
			return 0;
		}
		if ( ! $id && isset( $registry[ $key ] ) && ! ak_ic_flag( 'AK_RECREATE_DELETED' ) ) {
			WP_CLI::log( "  - {$key}: deleted in wp-admin earlier, not re-created (AK_RECREATE_DELETED=1 to override)" );
			ak_ic_stat( 'skipped' );
			return 0;
		}

		$meta_keys = array_keys( $meta );
		if ( $id ) {
			if ( get_post_meta( $id, '_ak_src_hash', true ) === $src_hash ) {
				ak_ic_stat( 'unchanged' );
				return $id;
			}
			$edited = get_post_meta( $id, '_ak_db_hash', true ) !== ak_ic_db_hash( $id, $meta_keys );
			if ( $edited && ! ak_ic_flag( 'AK_FORCE_UPDATE' ) ) {
				WP_CLI::warning( "{$key} (#{$id}) was edited in wp-admin; not overwritten (AK_FORCE_UPDATE=1 to overwrite)." );
				ak_ic_stat( 'kept (edited)' );
				return $id;
			}
			$result = wp_update_post( wp_slash( array_merge( $fields, array( 'ID' => $id ) ) ), true );
			$action = 'updated';
		} else {
			$result = wp_insert_post( wp_slash( array_merge( $fields, $create_only ) ), true );
			$action = 'created';
		}

		if ( is_wp_error( $result ) ) {
			WP_CLI::warning( "{$key}: " . $result->get_error_message() );
			ak_ic_stat( 'errors' );
			return (int) $id;
		}
		$id = (int) $result;
		foreach ( $meta as $meta_key => $meta_value ) {
			update_post_meta( $id, $meta_key, wp_slash( $meta_value ) );
		}
		update_post_meta( $id, '_ak_key', $key );
		update_post_meta( $id, '_ak_src_hash', $src_hash );
		update_post_meta( $id, '_ak_db_hash', ak_ic_db_hash( $id, $meta_keys ) );

		$registry[ $key ] = $id;
		update_option( 'ak_import_registry', $registry, false );

		ak_ic_stat( $action );
		WP_CLI::log( "  + {$key}: {$action} #{$id}" );
		return $id;
	}

	function ak_ic_has_lang( string $slug ): bool {
		static $slugs = null;
		if ( null === $slugs ) {
			$slugs = function_exists( 'pll_languages_list' ) ? (array) pll_languages_list( array( 'fields' => 'slug' ) ) : array();
		}
		return in_array( $slug, $slugs, true );
	}

	function ak_ic_set_post_lang( int $id, string $lang ): void {
		if ( $id && ak_ic_has_lang( $lang ) && function_exists( 'pll_set_post_language' ) ) {
			if ( pll_get_post_language( $id ) !== $lang ) {
				pll_set_post_language( $id, $lang );
			}
		}
	}

	function ak_ic_link_posts( array $by_lang ): void {
		$by_lang = array_filter( $by_lang );
		if ( count( $by_lang ) > 1 && function_exists( 'pll_save_post_translations' ) ) {
			pll_save_post_translations( $by_lang );
		}
	}

	function ak_ic_replace_placeholders( string $html, array $pattern_ids ): string {
		$callback = static function ( array $m ) use ( $pattern_ids ): string {
			if ( empty( $pattern_ids[ $m[1] ] ) ) {
				WP_CLI::warning( sprintf( 'Unknown pattern placeholder {{pattern:%s}}.', $m[1] ) );
				return $m[0];
			}
			return (string) (int) $pattern_ids[ $m[1] ];
		};
		// "ref":"{{pattern:x}}" must become the number "ref":123 for the block editor.
		$html = (string) preg_replace_callback( '/"\{\{pattern:([A-Za-z0-9_-]+)\}\}"/', $callback, $html );
		return (string) preg_replace_callback( '/\{\{pattern:([A-Za-z0-9_-]+)\}\}/', $callback, $html );
	}

	/** Looks up a per-language site string in the shapes site.json might use. */
	function ak_ic_site_str( array $site, string $field, string $lang, string $default ): string {
		$aliases = array(
			'title'   => array( 'title', 'name', 'site_title', 'blogname' ),
			'tagline' => array( 'tagline', 'description', 'site_tagline', 'blogdescription' ),
		);
		foreach ( $aliases[ $field ] ?? array( $field ) as $name ) {
			$candidates = array(
				$site[ $lang ][ $name ] ?? null,
				$site[ $name ][ $lang ] ?? null,
				$site['site'][ $lang ][ $name ] ?? null,
				$site['site'][ $name ][ $lang ] ?? null,
				$site['languages'][ $lang ][ $name ] ?? null,
			);
			foreach ( $candidates as $value ) {
				if ( is_string( $value ) && '' !== trim( $value ) ) {
					return trim( $value );
				}
			}
		}
		return $default;
	}

	/**
	 * Category definitions keyed by the ET slug used in posts.json:
	 * [ 'uudised' => [ 'et' => [name, slug], 'en' => [name, slug] ], ... ].
	 */
	function ak_ic_categories( array $site ): array {
		$defs = array(
			'uudised'    => array(
				'et' => array( 'name' => 'Uudised', 'slug' => 'uudised' ),
				'en' => array( 'name' => 'News', 'slug' => 'news' ),
			),
			'kuulutused' => array(
				'et' => array( 'name' => 'Kuulutused', 'slug' => 'kuulutused' ),
				'en' => array( 'name' => 'Announcements', 'slug' => 'announcements' ),
			),
		);
		$raw = $site['categories'] ?? array();
		if ( ! is_array( $raw ) ) {
			return $defs;
		}
		foreach ( $raw as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$key = is_string( $index ) ? $index : (string) ( $item['key'] ?? $item['slug'] ?? '' );
			if ( '' === $key ) {
				continue;
			}
			$def = $defs[ $key ] ?? array(
				'et' => array( 'name' => $key, 'slug' => $key ),
				'en' => array( 'name' => $key, 'slug' => $key . '-en' ),
			);
			foreach ( array( 'et', 'en' ) as $lang ) {
				$value = $item[ $lang ] ?? null;
				if ( is_string( $value ) && '' !== $value ) {
					$def[ $lang ]['name'] = $value;
				} elseif ( is_array( $value ) ) {
					if ( ! empty( $value['name'] ) ) {
						$def[ $lang ]['name'] = (string) $value['name'];
					} elseif ( ! empty( $value['title'] ) ) {
						$def[ $lang ]['name'] = (string) $value['title'];
					}
					if ( ! empty( $value['slug'] ) ) {
						$def[ $lang ]['slug'] = (string) $value['slug'];
					}
				}
				if ( ! empty( $item[ $lang . '_slug' ] ) ) {
					$def[ $lang ]['slug'] = (string) $item[ $lang . '_slug' ];
				}
				if ( ! empty( $item[ $lang . '_name' ] ) ) {
					$def[ $lang ]['name'] = (string) $item[ $lang . '_name' ];
				}
			}
			$def['et']['slug'] = sanitize_title( $def['et']['slug'] );
			$def['en']['slug'] = sanitize_title( $def['en']['slug'] );
			$defs[ $key ]      = $def;
		}
		return $defs;
	}

	function ak_ic_term( string $name, string $slug, string $lang ): int {
		$found = get_terms(
			array(
				'taxonomy'   => 'category',
				'slug'       => $slug,
				'hide_empty' => false,
				'number'     => 1,
				'lang'       => '',
			)
		);
		if ( ! is_wp_error( $found ) && ! empty( $found ) ) {
			$term_id = (int) $found[0]->term_id;
		} else {
			$result = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
			if ( is_wp_error( $result ) ) {
				if ( 'term_exists' === $result->get_error_code() && $result->get_error_data() ) {
					$term_id = (int) $result->get_error_data();
				} else {
					WP_CLI::warning( "Category {$slug}: " . $result->get_error_message() );
					return 0;
				}
			} else {
				$term_id = (int) $result['term_id'];
				WP_CLI::log( "  + category {$slug} ({$lang}): created #{$term_id}" );
			}
		}
		if ( ak_ic_has_lang( $lang ) && function_exists( 'pll_set_term_language' ) && pll_get_term_language( $term_id ) !== $lang ) {
			pll_set_term_language( $term_id, $lang );
		}
		return $term_id;
	}

	/** Uploads $path once (re-uploads when the file changed) and returns the attachment ID. */
	function ak_ic_media( string $key, string $path, string $title ): int {
		if ( ! is_readable( $path ) ) {
			WP_CLI::warning( "Brand file missing: {$path}" );
			return 0;
		}
		$hash = md5_file( $path );
		$id   = ak_ic_find_by_key( 'attachment', $key );
		if ( $id && get_post_meta( $id, '_ak_file_hash', true ) === $hash ) {
			$file = get_attached_file( $id );
			if ( $file && file_exists( $file ) ) {
				return $id;
			}
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = wp_tempnam( basename( $path ) );
		if ( ! $tmp || ! copy( $path, $tmp ) ) {
			WP_CLI::warning( "Could not copy {$path} to a temp file." );
			return $id;
		}
		$new_id = media_handle_sideload(
			array(
				'name'     => basename( $path ),
				'tmp_name' => $tmp,
				'error'    => 0,
				'size'     => filesize( $tmp ),
			),
			0,
			$title
		);
		if ( is_wp_error( $new_id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			WP_CLI::warning( "Upload of {$path} failed: " . $new_id->get_error_message() );
			return $id;
		}
		if ( $id ) {
			delete_post_meta( $id, '_ak_key' );
		}
		update_post_meta( $new_id, '_ak_key', $key );
		update_post_meta( $new_id, '_ak_file_hash', $hash );
		update_post_meta( $new_id, '_wp_attachment_image_alt', $title );
		WP_CLI::log( "  + {$key}: uploaded #{$new_id}" );
		return (int) $new_id;
	}

	function ak_ic_set_theme_mod_for( string $stylesheet, string $name, $value ): void {
		if ( get_stylesheet() === $stylesheet ) {
			set_theme_mod( $name, $value );
			return;
		}
		$mods = get_option( "theme_mods_{$stylesheet}", array() );
		if ( ! is_array( $mods ) ) {
			$mods = array();
		}
		$mods[ $name ] = $value;
		update_option( "theme_mods_{$stylesheet}", $mods );
	}

	/** Same approach as configure-polylang.php: 3.7+ Options object or the legacy array. */
	function ak_ic_pll_option( string $key ) {
		if ( ! function_exists( 'PLL' ) || ! is_object( PLL() ) || empty( PLL()->model ) ) {
			return null;
		}
		$options = PLL()->model->options;
		if ( is_array( $options ) || $options instanceof ArrayAccess ) {
			return $options[ $key ] ?? null;
		}
		return null;
	}

	function ak_ic_pll_set_option( string $key, $value ): void {
		if ( ! function_exists( 'PLL' ) || ! is_object( PLL() ) || empty( PLL()->model ) ) {
			return;
		}
		$model = PLL()->model;
		if ( $model->options instanceof ArrayAccess ) {
			$model->options[ $key ] = $value;
			if ( method_exists( $model->options, 'save' ) ) {
				$model->options->save();
			}
		} else {
			$stored = get_option( 'polylang' );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			$stored[ $key ] = $value;
			if ( is_array( $model->options ) ) {
				$model->options[ $key ] = $value;
			}
			update_option( 'polylang', $stored );
		}
		if ( ak_ic_pll_option( $key ) != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons
			WP_CLI::warning( "Polylang option {$key} was not saved as expected." );
		}
	}

	/** Polylang free string translations (site title, tagline) for one language. */
	function ak_ic_pll_strings( string $lang_slug, array $pairs ): void {
		if ( ! class_exists( 'PLL_MO' ) || ! function_exists( 'PLL' ) ) {
			WP_CLI::warning( 'PLL_MO not available; EN site title/tagline not translated.' );
			return;
		}
		// Polylang 3.7+ exposes languages->get(); older versions (and the magic __call proxy) use get_language().
		$model    = PLL()->model;
		$language = ( isset( $model->languages ) && is_object( $model->languages ) && method_exists( $model->languages, 'get' ) )
			? $model->languages->get( $lang_slug )
			: $model->get_language( $lang_slug );
		if ( ! $language ) {
			WP_CLI::warning( "Language {$lang_slug} missing; strings not translated." );
			return;
		}
		$mo = new PLL_MO();
		$mo->import_from_db( $language );
		foreach ( $pairs as $pair ) {
			if ( '' !== $pair[0] && '' !== $pair[1] ) {
				$mo->add_entry( $mo->make_entry( $pair[0], $pair[1] ) );
			}
		}
		$mo->export_to_db( $language );
	}

	function ak_ic_unique_key( string $key, array &$seen ): string {
		if ( ! isset( $seen[ $key ] ) ) {
			$seen[ $key ] = 1;
			return $key;
		}
		++$seen[ $key ];
		return $key . '-' . $seen[ $key ];
	}
}

// ---------------------------------------------------------------------------
// Setup.

$GLOBALS['ak_ic_stats'] = array();

$ak_content = ak_ic_dir( 'CONTENT_DIR', 'content' );
$ak_brand   = ak_ic_dir( 'BRAND_DIR', 'brand' );
if ( ! is_dir( $ak_content ) ) {
	WP_CLI::error( "Content directory not found: {$ak_content} (set CONTENT_DIR)." );
}
WP_CLI::log( "Content: {$ak_content}" );
WP_CLI::log( "Brand:   {$ak_brand}" );

// Run as an administrator: posts get an author and kses does not strip block markup.
$ak_admins = get_users(
	array(
		'role'    => 'administrator',
		'number'  => 1,
		'orderby' => 'ID',
		'fields'  => 'ID',
	)
);
if ( $ak_admins ) {
	wp_set_current_user( (int) $ak_admins[0] );
} else {
	WP_CLI::warning( 'No administrator found; importing without a user (kses may filter markup).' );
}

$ak_has_pll = function_exists( 'pll_set_post_language' ) && ak_ic_has_lang( 'et' ) && ak_ic_has_lang( 'en' );
if ( ! $ak_has_pll ) {
	WP_CLI::warning( 'Polylang languages et/en are not available; importing without languages or translation links.' );
}

$ak_site = ak_ic_read_json( $ak_content, 'site.json', false ) ?? array();
if ( ! $ak_site ) {
	WP_CLI::warning( 'content/site.json missing or empty; using default site title, tagline and category names.' );
}

// ---------------------------------------------------------------------------
// 1. Site title and tagline (EN through Polylang string translations).

$ak_title_et   = ak_ic_site_str( $ak_site, 'title', 'et', 'Valgamaa Äriklubi' );
$ak_title_en   = ak_ic_site_str( $ak_site, 'title', 'en', $ak_title_et );
$ak_tagline_et = ak_ic_site_str( $ak_site, 'tagline', 'et', 'Valgamaa ettevõtjate klubi alates 1994' );
$ak_tagline_en = ak_ic_site_str( $ak_site, 'tagline', 'en', 'Valga County business club since 1994' );

WP_CLI::log( 'Site title and tagline' );
update_option( 'blogname', $ak_title_et );
update_option( 'blogdescription', $ak_tagline_et );
if ( $ak_has_pll ) {
	ak_ic_pll_strings(
		'en',
		array(
			array( $ak_title_et, $ak_title_en ),
			array( $ak_tagline_et, $ak_tagline_en ),
		)
	);
}

// ---------------------------------------------------------------------------
// 2. Synced patterns (wp_block without wp_pattern_sync_status = synced). No language.

WP_CLI::log( 'Patterns' );
$ak_pattern_titles = array( 'history-table' => 'Ajaloo tabel' );
$ak_pattern_ids    = array();
foreach ( glob( $ak_content . '/patterns/*.html' ) ?: array() as $ak_file ) {
	$ak_name = basename( $ak_file, '.html' );
	$ak_body = ak_ic_read_file( $ak_content, 'patterns/' . basename( $ak_file ) );
	if ( null === $ak_body ) {
		continue;
	}
	$ak_id = ak_ic_upsert(
		'pattern:' . $ak_name,
		array(
			'post_type'    => 'wp_block',
			'post_title'   => $ak_pattern_titles[ $ak_name ] ?? ucfirst( str_replace( array( '-', '_' ), ' ', $ak_name ) ),
			'post_name'    => sanitize_title( $ak_name ),
			'post_content' => $ak_body,
		),
		array(),
		array( 'post_status' => 'publish' )
	);
	if ( $ak_id ) {
		delete_post_meta( $ak_id, 'wp_pattern_sync_status' );
		$ak_pattern_ids[ $ak_name ] = $ak_id;
	}
}
if ( empty( $ak_pattern_ids['history-table'] ) ) {
	WP_CLI::warning( 'Synced pattern history-table was not imported (content/patterns/history-table.html).' );
}

// ---------------------------------------------------------------------------
// 3. Pages (ET + EN, linked as translations).

WP_CLI::log( 'Pages' );
$ak_pages    = ak_ic_read_json( $ak_content, 'pages.json' ) ?? array();
$ak_page_ids = array();
$ak_front    = '';
$ak_posts_pg = '';
foreach ( $ak_pages as $ak_index => $ak_page ) {
	if ( ! is_array( $ak_page ) || empty( $ak_page['key'] ) ) {
		WP_CLI::warning( "pages.json entry #{$ak_index} has no key; skipped." );
		continue;
	}
	$ak_pkey = (string) $ak_page['key'];
	foreach ( array( 'et', 'en' ) as $ak_lang ) {
		$ak_d = $ak_page[ $ak_lang ] ?? null;
		if ( ! is_array( $ak_d ) ) {
			continue;
		}
		$ak_body = empty( $ak_d['file'] ) ? '' : ak_ic_read_file( $ak_content, (string) $ak_d['file'] );
		if ( null === $ak_body ) {
			continue;
		}
		$ak_title = (string) ( $ak_d['title'] ?? $ak_pkey );
		$ak_id    = ak_ic_upsert(
			"page:{$ak_pkey}:{$ak_lang}",
			array(
				'post_type'    => 'page',
				'post_title'   => $ak_title,
				'post_name'    => sanitize_title( (string) ( $ak_d['slug'] ?? $ak_title ) ),
				'post_content' => ak_ic_replace_placeholders( $ak_body, $ak_pattern_ids ),
				'menu_order'   => (int) $ak_index,
			),
			array(),
			array(
				'post_status'    => 'publish',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);
		if ( $ak_id ) {
			ak_ic_set_post_lang( $ak_id, $ak_lang );
			$ak_page_ids[ $ak_pkey ][ $ak_lang ] = $ak_id;
		}
	}
	ak_ic_link_posts( $ak_page_ids[ $ak_pkey ] ?? array() );
	if ( ! empty( $ak_page['front_page'] ) ) {
		$ak_front = $ak_pkey;
	}
	if ( ! empty( $ak_page['posts_page'] ) ) {
		$ak_posts_pg = $ak_pkey;
	}
}
$ak_front    = $ak_front ?: 'home';
$ak_posts_pg = $ak_posts_pg ?: 'news';

// Reading settings use the ET pages; Polylang maps EN through the translation links.
if ( ! empty( $ak_page_ids[ $ak_front ]['et'] ) ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $ak_page_ids[ $ak_front ]['et'] );
} else {
	WP_CLI::warning( "Front page '{$ak_front}' (et) not found; static front page not set." );
}
if ( ! empty( $ak_page_ids[ $ak_posts_pg ]['et'] ) ) {
	update_option( 'page_for_posts', $ak_page_ids[ $ak_posts_pg ]['et'] );
} else {
	WP_CLI::warning( "Posts page '{$ak_posts_pg}' (et) not found; posts page not set." );
}
if ( ! empty( $ak_page_ids['privacy']['et'] ) ) {
	update_option( 'wp_page_for_privacy_policy', $ak_page_ids['privacy']['et'] );
}

// ---------------------------------------------------------------------------
// 4. Categories (ET + EN, linked).

WP_CLI::log( 'Categories' );
$ak_cat_ids = array();
foreach ( ak_ic_categories( $ak_site ) as $ak_ckey => $ak_def ) {
	$ak_ids = array();
	foreach ( array( 'et', 'en' ) as $ak_lang ) {
		$ak_tid = ak_ic_term( $ak_def[ $ak_lang ]['name'], $ak_def[ $ak_lang ]['slug'], $ak_lang );
		if ( $ak_tid ) {
			$ak_ids[ $ak_lang ] = $ak_tid;
		}
	}
	if ( $ak_has_pll && count( $ak_ids ) > 1 && function_exists( 'pll_save_term_translations' ) ) {
		pll_save_term_translations( $ak_ids );
	}
	$ak_cat_ids[ $ak_ckey ] = $ak_ids;
}

// ---------------------------------------------------------------------------
// 5. Posts (news and announcements).

WP_CLI::log( 'Posts' );
$ak_posts = ak_ic_read_json( $ak_content, 'posts.json' ) ?? array();
foreach ( $ak_posts as $ak_index => $ak_item ) {
	if ( ! is_array( $ak_item ) || empty( $ak_item['key'] ) ) {
		WP_CLI::warning( "posts.json entry #{$ak_index} has no key; skipped." );
		continue;
	}
	$ak_pkey  = (string) $ak_item['key'];
	$ak_date  = ak_ic_date( $ak_item['date'] ?? '' );
	$ak_cat   = (string) ( $ak_item['category'] ?? '' );
	$ak_dates = '' === $ak_date ? array() : array(
		'post_date'     => $ak_date,
		'post_date_gmt' => get_gmt_from_date( $ak_date ),
	);
	if ( '' !== $ak_cat && empty( $ak_cat_ids[ $ak_cat ] ) ) {
		WP_CLI::warning( "Post {$ak_pkey}: unknown category '{$ak_cat}'." );
	}
	$ak_ids = array();
	foreach ( array( 'et', 'en' ) as $ak_lang ) {
		$ak_d = $ak_item[ $ak_lang ] ?? null;
		if ( ! is_array( $ak_d ) ) {
			continue;
		}
		$ak_body = empty( $ak_d['file'] ) ? '' : ak_ic_read_file( $ak_content, (string) $ak_d['file'] );
		if ( null === $ak_body ) {
			continue;
		}
		$ak_title = (string) ( $ak_d['title'] ?? $ak_pkey );
		$ak_id    = ak_ic_upsert(
			"post:{$ak_pkey}:{$ak_lang}",
			array_merge(
				array(
					'post_type'    => 'post',
					'post_title'   => $ak_title,
					'post_name'    => sanitize_title( (string) ( $ak_d['slug'] ?? $ak_title ) ),
					'post_content' => $ak_body,
				),
				$ak_dates
			),
			array(),
			array(
				'post_status'    => 'publish',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			)
		);
		if ( ! $ak_id ) {
			continue;
		}
		ak_ic_set_post_lang( $ak_id, $ak_lang );
		if ( ! empty( $ak_cat_ids[ $ak_cat ][ $ak_lang ] ) ) {
			wp_set_post_categories( $ak_id, array( $ak_cat_ids[ $ak_cat ][ $ak_lang ] ), false );
		}
		$ak_ids[ $ak_lang ] = $ak_id;
	}
	ak_ic_link_posts( $ak_ids );
}

// ---------------------------------------------------------------------------
// 6. Members (language-neutral).

WP_CLI::log( 'Members' );
if ( ! post_type_exists( 'ak_member' ) ) {
	WP_CLI::warning( 'Post type ak_member is not registered (mu-plugin missing?); importing anyway.' );
}
$ak_members = ak_ic_read_json( $ak_content, 'members.json' ) ?? array();
$ak_seen    = array();
foreach ( $ak_members as $ak_index => $ak_m ) {
	$ak_name = is_array( $ak_m ) ? trim( (string) ( $ak_m['name'] ?? '' ) ) : '';
	if ( '' === $ak_name ) {
		WP_CLI::warning( "members.json entry #{$ak_index} has no name; skipped." );
		continue;
	}
	$ak_key = ak_ic_unique_key( 'member:' . sanitize_title( (string) ( $ak_m['key'] ?? $ak_name ) ), $ak_seen );
	ak_ic_upsert(
		$ak_key,
		array(
			'post_type'    => 'ak_member',
			'post_title'   => $ak_name,
			'post_content' => '',
			'menu_order'   => (int) $ak_index,
		),
		array(
			'_ak_company' => trim( (string) ( $ak_m['company'] ?? '' ) ),
			'_ak_website' => esc_url_raw( trim( (string) ( $ak_m['website'] ?? '' ) ) ),
		),
		array( 'post_status' => 'publish' )
	);
}

// ---------------------------------------------------------------------------
// 7. Album drafts (language-neutral, no images).

WP_CLI::log( 'Albums' );
if ( ! post_type_exists( 'ak_album' ) ) {
	WP_CLI::warning( 'Post type ak_album is not registered (mu-plugin missing?); importing anyway.' );
}
$ak_albums = ak_ic_read_json( $ak_content, 'albums.json' ) ?? array();
$ak_seen   = array();
foreach ( $ak_albums as $ak_index => $ak_a ) {
	$ak_title = is_array( $ak_a ) ? trim( (string) ( $ak_a['title'] ?? '' ) ) : '';
	if ( '' === $ak_title ) {
		WP_CLI::warning( "albums.json entry #{$ak_index} has no title; skipped." );
		continue;
	}
	$ak_legacy = isset( $ak_a['legacy_id'] ) && '' !== (string) $ak_a['legacy_id'] ? (string) $ak_a['legacy_id'] : '';
	$ak_key    = ak_ic_unique_key( 'album:' . ( '' !== $ak_legacy ? $ak_legacy : sanitize_title( $ak_title ) ), $ak_seen );
	$ak_date   = ak_ic_date( $ak_a['date'] ?? '' );
	$ak_create = array( 'post_status' => 'draft' );
	if ( '' !== $ak_date ) {
		// An explicit GMT date keeps the date when the draft is published later.
		$ak_create['post_date']     = $ak_date;
		$ak_create['post_date_gmt'] = get_gmt_from_date( $ak_date );
	}
	ak_ic_upsert(
		$ak_key,
		array(
			'post_type'    => 'ak_album',
			'post_title'   => $ak_title,
			'post_content' => '',
		),
		'' !== $ak_legacy ? array( '_ak_legacy_id' => $ak_legacy ) : array(),
		$ak_create
	);
}

// ---------------------------------------------------------------------------
// 8. Menus per language with the Polylang language switcher.

WP_CLI::log( 'Menus' );
$ak_menu_names = array(
	'et' => 'Peamenüü (ET)',
	'en' => 'Main menu (EN)',
);
$ak_menu_pages = array( 'home', 'members', 'news', 'history', 'gallery' );
$ak_menu_ids   = array();
foreach ( $ak_menu_names as $ak_lang => $ak_menu_name ) {
	$ak_menu    = wp_get_nav_menu_object( $ak_menu_name );
	$ak_menu_id = $ak_menu ? (int) $ak_menu->term_id : wp_create_nav_menu( $ak_menu_name );
	if ( is_wp_error( $ak_menu_id ) ) {
		WP_CLI::warning( "Menu {$ak_menu_name}: " . $ak_menu_id->get_error_message() );
		continue;
	}
	$ak_menu_ids[ $ak_lang ] = (int) $ak_menu_id;

	// Items carry _ak_key; existing ones are left as the admin arranged them.
	$ak_existing = array();
	foreach ( (array) wp_get_nav_menu_items( $ak_menu_id, array( 'post_status' => 'any' ) ) as $ak_item ) {
		$ak_item_key = get_post_meta( $ak_item->ID, '_ak_key', true );
		if ( $ak_item_key ) {
			$ak_existing[ $ak_item_key ] = (int) $ak_item->ID;
		}
	}

	$ak_pos = 0;
	foreach ( $ak_menu_pages as $ak_pkey ) {
		if ( empty( $ak_page_ids[ $ak_pkey ][ $ak_lang ] ) ) {
			continue;
		}
		++$ak_pos;
		$ak_item_key = "menu:{$ak_lang}:{$ak_pkey}";
		if ( isset( $ak_existing[ $ak_item_key ] ) ) {
			continue;
		}
		$ak_item_id = wp_update_nav_menu_item(
			$ak_menu_id,
			0,
			array(
				'menu-item-object-id' => $ak_page_ids[ $ak_pkey ][ $ak_lang ],
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $ak_pos,
			)
		);
		if ( is_wp_error( $ak_item_id ) ) {
			WP_CLI::warning( "{$ak_item_key}: " . $ak_item_id->get_error_message() );
			continue;
		}
		update_post_meta( $ak_item_id, '_ak_key', $ak_item_key );
		WP_CLI::log( "  + {$ak_item_key}: #{$ak_item_id}" );
	}

	++$ak_pos;
	$ak_item_key = "menu:{$ak_lang}:switcher";
	$ak_item_id  = $ak_existing[ $ak_item_key ] ?? 0;
	if ( ! $ak_item_id ) {
		$ak_item_id = wp_update_nav_menu_item(
			$ak_menu_id,
			0,
			array(
				'menu-item-type'     => 'custom',
				'menu-item-url'      => '#pll_switcher',
				'menu-item-title'    => 'et' === $ak_lang ? 'Keelevahetaja' : 'Language switcher',
				'menu-item-status'   => 'publish',
				'menu-item-position' => $ak_pos,
			)
		);
		if ( is_wp_error( $ak_item_id ) ) {
			WP_CLI::warning( "{$ak_item_key}: " . $ak_item_id->get_error_message() );
			$ak_item_id = 0;
		} else {
			update_post_meta( $ak_item_id, '_ak_key', $ak_item_key );
			WP_CLI::log( "  + {$ak_item_key}: #{$ak_item_id}" );
		}
	}
	if ( $ak_item_id ) {
		update_post_meta(
			$ak_item_id,
			'_pll_menu_item',
			array(
				'hide_if_no_translation' => 0,
				'hide_current'           => 1,
				'force_home'             => 0,
				'show_flags'             => 0,
				'show_names'             => 1,
				'dropdown'               => 0,
			)
		);
	}
}

if ( ! empty( $ak_menu_ids['et'] ) ) {
	$ak_locations = get_theme_mod( 'nav_menu_locations' );
	if ( get_stylesheet() !== 'ariklubi' ) {
		$ak_mods      = get_option( 'theme_mods_ariklubi', array() );
		$ak_locations = is_array( $ak_mods ) ? ( $ak_mods['nav_menu_locations'] ?? array() ) : array();
	}
	if ( ! is_array( $ak_locations ) ) {
		$ak_locations = array();
	}
	$ak_locations['primary'] = $ak_menu_ids['et'];
	ak_ic_set_theme_mod_for( 'ariklubi', 'nav_menu_locations', $ak_locations );
}
if ( $ak_has_pll && $ak_menu_ids ) {
	$ak_nav = ak_ic_pll_option( 'nav_menus' );
	if ( ! is_array( $ak_nav ) ) {
		$ak_nav = array();
	}
	foreach ( $ak_menu_ids as $ak_lang => $ak_menu_id ) {
		$ak_nav['ariklubi']['primary'][ $ak_lang ] = $ak_menu_id;
	}
	ak_ic_pll_set_option( 'nav_menus', $ak_nav );
}

// ---------------------------------------------------------------------------
// 9. Footer widgets: block widgets per language in footer-1 (contact) and footer-2 (about).

WP_CLI::log( 'Footer widgets' );
$ak_widget_defs = array(
	array( 'footer-1', 'footer-contact', 'et' ),
	array( 'footer-1', 'footer-contact', 'en' ),
	array( 'footer-2', 'footer-about', 'et' ),
	array( 'footer-2', 'footer-about', 'en' ),
);
$ak_instances = get_option( 'widget_block', array() );
if ( ! is_array( $ak_instances ) ) {
	$ak_instances = array();
}
$ak_widget_map = get_option( 'ak_widget_map', array() );
if ( ! is_array( $ak_widget_map ) ) {
	$ak_widget_map = array();
}
$ak_sidebar_ids = array(
	'footer-1' => array(),
	'footer-2' => array(),
);
foreach ( $ak_widget_defs as $ak_def ) {
	list( $ak_sidebar, $ak_base, $ak_lang ) = $ak_def;
	$ak_slot         = "{$ak_base}.{$ak_lang}";
	$ak_content_html = ak_ic_read_file( $ak_content, "widgets/{$ak_slot}.html" );
	if ( null === $ak_content_html ) {
		continue;
	}
	$ak_num = (int) ( $ak_widget_map[ $ak_slot ]['n'] ?? 0 );
	if ( $ak_num && isset( $ak_instances[ $ak_num ] ) && is_array( $ak_instances[ $ak_num ] ) ) {
		$ak_current = (string) ( $ak_instances[ $ak_num ]['content'] ?? '' );
		$ak_edited  = md5( $ak_current ) !== ( $ak_widget_map[ $ak_slot ]['h'] ?? '' );
		if ( $ak_current !== $ak_content_html ) {
			if ( ! $ak_edited || ak_ic_flag( 'AK_FORCE_UPDATE' ) ) {
				$ak_instances[ $ak_num ]['content'] = $ak_content_html;
				WP_CLI::log( "  + widget {$ak_slot}: updated block-{$ak_num}" );
			} else {
				WP_CLI::warning( "Widget {$ak_slot} (block-{$ak_num}) was edited in wp-admin; not overwritten (AK_FORCE_UPDATE=1 to overwrite)." );
			}
		}
		$ak_instances[ $ak_num ]['pll_lang'] = $ak_lang;
	} else {
		$ak_numbers = array_filter( array_keys( $ak_instances ), 'is_int' );
		$ak_num     = max( array_merge( array( 1 ), $ak_numbers ) ) + 1;
		$ak_instances[ $ak_num ] = array(
			'content'  => $ak_content_html,
			'pll_lang' => $ak_lang,
		);
		WP_CLI::log( "  + widget {$ak_slot}: created block-{$ak_num}" );
	}
	$ak_widget_map[ $ak_slot ]       = array(
		'n' => $ak_num,
		'h' => md5( (string) $ak_instances[ $ak_num ]['content'] ),
	);
	$ak_sidebar_ids[ $ak_sidebar ][] = 'block-' . $ak_num;
}
$ak_instances['_multiwidget'] = 1;
update_option( 'widget_block', $ak_instances );
update_option( 'ak_widget_map', $ak_widget_map, false );

$ak_sidebars = get_option( 'sidebars_widgets', array() );
if ( ! is_array( $ak_sidebars ) ) {
	$ak_sidebars = array();
}
$ak_ours = array_merge( $ak_sidebar_ids['footer-1'], $ak_sidebar_ids['footer-2'] );
foreach ( $ak_sidebars as $ak_sb => $ak_list ) {
	if ( is_array( $ak_list ) ) {
		$ak_sidebars[ $ak_sb ] = array_values( array_diff( $ak_list, $ak_ours ) );
	}
}
$ak_inactive = isset( $ak_sidebars['wp_inactive_widgets'] ) && is_array( $ak_sidebars['wp_inactive_widgets'] ) ? $ak_sidebars['wp_inactive_widgets'] : array();
foreach ( $ak_sidebar_ids as $ak_sb => $ak_list ) {
	// Anything else that was in our footer areas moves to "Inactive widgets" instead of being lost.
	if ( ! empty( $ak_sidebars[ $ak_sb ] ) && is_array( $ak_sidebars[ $ak_sb ] ) ) {
		$ak_inactive = array_merge( $ak_inactive, $ak_sidebars[ $ak_sb ] );
	}
	$ak_sidebars[ $ak_sb ] = $ak_list;
}
$ak_sidebars['wp_inactive_widgets'] = array_values( array_unique( $ak_inactive ) );
if ( ! isset( $ak_sidebars['array_version'] ) ) {
	$ak_sidebars['array_version'] = 3;
}
update_option( 'sidebars_widgets', $ak_sidebars );

// The two footer columns come from the ariklubi theme's GeneratePress defaults. Never write
// generate_settings here: GeneratePress treats a pre-existing option as a legacy site and
// freezes its old grey defaults into it, overriding the brand palette.

// ---------------------------------------------------------------------------
// 10. Brand: custom logo (ariklubi theme), site icon, social image.

WP_CLI::log( 'Brand assets' );
if ( is_dir( $ak_brand ) ) {
	$ak_logo = ak_ic_media( 'brand:logo-horizontal-on-dark', $ak_brand . '/logo-horizontal-on-dark.png', $ak_title_et );
	if ( $ak_logo ) {
		ak_ic_set_theme_mod_for( 'ariklubi', 'custom_logo', $ak_logo );
	}
	$ak_icon = ak_ic_media( 'brand:site-icon-512', $ak_brand . '/site-icon-512.png', $ak_title_et );
	if ( $ak_icon ) {
		update_option( 'site_icon', $ak_icon );
	}
	$ak_og = ak_ic_media( 'brand:og-image-1200x630', $ak_brand . '/og-image-1200x630.png', $ak_title_et );
	if ( $ak_og ) {
		update_option( 'ak_og_image_id', $ak_og, false );
	}
} else {
	WP_CLI::warning( "Brand directory not found ({$ak_brand}); logo and site icon not set." );
}

// ---------------------------------------------------------------------------
// 11. Polylang caches (per-language front/posts pages are derived from translations).

if ( function_exists( 'PLL' ) && is_object( PLL() ) && ! empty( PLL()->model ) ) {
	if ( method_exists( PLL()->model, 'clean_languages_cache' ) ) {
		PLL()->model->clean_languages_cache();
	}
	if ( isset( PLL()->model->languages ) && is_object( PLL()->model->languages ) && method_exists( PLL()->model->languages, 'clean_cache' ) ) {
		PLL()->model->languages->clean_cache();
	}
}
delete_transient( 'pll_languages_list' );

// ---------------------------------------------------------------------------
// Summary.

WP_CLI::log( '' );
WP_CLI::log( 'Pages:' );
$ak_rows = array();
foreach ( $ak_page_ids as $ak_pkey => $ak_by_lang ) {
	foreach ( $ak_by_lang as $ak_lang => $ak_id ) {
		$ak_rows[] = array(
			'key'  => $ak_pkey,
			'lang' => $ak_lang,
			'ID'   => $ak_id,
			'url'  => (string) get_permalink( $ak_id ),
		);
	}
}
if ( $ak_rows ) {
	WP_CLI\Utils\format_items( 'table', $ak_rows, array( 'key', 'lang', 'ID', 'url' ) );
}
WP_CLI::log( sprintf( 'Front page #%s, posts page #%s, privacy page #%s', get_option( 'page_on_front' ), get_option( 'page_for_posts' ), get_option( 'wp_page_for_privacy_policy' ) ) );
WP_CLI::log( 'Menus: ' . wp_json_encode( $ak_menu_ids ) . '; patterns: ' . wp_json_encode( $ak_pattern_ids ) );
$ak_stats = array();
foreach ( $GLOBALS['ak_ic_stats'] as $ak_what => $ak_n ) {
	$ak_stats[] = "{$ak_what}: {$ak_n}";
}
WP_CLI::success( 'Import finished (' . ( $ak_stats ? implode( ', ', $ak_stats ) : 'nothing to do' ) . ').' );
