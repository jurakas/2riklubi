<?php
/**
 * Polylang configuration for the Valgamaa Äriklubi site. Idempotent.
 *
 * Run with: wp eval-file scripts/configure-polylang.php (scripts/setup.sh does this).
 *
 * - Languages: Eesti (slug et, locale et, flag ee, default) and English (slug en, locale en_US, flag gb).
 * - URLs: language taken from the directory, default language hidden, no /language/ base.
 * - No custom post types or taxonomies are translatable (ak_member, ak_album and wp_block are
 *   also removed from Polylang by the ariklubi-core mu-plugin via pll_get_post_types).
 * - Posts, pages and terms without a language are assigned to Estonian.
 *
 * Works with Polylang 3.4+ (array options, PLL_Admin_Model) and 3.7+ (Options object,
 * $model->languages API).
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_CLI' ) ) {
	echo "Run with: wp eval-file configure-polylang.php\n";
	return;
}

if ( ! function_exists( 'ak_cp_languages_api' ) ) {

	/** Polylang 3.7+ language manager, or null on older versions. */
	function ak_cp_languages_api() {
		$model = PLL()->model;
		if ( isset( $model->languages ) && is_object( $model->languages ) ) {
			return $model->languages;
		}
		return null;
	}

	/** @return object|false PLL_Language */
	function ak_cp_get_language( string $slug ) {
		$model = PLL()->model;
		if ( method_exists( $model, 'get_language' ) ) {
			return $model->get_language( $slug );
		}
		$api = ak_cp_languages_api();
		if ( $api && method_exists( $api, 'get' ) ) {
			return $api->get( $slug );
		}
		return false;
	}

	function ak_cp_clean_cache(): void {
		$model = PLL()->model;
		if ( method_exists( $model, 'clean_languages_cache' ) ) {
			$model->clean_languages_cache();
		}
		$api = ak_cp_languages_api();
		if ( $api && method_exists( $api, 'clean_cache' ) ) {
			$api->clean_cache();
		}
		delete_transient( 'pll_languages_list' );
	}

	/** A model that can manage languages (PLL_Model is read-only before 3.7 outside the settings page). */
	function ak_cp_admin_model() {
		$model = PLL()->model;
		if ( method_exists( $model, 'add_language' ) ) {
			return $model;
		}
		if ( class_exists( 'PLL_Admin_Model' ) ) {
			return new PLL_Admin_Model( $model->options );
		}
		return null;
	}

	/** @return true|WP_Error */
	function ak_cp_add_language( array $args ) {
		$api = ak_cp_languages_api();
		if ( $api && method_exists( $api, 'add' ) ) {
			return $api->add( $args );
		}
		$admin_model = ak_cp_admin_model();
		if ( $admin_model && method_exists( $admin_model, 'add_language' ) ) {
			return $admin_model->add_language( $args );
		}
		return new WP_Error( 'ak_no_api', 'No Polylang API found to add languages (tried $model->languages->add(), add_language(), PLL_Admin_Model).' );
	}

	function ak_cp_set_default_language( string $slug ): void {
		$current = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
		if ( $current === $slug ) {
			return;
		}
		$api = ak_cp_languages_api();
		if ( $api && method_exists( $api, 'update_default' ) ) {
			$result = $api->update_default( $slug );
		} else {
			$admin_model = ak_cp_admin_model();
			if ( $admin_model && method_exists( $admin_model, 'update_default_lang' ) ) {
				$result = $admin_model->update_default_lang( $slug );
			} else {
				ak_cp_set_options( array( 'default_lang' => $slug ) );
				$result = true;
			}
		}
		if ( is_wp_error( $result ) ) {
			WP_CLI::warning( 'Could not set default language: ' . $result->get_error_message() );
		}
		ak_cp_clean_cache();
	}

	/**
	 * Writes Polylang options. 3.7+ stores them in an ArrayAccess Options object that is
	 * saved on shutdown, so writing the raw option there would be overwritten; older
	 * versions keep an array on the model that later model calls write back.
	 */
	function ak_cp_set_options( array $changes ): void {
		$model = PLL()->model;
		if ( isset( $model->options ) && $model->options instanceof ArrayAccess ) {
			foreach ( $changes as $key => $value ) {
				$model->options[ $key ] = $value;
			}
			if ( method_exists( $model->options, 'save' ) ) {
				$model->options->save();
			}
		} else {
			$stored = get_option( 'polylang' );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			foreach ( $changes as $key => $value ) {
				$stored[ $key ] = $value;
				if ( isset( $model->options ) && is_array( $model->options ) ) {
					$model->options[ $key ] = $value;
				}
			}
			update_option( 'polylang', $stored );
		}
		foreach ( $changes as $key => $value ) {
			$actual = ak_cp_get_option( $key );
			if ( $actual != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons -- 1 vs true is fine.
				WP_CLI::warning( sprintf( 'Polylang option "%s" did not take the value %s (now %s).', $key, wp_json_encode( $value ), wp_json_encode( $actual ) ) );
			}
		}
	}

	function ak_cp_get_option( string $key ) {
		$options = PLL()->model->options;
		if ( is_array( $options ) || $options instanceof ArrayAccess ) {
			return $options[ $key ] ?? null;
		}
		return null;
	}
}

