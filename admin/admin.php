<?php

require_once WPCF7_PLUGIN_DIR . '/admin/admin-functions.php';

add_action( 'admin_menu', 'wpcf7_admin_menu', 9 );

function wpcf7_admin_menu() {
	add_menu_page( __( 'Contact Form 7', 'wpcf7' ), __( 'Contact', 'wpcf7' ),
		'wpcf7_read_contact_forms', 'wpcf7', 'wpcf7_admin_management_page',
		wpcf7_plugin_url( 'admin/images/menu-icon.png' ) );

	$contact_form_admin = add_submenu_page( 'wpcf7',
		__( 'Edit Contact Forms', 'wpcf7' ), __( 'Edit', 'wpcf7' ),
		'wpcf7_read_contact_forms', 'wpcf7', 'wpcf7_admin_management_page' );

	add_action( 'load-' . $contact_form_admin, 'wpcf7_load_contact_form_admin' );
}

add_filter( 'set-screen-option', 'wpcf7_set_screen_options', 10, 3 );

function wpcf7_set_screen_options( $result, $option, $value ) {
	$wpcf7_screens = array(
		'cfseven_contact_forms_per_page' );

	if ( in_array( $option, $wpcf7_screens ) )
		$result = $value;

	return $result;
}

function wpcf7_load_contact_form_admin() {
	if ( ! wpcf7_admin_has_edit_cap() )
		return;

	$action = wpcf7_current_action();

	if ( 'save' == $action ) {
		$id = $_POST['post_ID'];
		check_admin_referer( 'wpcf7-save-contact-form_' . $id );

		if ( ! $contact_form = wpcf7_contact_form( $id ) ) {
			$contact_form = new WPCF7_ContactForm();
			$contact_form->initial = true;
		}

		$contact_form->title = trim( $_POST['wpcf7-title'] );

		$form = trim( $_POST['wpcf7-form'] );

		$mail = array(
			'subject' => trim( $_POST['wpcf7-mail-subject'] ),
			'sender' => trim( $_POST['wpcf7-mail-sender'] ),
			'body' => trim( $_POST['wpcf7-mail-body'] ),
			'recipient' => trim( $_POST['wpcf7-mail-recipient'] ),
			'additional_headers' => trim( $_POST['wpcf7-mail-additional-headers'] ),
			'attachments' => trim( $_POST['wpcf7-mail-attachments'] ),
			'use_html' =>
				isset( $_POST['wpcf7-mail-use-html'] ) && 1 == $_POST['wpcf7-mail-use-html']
		);

		$mail_2 = array(
			'active' =>
				isset( $_POST['wpcf7-mail-2-active'] ) && 1 == $_POST['wpcf7-mail-2-active'],
			'subject' => trim( $_POST['wpcf7-mail-2-subject'] ),
			'sender' => trim( $_POST['wpcf7-mail-2-sender'] ),
			'body' => trim( $_POST['wpcf7-mail-2-body'] ),
			'recipient' => trim( $_POST['wpcf7-mail-2-recipient'] ),
			'additional_headers' => trim( $_POST['wpcf7-mail-2-additional-headers'] ),
			'attachments' => trim( $_POST['wpcf7-mail-2-attachments'] ),
			'use_html' =>
				isset( $_POST['wpcf7-mail-2-use-html'] ) && 1 == $_POST['wpcf7-mail-2-use-html']
		);

		$messages = isset( $contact_form->messages ) ? $contact_form->messages : array();

		foreach ( wpcf7_messages() as $key => $arr ) {
			$field_name = 'wpcf7-message-' . strtr( $key, '_', '-' );
			if ( isset( $_POST[$field_name] ) )
				$messages[$key] = trim( $_POST[$field_name] );
		}

		$additional_settings = trim( $_POST['wpcf7-additional-settings'] );

		$props = apply_filters( 'wpcf7_contact_form_admin_posted_properties',
			compact( 'form', 'mail', 'mail_2', 'messages', 'additional_settings' ) );

		foreach ( (array) $props as $key => $prop )
			$contact_form->{$key} = $prop;

		$query = array();
		$query['message'] = ( $contact_form->initial ) ? 'created' : 'saved';

		$contact_form->save();

		$query['post'] = $contact_form->id;
		$redirect_to = wpcf7_admin_url( $query );
		wp_safe_redirect( $redirect_to );
		exit();
	}

	if ( 'copy' == $action ) {
		$id = $_POST['post_ID'];
		check_admin_referer( 'wpcf7-copy-contact-form_' . $id );

		$query = array();

		if ( $contact_form = wpcf7_contact_form( $id ) ) {
			$new_contact_form = $contact_form->copy();
			$new_contact_form->save();

			$query['post'] = $new_contact_form->id;
			$query['message'] = 'created';
		} else {
			$query['post'] = $contact_form->id;
		}

		$redirect_to = wpcf7_admin_url( $query );
		wp_safe_redirect( $redirect_to );
		exit();
	}

	if ( 'delete' == $action ) {
		if ( ! empty( $_POST['post_ID'] ) )
			check_admin_referer( 'wpcf7-delete-contact-form_' . $_POST['post_ID'] );
		elseif ( ! is_array( $_REQUEST['post'] ) )
			check_admin_referer( 'wpcf7-delete-contact-form_' . $_REQUEST['post'] );
		else
			check_admin_referer( 'bulk-posts' );

		$posts = empty( $_POST['post_ID'] )
			? (array) $_REQUEST['post']
			: (array) $_POST['post_ID'];

		$deleted = 0;

		foreach ( $posts as $post ) {
			$post = new WPCF7_ContactForm( $post );

			if ( empty( $post ) )
				continue;

			if ( ! current_user_can( 'wpcf7_delete_contact_form', $post->id ) )
				wp_die( __( 'You are not allowed to delete this item.', 'wpcf7' ) );

			if ( ! $post->delete() )
				wp_die( __( 'Error in deleting.', 'wpcf7' ) );

			$deleted += 1;
		}

		if ( ! empty( $deleted ) )
			$redirect_to = wpcf7_admin_url( array( 'message' => 'deleted' ) );

		wp_safe_redirect( $redirect_to );
		exit();
	}

	if ( empty( $_GET['post'] ) ) {
		$current_screen = get_current_screen();

		if ( ! class_exists( 'WPCF7_Contact_Form_List_Table' ) )
			require_once WPCF7_PLUGIN_DIR . '/admin/includes/class-contact-forms-list-table.php';

		add_filter( 'manage_' . $current_screen->id . '_columns',
			array( 'WPCF7_Contact_Form_List_Table', 'define_columns' ) );

		add_screen_option( 'per_page', array(
			'label' => __( 'Contact Forms', 'wpcf7' ),
			'default' => 20,
			'option' => 'cfseven_contact_forms_per_page' ) );
	}
}

