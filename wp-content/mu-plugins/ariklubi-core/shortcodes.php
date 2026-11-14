<?php
/**
 * Shortcodes: [ak_albums], [ak_latest_news], [ak_members], [ak_member_count].
 *
 * @package ariklubi-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds a space after line breaks and block-level closing tags so stripped text keeps word boundaries.
 */
function ak_excerpt_spacing( string $html ): string {
	return (string) preg_replace( '#(<br\s*/?>|</(p|li|h[1-6]|div|td|th|figcaption|blockquote)>)#i', '$1 ', $html );
}

// Core's auto-excerpt strips tags without separators; space them only while an excerpt is built.
add_filter(
	'get_the_excerpt',
	static function ( $text ) {
		add_filter( 'the_content', 'ak_excerpt_spacing', 999 );
		return $text;
	},
	1
);
add_filter(
	'get_the_excerpt',
	static function ( $text ) {
		remove_filter( 'the_content', 'ak_excerpt_spacing', 999 );
		return $text;
	},
	99
);

/**
 * Plain-text excerpt of a post: the manual excerpt, else trimmed content.
 *
 * @param WP_Post $post  Post.
 * @param int     $words Word limit.
 */
function ak_plain_excerpt( WP_Post $post, int $words = 22 ): string {
	$text = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
	$text = strip_shortcodes( $text );
	if ( function_exists( 'excerpt_remove_blocks' ) && has_blocks( $text ) ) {
		$text = excerpt_remove_blocks( $text );
	}
	$text = wp_strip_all_tags( ak_excerpt_spacing( $text ), true );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return wp_trim_words( $text, $words, '…' );
}

/**
 * [ak_albums count="12" pagination="yes"]
 *
 * @param array|string $atts Attributes.
 */
function ak_shortcode_albums( $atts ): string {
	$atts     = shortcode_atts(
		array(
			'count'      => 12,
			'pagination' => 'yes',
		),
		$atts,
		'ak_albums'
	);
	$count    = max( 1, min( 100, absint( $atts['count'] ) ) );
	$paginate = ! in_array( strtolower( (string) $atts['pagination'] ), array( 'no', '0', 'false', 'off' ), true );
	$paged    = $paginate ? max( 1, absint( get_query_var( 'paged' ) ), absint( get_query_var( 'page' ) ) ) : 1;

	$query = new WP_Query(
		array(
			'post_type'           => 'ak_album',
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'paged'               => $paged,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => ! $paginate,
		)
	);

	if ( ! $query->have_posts() ) {
		return '<p class="ak-albums__empty">' . esc_html( ak_t( 'albums_empty' ) ) . '</p>';
	}

	$out = '<div class="ak-albums">';
	foreach ( $query->posts as $album ) {
		$cover = ak_album_cover_id( $album );
		$img   = $cover ? wp_get_attachment_image(
			$cover,
			'medium_large',
			false,
			array(
				'class'   => 'ak-album-card__img',
				'loading' => 'lazy',
				'alt'     => '',
			)
		) : '';
		if ( '' === $img ) {
			$img = '<span class="ak-album-card__img ak-album-card__img--placeholder" aria-hidden="true"></span>';
		}

		$out .= sprintf(
			'<a class="ak-album-card" href="%1$s">%2$s<span class="ak-album-card__title">%3$s</span><time class="ak-album-card__date" datetime="%4$s">%5$s</time></a>',
			esc_url( get_permalink( $album ) ),
			$img,
			esc_html( get_the_title( $album ) ),
			esc_attr( (string) get_post_time( 'c', false, $album ) ),
			esc_html( date_i18n( get_option( 'date_format' ), strtotime( $album->post_date ) ) )
		);
	}
	$out .= '</div>';

	if ( $paginate && $query->max_num_pages > 1 ) {
		$big   = 999999999;
		$links = paginate_links(
			array(
				'base'      => str_replace( (string) $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
				'format'    => '?paged=%#%',
				'current'   => $paged,
				'total'     => (int) $query->max_num_pages,
				'prev_text' => esc_html( ak_t( 'prev' ) ),
				'next_text' => esc_html( ak_t( 'next' ) ),
			)
		);
		if ( $links ) {
			$out .= '<nav class="ak-pagination" aria-label="' . esc_attr( ak_t( 'albums_nav' ) ) . '">' . wp_kses_post( $links ) . '</nav>';
		}
	}

	return $out;
}
add_shortcode( 'ak_albums', 'ak_shortcode_albums' );

/**
 * [ak_latest_news count="3"]
 *
 * @param array|string $atts Attributes.
 */
function ak_shortcode_latest_news( $atts ): string {
	$atts  = shortcode_atts( array( 'count' => 3 ), $atts, 'ak_latest_news' );
	$count = max( 1, min( 50, absint( $atts['count'] ) ) );

	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'orderby'             => 'date',
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language( 'slug' );
		if ( $lang ) {
			$args['lang'] = $lang;
		}
	}
	$query = new WP_Query( $args );

	$out = '';
	if ( ! $query->have_posts() ) {
		$out .= '<p class="ak-news-list__empty">' . esc_html( ak_t( 'news_empty' ) ) . '</p>';
	} else {
		$out .= '<div class="ak-news-list">';
		foreach ( $query->posts as $item ) {
			$out .= sprintf(
				'<article class="ak-news-item"><time class="ak-news-item__date" datetime="%1$s">%2$s</time><h3 class="ak-news-item__title"><a href="%3$s">%4$s</a></h3><div class="ak-news-item__excerpt">%5$s</div></article>',
				esc_attr( (string) get_post_time( 'c', false, $item ) ),
				esc_html( date_i18n( get_option( 'date_format' ), strtotime( $item->post_date ) ) ),
				esc_url( get_permalink( $item ) ),
				esc_html( get_the_title( $item ) ),
				esc_html( ak_plain_excerpt( $item ) )
			);
		}
		$out .= '</div>';
	}

	$more = ak_posts_page_url();
	if ( '' !== $more ) {
		$out .= '<p class="ak-news-list__more"><a href="' . esc_url( $more ) . '">' . esc_html( ak_t( 'news_all' ) ) . '</a></p>';
	}

	return $out;
}
add_shortcode( 'ak_latest_news', 'ak_shortcode_latest_news' );