if ( ! function_exists( 'PLL' ) || ! is_object( PLL() ) || empty( PLL()->model ) ) {
	WP_CLI::error( 'Polylang is not active or not initialised (PLL() is unavailable). Activate the polylang plugin first.' );
}
if ( defined( 'POLYLANG_VERSION' ) ) {
	WP_CLI::log( 'Polylang ' . POLYLANG_VERSION );
}

$ak_languages = array(
	array(
		'name'       => 'Eesti',
		'slug'       => 'et',
		'locale'     => 'et',
		'rtl'        => false,
		'term_group' => 0,
		'flag'       => 'ee',
	),
	array(
		'name'       => 'English',
		'slug'       => 'en',
		'locale'     => 'en_US',
		'rtl'        => false,
		'term_group' => 1,
		'flag'       => 'gb',
	),
);

// 1. Languages.
foreach ( $ak_languages as $ak_lang ) {
	if ( ak_cp_get_language( $ak_lang['slug'] ) ) {
		WP_CLI::log( "Language {$ak_lang['slug']} exists" );
		continue;
	}
	$ak_result = ak_cp_add_language( $ak_lang );
	ak_cp_clean_cache();
	if ( ak_cp_get_language( $ak_lang['slug'] ) ) {
		WP_CLI::log( "Language {$ak_lang['slug']} added" );
		if ( is_wp_error( $ak_result ) ) {
			// For example the language pack download failed; the language itself exists.
			WP_CLI::warning( "While adding {$ak_lang['slug']}: " . $ak_result->get_error_message() );
		}
	} else {
		$ak_message = is_wp_error( $ak_result ) ? $ak_result->get_error_message() : 'unknown error';
		WP_CLI::error( "Could not add language {$ak_lang['slug']}: {$ak_message}" );
	}
}

// 2. Default language.
ak_cp_set_default_language( 'et' );

// 3. URL mode and translatable types.
ak_cp_set_options(
	array(
		'force_lang'    => 1,     // Language set from the directory name.
		'hide_default'  => true,  // No /et/ prefix.
		'rewrite'       => true,  // /en/ instead of /language/en/.
		'redirect_lang' => true,  // Translated front pages live at /en/, not /en/home/.
		'browser'       => false, // No browser-language redirect; ET is always at /.
		'media_support' => false,
		'post_types'    => array(),
		'taxonomies'    => array(),
	)
);

// 4. Content without a language becomes Estonian.
if ( function_exists( 'pll_set_post_language' ) && function_exists( 'pll_get_post_language' ) ) {
	$ak_post_ids = get_posts(
		array(
			'post_type'        => array( 'post', 'page' ),
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'lang'             => '',
			'suppress_filters' => true,
		)
	);
	$ak_assigned = 0;
	foreach ( $ak_post_ids as $ak_id ) {
		if ( ! pll_get_post_language( $ak_id ) ) {
			pll_set_post_language( $ak_id, 'et' );
			++$ak_assigned;
		}
	}
	WP_CLI::log( "Assigned {$ak_assigned} post(s)/page(s) to et" );
}
if ( function_exists( 'pll_set_term_language' ) && function_exists( 'pll_get_term_language' ) ) {
	$ak_term_ids = get_terms(
		array(
			'taxonomy'   => array( 'category', 'post_tag' ),
			'hide_empty' => false,
			'fields'     => 'ids',
			'lang'       => '',
		)
	);
	$ak_assigned = 0;
	if ( ! is_wp_error( $ak_term_ids ) ) {
		foreach ( $ak_term_ids as $ak_id ) {
			if ( ! pll_get_term_language( $ak_id ) ) {
				pll_set_term_language( $ak_id, 'et' );
				++$ak_assigned;
			}
		}
	}
	WP_CLI::log( "Assigned {$ak_assigned} term(s) to et" );
}

// 5. No setup wizard redirect or admin nags.
delete_transient( 'pll_activation_redirect' );
$ak_dismissed = get_option( 'pll_dismissed_notices', array() );
if ( ! is_array( $ak_dismissed ) ) {
	$ak_dismissed = array();
}
update_option( 'pll_dismissed_notices', array_values( array_unique( array_merge( $ak_dismissed, array( 'wizard', 'review' ) ) ) ) );

// 6. Rules are rebuilt by `wp rewrite flush` in a fresh process, where Polylang reads the new options.
ak_cp_clean_cache();
delete_option( 'rewrite_rules' );

WP_CLI::success(
	sprintf(
		'Polylang configured: languages [%s], default %s, force_lang %s, hide_default %s.',
		function_exists( 'pll_languages_list' ) ? implode( ', ', pll_languages_list() ) : '?',
		function_exists( 'pll_default_language' ) ? pll_default_language() : '?',
		wp_json_encode( ak_cp_get_option( 'force_lang' ) ),
		wp_json_encode( ak_cp_get_option( 'hide_default' ) )
	)
);