add_action( 'admin_enqueue_scripts', 'wpcf7_admin_enqueue_scripts' );

function wpcf7_admin_enqueue_scripts( $hook_suffix ) {
	global $wpcf7_tag_generators;

	if ( false === strpos( $hook_suffix, 'wpcf7' ) )
		return;

	wp_enqueue_style( 'contact-form-7-admin', wpcf7_plugin_url( 'admin/styles.css' ),
		array( 'thickbox' ), WPCF7_VERSION, 'all' );

	if ( wpcf7_is_rtl() ) {
		wp_enqueue_style( 'contact-form-7-admin-rtl',
			wpcf7_plugin_url( 'admin/styles-rtl.css' ), array(), WPCF7_VERSION, 'all' );
	}

	wp_enqueue_script( 'wpcf7-admin-taggenerator', wpcf7_plugin_url( 'admin/taggenerator.js' ),
		array( 'jquery' ), WPCF7_VERSION, true );

	wp_enqueue_script( 'wpcf7-admin', wpcf7_plugin_url( 'admin/scripts.js' ),
		array( 'jquery', 'thickbox', 'postbox', 'wpcf7-admin-taggenerator' ),
		WPCF7_VERSION, true );

	$taggenerators = array();

	foreach ( (array) $wpcf7_tag_generators as $name => $tg ) {
		$taggenerators[$name] = array_merge(
			(array) $tg['options'],
			array( 'title' => $tg['title'], 'content' => $tg['content'] ) );
	}

	wp_localize_script( 'wpcf7-admin', '_wpcf7', array(
		'generateTag' => __( 'Generate Tag', 'wpcf7' ),
		'pluginUrl' => wpcf7_plugin_url(),
		'tagGenerators' => $taggenerators ) );
}

add_action( 'admin_print_footer_scripts', 'wpcf7_print_taggenerators_json', 20 );

