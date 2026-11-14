<?php
/**
 * CPT ak_member: club members (name in the title, company and website in meta).
 *
 * @package ariklubi-core
 */

defined( 'ABSPATH' ) || exit;

function ak_register_member_post_type(): void {
	$labels = array(
		'name'                  => ak_at( 'Liikmed', 'Members' ),
		'singular_name'         => ak_at( 'Liige', 'Member' ),
		'menu_name'             => ak_at( 'Liikmed', 'Members' ),
		'all_items'             => ak_at( 'Kõik liikmed', 'All members' ),
		'add_new'               => ak_at( 'Lisa uus', 'Add new' ),
		'add_new_item'          => ak_at( 'Lisa uus liige', 'Add new member' ),
		'edit_item'             => ak_at( 'Muuda liiget', 'Edit member' ),
		'new_item'              => ak_at( 'Uus liige', 'New member' ),
		'view_item'             => ak_at( 'Vaata liiget', 'View member' ),
		'search_items'          => ak_at( 'Otsi liikmeid', 'Search members' ),
		'not_found'             => ak_at( 'Liikmeid ei leitud.', 'No members found.' ),
		'not_found_in_trash'    => ak_at( 'Prügikastis liikmeid ei ole.', 'No members found in Trash.' ),
		'featured_image'        => ak_at( 'Logo või foto', 'Logo or photo' ),
		'set_featured_image'    => ak_at( 'Määra logo või foto', 'Set logo or photo' ),
		'remove_featured_image' => ak_at( 'Eemalda logo või foto', 'Remove logo or photo' ),
		'use_featured_image'    => ak_at( 'Kasuta logona', 'Use as logo' ),
		'item_published'        => ak_at( 'Liige lisatud.', 'Member published.' ),
		'item_updated'          => ak_at( 'Liige uuendatud.', 'Member updated.' ),
	);

	register_post_type(
		'ak_member',
		array(
			'labels'              => $labels,
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
			'menu_position'       => 22,
			'menu_icon'           => 'dashicons-groups',
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title', 'thumbnail', 'page-attributes', 'custom-fields' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		)
	);

	$meta_args = array(
		'type'          => 'string',
		'single'        => true,
		'show_in_rest'  => true,
		'default'       => '',
		'auth_callback' => static function () {
			return current_user_can( 'edit_posts' );
		},
	);
	register_post_meta( 'ak_member', '_ak_company', $meta_args + array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_post_meta( 'ak_member', '_ak_website', $meta_args + array( 'sanitize_callback' => 'ak_sanitize_website' ) );
}
add_action( 'init', 'ak_register_member_post_type' );

/**
 * Normalise a website URL; bare domains get https://.
 */
function ak_sanitize_website( $value ): string {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $value ) ) {
		$value = 'https://' . ltrim( $value, '/' );
	}
	return esc_url_raw( $value, array( 'http', 'https' ) );
}

function ak_member_title_placeholder( $title, $post ) {
	if ( $post instanceof WP_Post && 'ak_member' === $post->post_type ) {
		return 'Eesnimi Perenimi';
	}
	return $title;
}
add_filter( 'enter_title_here', 'ak_member_title_placeholder', 10, 2 );

function ak_member_add_meta_box(): void {
	add_meta_box(
		'ak_member_details',
		ak_at( 'Ettevõte ja koduleht', 'Company and website' ),
		'ak_member_render_meta_box',
		'ak_member',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_ak_member', 'ak_member_add_meta_box' );

function ak_member_render_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'ak_member_save', 'ak_member_nonce' );
	$company = (string) get_post_meta( $post->ID, '_ak_company', true );
	$website = (string) get_post_meta( $post->ID, '_ak_website', true );
	?>
	<p>
		<label for="ak_company"><strong><?php echo esc_html( ak_at( 'Ettevõte', 'Company' ) ); ?></strong></label><br>
		<input type="text" id="ak_company" name="ak_company" class="widefat" value="<?php echo esc_attr( $company ); ?>">
	</p>
	<p>
		<label for="ak_website"><strong><?php echo esc_html( ak_at( 'Koduleht', 'Website' ) ); ?></strong></label><br>
		<input type="url" id="ak_website" name="ak_website" class="widefat" placeholder="https://" value="<?php echo esc_attr( $website ); ?>">
	</p>
	<p class="description">
		<?php echo esc_html( ak_at( 'Pealkirjaks kirjuta liikme nimi kujul „Eesnimi Perenimi“. Liikmed järjestatakse perenime järgi. Logo või foto saab lisada paremalt.', 'Enter the member\'s name as the title ("First Last"). Members are sorted by surname. A logo or photo can be added in the sidebar.' ) ); ?>
	</p>
	<?php
}

function ak_member_save_meta( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['ak_member_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ak_member_nonce'] ) ), 'ak_member_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = array(
		'_ak_company' => isset( $_POST['ak_company'] ) ? sanitize_text_field( wp_unslash( $_POST['ak_company'] ) ) : '',
		'_ak_website' => isset( $_POST['ak_website'] ) ? ak_sanitize_website( wp_unslash( $_POST['ak_website'] ) ) : '',
	);
	foreach ( $fields as $key => $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
add_action( 'save_post_ak_member', 'ak_member_save_meta' );

function ak_member_admin_columns( array $columns ): array {
	return array(
		'cb'         => $columns['cb'] ?? '<input type="checkbox">',
		'title'      => ak_at( 'Nimi', 'Name' ),
		'ak_company' => ak_at( 'Ettevõte', 'Company' ),
		'ak_website' => ak_at( 'Koduleht', 'Website' ),
	);
}
add_filter( 'manage_ak_member_posts_columns', 'ak_member_admin_columns' );

function ak_member_admin_column_content( string $column, int $post_id ): void {
	if ( 'ak_company' === $column ) {
		$company = (string) get_post_meta( $post_id, '_ak_company', true );
		echo '' !== $company ? esc_html( $company ) : '&mdash;';
	} elseif ( 'ak_website' === $column ) {
		$website = (string) get_post_meta( $post_id, '_ak_website', true );
		if ( '' !== $website ) {
			printf( '<a href="%1$s" rel="noopener">%2$s</a>', esc_url( $website ), esc_html( ak_url_label( $website ) ) );
		} else {
			echo '&mdash;';
		}
	}
}
add_action( 'manage_ak_member_posts_custom_column', 'ak_member_admin_column_content', 10, 2 );

function ak_member_sortable_columns( array $columns ): array {
	$columns['title']      = 'title';
	$columns['ak_company'] = 'ak_company';
	return $columns;
}
add_filter( 'manage_edit-ak_member_sortable_columns', 'ak_member_sortable_columns' );

/**
 * Admin list: alphabetical by default, and sortable by company.
 *
 * @param WP_Query $query Query.
 */
function ak_member_admin_order( $query ): void {
	if ( ! is_admin() || ! $query->is_main_query() || 'ak_member' !== $query->get( 'post_type' ) ) {
		return;
	}
	$orderby = $query->get( 'orderby' );
	if ( 'ak_company' === $orderby ) {
		$query->set( 'meta_key', '_ak_company' );
		$query->set( 'orderby', 'meta_value' );
	} elseif ( empty( $orderby ) ) {
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'ak_member_admin_order' );

/**
 * Display label for a URL: host without scheme or leading "www.".
 */
function ak_url_label( string $url ): string {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! is_string( $host ) || '' === $host ) {
		return $url;
	}
	return (string) preg_replace( '/^www\./i', '', $host );
}
