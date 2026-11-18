<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all data the plugin created: every `maqsy` FAQ and `maqsy_quiz`
 * quiz post (and their post meta, via wp_delete_post()'s force-delete), the
 * quiz sessions/events tables, and the plugin's options.
 *
 * @since      1.0.0
 *
 * @package    Maqsy
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/quiz/class-maqsy-quiz-store.php';

/**
 * Delete all Maqsy data for a single site.
 *
 * @since 1.0.0
 */
function maqsy_uninstall_site() {

	$post_ids = get_posts(
		array(
			'post_type'   => array( 'maqsy', 'maqsy_quiz' ),
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	);

	foreach ( $post_ids as $post_id ) {
		wp_delete_post( $post_id, true );
	}

	Maqsy_Quiz_Store::drop_tables();

	delete_option( 'maqsy_ai_settings' );
}

if ( is_multisite() ) {
	$maqsy_site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $maqsy_site_ids as $maqsy_site_id ) {
		switch_to_blog( $maqsy_site_id );
		maqsy_uninstall_site();
		restore_current_blog();
	}
} else {
	maqsy_uninstall_site();
}