function wpcf7_print_taggenerators_json() { // for backward compatibility
	global $plugin_page, $wpcf7_tag_generators;

	if ( ! version_compare( get_bloginfo( 'version' ), '3.3-dev', '<' ) )
		return;

	if ( ! isset( $plugin_page ) || 'wpcf7' != $plugin_page )
		return;

	$taggenerators = array();

	foreach ( (array) $wpcf7_tag_generators as $name => $tg ) {
		$taggenerators[$name] = array_merge(
			(array) $tg['options'],
			array( 'title' => $tg['title'], 'content' => $tg['content'] ) );
	}

?>
<script type="text/javascript">
/* <![CDATA[ */
_wpcf7.tagGenerators = <?php echo json_encode( $taggenerators ) ?>;
/* ]]> */
</script>
<?php
}

function wpcf7_admin_management_page() {
	$cf = null;
	$unsaved = false;

	if ( ! isset( $_GET['post'] ) )
		$_GET['post'] = '';

	if ( 'new' == $_GET['post'] && wpcf7_admin_has_edit_cap() ) {
		$unsaved = true;
		$current = -1;
		$cf = wpcf7_get_contact_form_default_pack(
			array( 'locale' => ( isset( $_GET['locale'] ) ? $_GET['locale'] : '' ) ) );
	} elseif ( $cf = wpcf7_contact_form( $_GET['post'] ) ) {
		$current = (int) $_GET['post'];
	}

	if ( $cf ) {
		require_once WPCF7_PLUGIN_DIR . '/admin/includes/meta-boxes.php';
		require_once WPCF7_PLUGIN_DIR . '/admin/edit-contact-form.php';
		return;
	}

	$list_table = new WPCF7_Contact_Form_List_Table();
	$list_table->prepare_items();

?>
<div class="wrap">
<?php screen_icon(); ?>

<h2><?php
	echo esc_html( __( 'Contact Form 7', 'wpcf7' ) );

	echo ' <a href="#TB_inline?height=300&width=400&inlineId=wpcf7-lang-select-modal" class="add-new-h2 thickbox">' . esc_html( __( 'Add New', 'wpcf7' ) ) . '</a>';

	if ( ! empty( $_REQUEST['s'] ) ) {
		echo sprintf( '<span class="subtitle">'
			. __( 'Search results for &#8220;%s&#8221;', 'wpcf7' )
			. '</span>', esc_html( $_REQUEST['s'] ) );
	}
?></h2>

<?php do_action( 'wpcf7_admin_notices' ); ?>

<form method="get" action="">
	<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
	<?php $list_table->search_box( __( 'Search Contact Forms', 'wpcf7' ), 'wpcf7-contact' ); ?>
	<?php $list_table->display(); ?>
</form>

</div>

<div id="wpcf7-lang-select-modal" class="hidden">
<?php
	$available_locales = wpcf7_l10n();
	$default_locale = get_locale();

	if ( ! isset( $available_locales[$default_locale] ) )
		$default_locale = 'en_US';

?>
<h4><?php echo esc_html( sprintf( __( 'Use the default language (%s)', 'wpcf7' ), $available_locales[$default_locale] ) ); ?></h4>
<p><a href="<?php echo wpcf7_admin_url( array( 'post' => 'new' ) ); ?>" class="button" /><?php echo esc_html( __( 'Add New', 'wpcf7' ) ); ?></a></p>

<?php unset( $available_locales[$default_locale] ); ?>
<h4><?php echo esc_html( __( 'Or', 'wpcf7' ) ); ?></h4>
<form action="" method="get">
<input type="hidden" name="page" value="wpcf7" />
<input type="hidden" name="post" value="new" />
<select name="locale">
<option value="" selected="selected"><?php echo esc_html( __( '(select language)', 'wpcf7' ) ); ?></option>
<?php foreach ( $available_locales as $code => $locale ) : ?>
<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $locale ); ?></option>
<?php endforeach; ?>
</select>
<input type="submit" class="button" value="<?php echo esc_attr( __( 'Add New', 'wpcf7' ) ); ?>" />
</form>
</div>
<?php
}

/* Misc */

add_action( 'wpcf7_admin_notices', 'wpcf7_admin_before_subsubsub' );

function wpcf7_admin_before_subsubsub() {
	// wpcf7_admin_before_subsubsub is deprecated. Use wpcf7_admin_notices instead.

	$current_screen = get_current_screen();

	if ( 'toplevel_page_wpcf7' != $current_screen->id )
		return;

	if ( empty( $_GET['post'] ) || ! $contact_form = wpcf7_contact_form( $_GET['post'] ) )
		return;

	do_action_ref_array( 'wpcf7_admin_before_subsubsub', array( &$contact_form ) );
}

add_action( 'wpcf7_admin_notices', 'wpcf7_admin_updated_message' );

