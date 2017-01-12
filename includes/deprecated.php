<?php

/**
 * @deprecated since 1.35
 * @todo remove after 01.06.2017
 */
/* Adding 'output_order' array option since 'taxonomy' type has been added to search and unsetting 'post_types' option as deprecated */
if ( ! function_exists( 'cstmsrch_update_old_options' ) ) {
	function cstmsrch_update_old_options() {
		global $cstmsrch_options;
		if ( ! empty( $cstmsrch_options['post_types'] ) ) {
			$output_order = array();
			foreach ( $cstmsrch_options['post_types'] as $value) {
				$output_order[] = array( 'name' => $value, 'type' => 'post_type', 'enabled' => 1 );
			}
			$cstmsrch_options['output_order'] = $output_order;
		}
		unset( $cstmsrch_options['post_types'] );
		update_option( 'cstmsrch_options', $cstmsrch_options );
	}
}

/**
 * @since 1.35
 * @todo remove after 01.05.2017
 */
if ( ! function_exists( 'cstmsrch_clear_uninstall_plugins_array' ) ) {
	function cstmsrch_clear_uninstall_plugins_array() {
		if ( is_multisite() ) {
			global $wpdb;
			/* Get all blog ids */
			$blogids = $wpdb->get_col( "SELECT `blog_id` FROM $wpdb->blogs" );
			$old_blog = $wpdb->blogid;
			foreach ( $blogids as $blog_id ) {
				switch_to_blog( $blog_id );
				$uninstallable_plugins = (array) get_option('uninstall_plugins');
				unset( $uninstallable_plugins[ 'custom-search-plugin/custom-search-plugin.php' ] );
				update_option('uninstall_plugins', $uninstallable_plugins);
			}
			switch_to_blog( $old_blog );
		}
	}
}
/* deprecated end */