/**
 * Locale-independent sort key following the Estonian alphabet
 * (… s š z ž t u v w õ ä ö ü x y), used when ext-intl is unavailable.
 */
function ak_et_sort_key( string $text ): string {
	static $alphabet = null;
	if ( null === $alphabet ) {
		$letters  = array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 'š', 'z', 'ž', 't', 'u', 'v', 'w', 'õ', 'ä', 'ö', 'ü', 'x', 'y' );
		$alphabet = array();
		foreach ( $letters as $i => $letter ) {
			$alphabet[ $letter ] = chr( 65 + $i );
		}
	}

	$text  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $chars ) ) {
		return $text;
	}

	$key = '';
	foreach ( $chars as $char ) {
		if ( isset( $alphabet[ $char ] ) ) {
			$key .= $alphabet[ $char ];
			continue;
		}
		$plain = strtolower( remove_accents( $char ) );
		$key  .= $alphabet[ $plain ] ?? $plain;
	}
	return $key;
}

/**
 * Compare two strings using Estonian collation.
 */
function ak_compare_et( string $a, string $b ): int {
	static $collator = null;
	if ( null === $collator ) {
		$collator = false;
		if ( class_exists( 'Collator' ) ) {
			try {
				$c = new Collator( 'et_EE' );
				if ( ! intl_is_failure( $c->getErrorCode() ) ) {
					$collator = $c;
				}
			} catch ( Throwable $e ) {
				$collator = false;
			}
		}
	}
	if ( $collator instanceof Collator ) {
		$result = $collator->compare( $a, $b );
		if ( false !== $result ) {
			return (int) $result;
		}
	}
	return strcmp( ak_et_sort_key( $a ), ak_et_sort_key( $b ) );
}

/**
 * Split a name into [surname, first names]; the surname is the last word.
 *
 * @return string[]
 */
function ak_split_name( string $name ): array {
	$parts = preg_split( '/\s+/u', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $parts ) || ! $parts ) {
		return array( '', '' );
	}
	$surname = (string) array_pop( $parts );
	return array( $surname, implode( ' ', $parts ) );
}

/**
 * Published members sorted by surname, then first name.
 *
 * @return WP_Post[]
 */
function ak_get_sorted_members(): array {
	$members = get_posts(
		array(
			'post_type'        => 'ak_member',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);

	usort(
		$members,
		static function ( WP_Post $a, WP_Post $b ): int {
			[ $a_last, $a_first ] = ak_split_name( $a->post_title );
			[ $b_last, $b_first ] = ak_split_name( $b->post_title );
			$cmp = ak_compare_et( $a_last, $b_last );
			if ( 0 === $cmp ) {
				$cmp = ak_compare_et( $a_first, $b_first );
			}
			return 0 !== $cmp ? $cmp : $a->ID <=> $b->ID;
		}
	);

	return $members;
}

/**
 * [ak_members]
 */
function ak_shortcode_members(): string {
	$members = ak_get_sorted_members();
	if ( ! $members ) {
		return '<p class="ak-members__empty">' . esc_html( ak_t( 'members_empty' ) ) . '</p>';
	}

	$company_label = '<span class="screen-reader-text">' . esc_html( ak_t( 'company' ) ) . ': </span>';
	$website_label = '<span class="screen-reader-text">' . esc_html( ak_t( 'website' ) ) . ': </span>';

	$out = '<ul class="ak-members">';
	foreach ( $members as $member ) {
		$name    = get_the_title( $member );
		$company = (string) get_post_meta( $member->ID, '_ak_company', true );
		$website = (string) get_post_meta( $member->ID, '_ak_website', true );

		$out .= '<li class="ak-member">';

		$thumb_id = (int) get_post_thumbnail_id( $member );
		if ( $thumb_id ) {
			$out .= wp_get_attachment_image(
				$thumb_id,
				'thumbnail',
				false,
				array(
					'class'   => 'ak-member__logo',
					'alt'     => '' !== $company ? $company : wp_strip_all_tags( $name ),
					'loading' => 'lazy',
				)
			);
		}

		$out .= '<p class="ak-member__name">' . esc_html( $name ) . '</p>';

		if ( '' !== $company ) {
			$company_html = esc_html( $company );
			if ( '' !== $website ) {
				$company_html = '<a href="' . esc_url( $website ) . '" rel="noopener">' . $company_html . '</a>';
			}
			$out .= '<p class="ak-member__company">' . $company_label . $company_html . '</p>';
		}

		if ( '' !== $website ) {
			$out .= '<p class="ak-member__web">' . $website_label . '<a href="' . esc_url( $website ) . '" rel="noopener">' . esc_html( ak_url_label( $website ) ) . '</a></p>';
		}

		$out .= '</li>';
	}
	$out .= '</ul>';

	return $out;
}
add_shortcode( 'ak_members', 'ak_shortcode_members' );

/**
 * [ak_member_count] – number of published members.
 */
function ak_shortcode_member_count(): string {
	$counts = wp_count_posts( 'ak_member' );
	return (string) (int) ( $counts->publish ?? 0 );
}
add_shortcode( 'ak_member_count', 'ak_shortcode_member_count' );