function wpcf7_admin_updated_message() {
	if ( empty( $_REQUEST['message'] ) )
		return;

	if ( 'created' == $_REQUEST['message'] )
		$updated_message = esc_html( __( 'Contact form created.', 'wpcf7' ) );
	elseif ( 'saved' == $_REQUEST['message'] )
		$updated_message = esc_html( __( 'Contact form saved.', 'wpcf7' ) );
	elseif ( 'deleted' == $_REQUEST['message'] )
		$updated_message = esc_html( __( 'Contact form deleted.', 'wpcf7' ) );

	if ( empty( $updated_message ) )
		return;

?>
<div id="message" class="updated"><p><?php echo $updated_message; ?></p></div>
<?php
}

add_filter( 'plugin_action_links', 'wpcf7_plugin_action_links', 10, 2 );

function wpcf7_plugin_action_links( $links, $file ) {
	if ( $file != WPCF7_PLUGIN_BASENAME )
		return $links;

	$url = wpcf7_admin_url();

	$settings_link = '<a href="' . esc_attr( $url ) . '">'
		. esc_html( __( 'Settings', 'wpcf7' ) ) . '</a>';

	array_unshift( $links, $settings_link );

	return $links;
}

add_action( 'wpcf7_admin_notices', 'wpcf7_cf7com_links', 9 );

function wpcf7_cf7com_links() {
	$links = '<div class="cf7com-links">'
		. '<a href="' . esc_url_raw( __( 'http://contactform7.com/', 'wpcf7' ) ) . '" target="_blank">'
		. esc_html( __( 'Contactform7.com', 'wpcf7' ) ) . '</a>&ensp;'
		. '<a href="' . esc_url_raw( __( 'http://contactform7.com/docs/', 'wpcf7' ) ) . '" target="_blank">'
		. esc_html( __( 'Docs', 'wpcf7' ) ) . '</a> - '
		. '<a href="' . esc_url_raw( __( 'http://contactform7.com/faq/', 'wpcf7' ) ) . '" target="_blank">'
		. esc_html( __( 'FAQ', 'wpcf7' ) ) . '</a> - '
		. '<a href="' . esc_url_raw( __( 'http://contactform7.com/support/', 'wpcf7' ) ) . '" target="_blank">'
		. esc_html( __( 'Support', 'wpcf7' ) ) . '</a>'
		. '</div>';

	echo apply_filters( 'wpcf7_cf7com_links', $links );
}

add_action( 'wpcf7_admin_notices', 'wpcf7_donation_link' );

function wpcf7_donation_link() {
	if ( ! WPCF7_SHOW_DONATION_LINK )
		return;

	if ( ! empty( $_REQUEST['post'] ) && 'new' == $_REQUEST['post'] )
		return;

	if ( ! empty( $_REQUEST['message'] ) )
		return;

	$show_link = true;

	$num = mt_rand( 0, 99 );

	if ( $num >= 20 )
		$show_link = false;

	$show_link = apply_filters( 'wpcf7_show_donation_link', $show_link );

	if ( ! $show_link )
		return;

	$texts = array(
		__( "Contact Form 7 needs your support. Please donate today.", 'wpcf7' ),
		__( "Your contribution is needed for making this plugin better.", 'wpcf7' ) );

	$text = $texts[array_rand( $texts )];

?>
<div class="donation">
<p><a href="<?php echo esc_url_raw( __( 'http://contactform7.com/donate/', 'wpcf7' ) ); ?>"><?php echo esc_html( $text ); ?></a> <a href="<?php echo esc_url_raw( __( 'http://contactform7.com/donate/', 'wpcf7' ) ); ?>" class="button"><?php echo esc_html( __( "Donate", 'wpcf7' ) ); ?></a></p>
</div>
<?php
}

add_action( 'admin_notices', 'wpcf7_old_wp_version_error', 9 );

function wpcf7_old_wp_version_error() {
	global $plugin_page;

	if ( 'wpcf7' != $plugin_page )
		return;

	$wp_version = get_bloginfo( 'version' );

	if ( ! version_compare( $wp_version, WPCF7_REQUIRED_WP_VERSION, '<' ) )
		return;

?>
<div class="error">
<p><?php echo sprintf( __( '<strong>Contact Form 7 %1$s requires WordPress %2$s or higher.</strong> Please <a href="%3$s">update WordPress</a> first.', 'wpcf7' ), WPCF7_VERSION, WPCF7_REQUIRED_WP_VERSION, admin_url( 'update-core.php' ) ); ?></p>
</div>
<?php
}

?>