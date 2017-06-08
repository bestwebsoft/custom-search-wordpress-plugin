<?php
/*
Plugin Name: Custom Search by BestWebSoft
Plugin URI: https://bestwebsoft.com/products/wordpress/plugins/custom-search/
Description: Add custom post types to WordPress website search results.
Author: BestWebSoft
Text Domain: custom-search-plugin
Domain Path: /languages
Version: 1.37
Author URI: https://bestwebsoft.com/
License: GPLv2 or later
*/

/*  © Copyright 2017  BestWebSoft  ( https://support.bestwebsoft.com )

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/* Function are using to add on admin-panel Wordpress page 'bws_panel' and sub-page of this plugin */
if ( ! function_exists( 'add_cstmsrch_admin_menu' ) ) {
	function add_cstmsrch_admin_menu() {
		bws_general_menu();
		$settings = add_submenu_page( 'bws_panel', __( 'Custom Search Settings', 'custom-search-plugin' ), 'Custom Search', 'manage_options', "custom_search.php", 'cstmsrch_settings_page' );
		add_action( 'load-' . $settings, 'cstmsrch_add_tabs' );
	}
}

if ( ! function_exists( 'cstmsrch_plugins_loaded' ) ) {
	function cstmsrch_plugins_loaded() {
		/* Function adds translations in this plugin */
		load_plugin_textdomain( 'custom-search-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}
}

if ( ! function_exists ( 'cstmsrch_init' ) ) {
	function cstmsrch_init() {
		global $cstmsrch_options, $cstmsrch_plugin_info;

		require_once( dirname( __FILE__ ) . '/bws_menu/bws_include.php' );
		bws_include_init( plugin_basename( __FILE__ ) );

		if ( empty( $cstmsrch_plugin_info ) ) {
			if ( ! function_exists( 'get_plugin_data' ) )
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			$cstmsrch_plugin_info = get_plugin_data( __FILE__ );
		}

		/* Function check if plugin is compatible with current WP version */
		bws_wp_min_version_check( plugin_basename( __FILE__ ), $cstmsrch_plugin_info, '3.9' );
	}
}

if ( ! function_exists( 'cstmsrch_admin_init' ) ) {
	function cstmsrch_admin_init() {
		global $bws_plugin_info, $cstmsrch_plugin_info, $cstmsrch_options;
		if ( empty( $bws_plugin_info ) )
			$bws_plugin_info = array( 'id' => '81', 'version' => $cstmsrch_plugin_info['Version'] );
	}
}

/* Function create column in table wp_options for option of this plugin. If this column exists - save value in variable. */
if ( ! function_exists( 'register_cstmsrch_settings' ) ) {
	function register_cstmsrch_settings() {
		global $cstmsrch_options, $bws_plugin_info, $cstmsrch_plugin_info, $cstmsrch_options_default;

		$cstmsrch_options_default = array(
			'plugin_option_version'		=>	$cstmsrch_plugin_info['Version'],
			'output_order'				=> array(
											array( 'name' => 'post', 'type' => 'post_type', 'enabled' => 1 ),
											array( 'name' => 'page', 'type' => 'post_type', 'enabled' => 1 ),
										),
			'first_install'				=>	strtotime( "now" ),
			'display_settings_notice'	=>	1,
			'suggest_feature_banner'	=>	1,
		);

		/* Install the option defaults */
		if ( ! get_option( 'cstmsrch_options' ) )
			add_option( 'cstmsrch_options', $cstmsrch_options_default );

		$cstmsrch_options = get_option( 'cstmsrch_options' );

		/* Array merge incase this version has added new options */
		if ( ! isset( $cstmsrch_options['plugin_option_version'] ) || $cstmsrch_options['plugin_option_version'] != $cstmsrch_plugin_info['Version'] ) {

			foreach ( $cstmsrch_options_default as $key => $value ) {
				if (
					! isset( $cstmsrch_options[ $key ] ) ||
					( isset( $cstmsrch_options[ $key ] ) && is_array( $cstmsrch_options_default[ $key ] ) && ! is_array( $cstmsrch_options[ $key ] ) )
				) {
					$cstmsrch_options[ $key ] = $cstmsrch_options_default[ $key ];
				} else {
					if ( is_array( $cstmsrch_options_default[ $key ] ) ) {
						foreach ( $cstmsrch_options_default[ $key ] as $key2 => $value2 ) {
							if ( ! isset( $cstmsrch_options[ $key ][ $key2 ] ) )
								$cstmsrch_options[ $key ][ $key2 ] = $cstmsrch_options_default[ $key ][ $key2 ];
						}
					}
				}
			}

			$cstmsrch_options['plugin_option_version'] = $cstmsrch_plugin_info['Version'];
			/* show pro features */
			$cstmsrch_options['hide_premium_options'] = array();
			cstmsrch_update_option( true );
			update_option( 'cstmsrch_options', $cstmsrch_options );
			cstmsrch_plugin_activate();
		}
		cstmsrch_search_objects();
	}
}

/**
 * Activation plugin function
 */
if ( ! function_exists( 'cstmsrch_plugin_activate' ) ) {
	function cstmsrch_plugin_activate() {
		if ( is_multisite() ) {
			switch_to_blog( 1 );
			register_uninstall_hook( __FILE__, 'delete_cstmsrch_settings' );
			restore_current_blog();
		} else {
			register_uninstall_hook( __FILE__, 'delete_cstmsrch_settings' );
		}
	}
}

/**
 * Preparing global array variables of post types and taxonomies enabled for search
 * @return void
 */
if ( ! function_exists( 'cstmsrch_search_objects' ) ) {
	function cstmsrch_search_objects() {
		global $cstmsrch_options, $cstmsrch_post_types_enabled, $cstmsrch_taxonomies_enabled;
		if ( empty( $cstmsrch_options ) )
			$cstmsrch_options = get_option( 'cstmsrch_options' );
		$cstmsrch_post_types_enabled = $cstmsrch_taxonomies_enabled = array();
		foreach ( $cstmsrch_options['output_order'] as $key => $item ) {
			if ( isset( $item['type'] ) && ! empty( $item['enabled'] ) ) {
				if ( 'post_type' == $item['type'] ) {
					$cstmsrch_post_types_enabled[] = $item['name'];
				} elseif ( 'taxonomy' == $item['type'] ) {
					$cstmsrch_taxonomies_enabled[] = $item['name'];
				}
			}
		}
	}
}

/**
 * Update plugin options
 * if custom post types was added or deleted
 * @return void
 */
if ( ! function_exists( 'cstmsrch_update_option' ) ) {
	function cstmsrch_update_option( $option_changed = false ) {
		global $cstmsrch_options;
		if ( empty( $cstmsrch_options ) )
			$cstmsrch_options = get_option( 'cstmsrch_options' );

		/* get custom post types */
		$post_types_custom	= get_post_types( array( '_builtin' => false ), 'names' );
		$post_types_default	= get_post_types( array( '_builtin' => true, 'public' => true ), 'names' );
		unset( $post_types_default['attachment'] );
		$post_types_global	= array_merge( $post_types_custom, $post_types_default );
		$taxonomies_global 	= get_taxonomies( array( 'public' => true ), 'names' );
		unset( $taxonomies_global['post_format'] );
		$order_items_keys = array();
		/* unsetting non-existent post types/taxonomies */
		foreach ( $cstmsrch_options['output_order'] as $key => $item ) {
			if (
				empty( $item['name'] ) ||
				! ( in_array( $item['name'], $post_types_global ) || in_array( $item['name'], $taxonomies_global ) )
			) {
				$option_changed = true;
				unset( $cstmsrch_options['output_order'][$key] );
			} else {
				if ( empty( $item['enabled'] ) )
					$cstmsrch_options['output_order'][ $key ]['enabled'] = 0;
				$order_items_keys[] = $item['name'];
			}
		}

		/* adding new post types/taxonomies to order list */
		foreach ( $post_types_global as $key => $post_type ) {
			if ( ! in_array( $post_type, $order_items_keys ) ) {
				$cstmsrch_options['output_order'][] = array (
					'name'		=> $post_type,
					'type'		=> 'post_type',
					'enabled'	=> 0
				);
				$option_changed = true;
			}
		}
		foreach ( $taxonomies_global as $taxonomy => $taxonomy_object ) {
			if ( ! in_array( $taxonomy, $order_items_keys ) ) {
				$cstmsrch_options['output_order'][] = array (
					'name'		=> $taxonomy,
					'type'		=> 'taxonomy',
					'enabled'	=> 0
				);
				$option_changed = true;
			}
		}

		if ( $option_changed ) {
			$cstmsrch_options['output_order'] = array_values( $cstmsrch_options['output_order'] );
			update_option( 'cstmsrch_options', $cstmsrch_options );
		}
		cstmsrch_search_objects();
	}
}

/**
 * Change WP_Query for querying only necessary post types in search query
 * @param    object  $query   WP_Query object
 * @return   object  $query   WP_Query object
 */
if ( ! function_exists( 'cstmsrch_searchfilter' ) ) {
	function cstmsrch_searchfilter( $query ) {
		global $cstmsrch_is_registered, $cstmsrch_post_types_enabled;

		if ( empty( $cstmsrch_is_registered ) )
			register_cstmsrch_settings();

		if ( $query->is_search && ! empty( $query->query['s'] ) && ! is_admin() && ! empty( $cstmsrch_post_types_enabled ) ) {
			$query->set( 'post_type', $cstmsrch_post_types_enabled );
		}
		return $query;
	}
}

/**
 * Changing SQL-join query for adding taxonomies to search query
 * @param    string  $join   SQL-join clause
 * @return   string  $join   SQL-join clause with necessary changes
 */
if ( ! function_exists( 'cstmsrch_posts_join' ) ) {
	function cstmsrch_posts_join( $join ) {
		if ( is_search() ) {
			global $wpdb;

			$join .= " LEFT JOIN {$wpdb->term_relationships} tr ON {$wpdb->posts}.ID = tr.object_id LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id LEFT JOIN {$wpdb->terms} t ON t.term_id = tt.term_id ";
		}
		return $join;
	}
}

if ( ! function_exists( 'cstmsrch_posts_where_tax' ) ) {
	function cstmsrch_posts_where_tax( $where ) {
		if ( is_search() ) {
			global $cstmsrch_is_registered, $wpdb, $cstmsrch_post_types_enabled, $cstmsrch_taxonomies_enabled;

			if ( ! $cstmsrch_is_registered )
				register_cstmsrch_settings();

			$taxonomies = array();
			$where_post_types = $where_tax = "";

			foreach ( $cstmsrch_taxonomies_enabled as $taxonomy ) {
				$taxonomies[] = "'" . esc_sql( $taxonomy ) . "'";
			}
			if ( ! empty( $_REQUEST['cstmsrch_post_type'] ) && in_array( $_REQUEST['cstmsrch_post_type'], $cstmsrch_post_types_enabled ) ) {
				$where_post_types = " {$wpdb->posts}.post_type = '" . esc_sql( $_REQUEST['cstmsrch_post_type'] ) . "' AND";
			}
			if ( ! empty( $taxonomies ) ) {
				$taxonomies = implode( ',', $taxonomies );
				$where_tax = " t.name LIKE '%" . esc_sql( get_search_query() ) . "%' AND tt.taxonomy IN ($taxonomies) AND";
			}
			if ( ! empty( $where_tax ) ) {
				$where .= " OR ( $where_post_types $where_tax {$wpdb->posts}.post_status = 'publish' )";
			}
		}
		return $where;
	}
}

if ( ! function_exists( 'cstmsrch_posts_groupby' ) ) {
	function cstmsrch_posts_groupby( $groupby ) {
		if ( is_search() ) {
			global $wpdb;

			/* group on post ID */
			$groupby_id = "{$wpdb->posts}.ID";
			if ( ! is_search() || strpos( $groupby, $groupby_id ) !== false ) return $groupby;
			/* if groupby was empty, using ours */
			if ( ! strlen( trim( $groupby ) ) ) return $groupby_id;
			/* if groupby wasn't empty, append ours */
			return $groupby . ", " . $groupby_id;
		}
		return $groupby;
	}
}

/* Function is forming page of the settings of this plugin */
if ( ! function_exists( 'cstmsrch_settings_page' ) ) {
	function cstmsrch_settings_page() {
		global $wpdb, $cstmsrch_options, $cstmsrch_plugin_info, $wp_version, $cstmsrch_options_default, $cstmsrch_post_types_enabled, $cstmsrch_taxonomies_enabled;

		register_cstmsrch_settings();

		$message = $error 			= '';
		$plugin_basename 			= plugin_basename( __FILE__ );


		if ( isset( $_POST['bws_restore_confirm'] ) && check_admin_referer( $plugin_basename, 'bws_settings_nonce_name' ) ) {
			$cstmsrch_options = $cstmsrch_options_default;
			update_option( 'cstmsrch_options', $cstmsrch_options );
			$message =  __( 'All plugin settings were restored.', 'custom-search-plugin' );
			cstmsrch_update_option(true);
		}

		$search_objects_custom['post_type']		= get_post_types( array( 'public' => true ), 'objects' );
		$search_objects_custom['taxonomy']		= get_taxonomies( array( 'public' => true ), 'objects' );
		unset( $search_objects_custom['post_type']['attachment'] );
		unset( $search_objects_custom['taxonomy']['post_format'] );
		$taxonomies_keys 						= array_keys( $search_objects_custom['taxonomy'] );
		$post_types_custom_keys					= ( ! empty( $search_objects_custom ) ) ? array_combine( array_keys( $search_objects_custom['post_type'] ), array_keys( $search_objects_custom['post_type'] ) ) : array();
		unset( $post_types_custom_keys['post'], $post_types_custom_keys['page'] );

		$object_types = array(
			'post_type'	=> array(
				'singular'	=> __( 'post type', 'custom-search-plugin' ),
				'plural'	=> __( 'post types', 'custom-search-plugin' )
			),
			'taxonomy'	=> array(
				'singular'	=> __( 'taxonomy', 'custom-search-plugin' ),
				'plural'	=> __( 'taxonomies', 'custom-search-plugin' )
			)
		);

		$post_types_custom	= ( ! empty( $post_types_custom_keys ) ) ? array_combine( $post_types_custom_keys, $post_types_custom_keys ) : array();
		$taxonomies_global	= array_combine( $taxonomies_keys, $taxonomies_keys );
		$post_types_global	= get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types_global['attachment'] );

		if ( isset( $_REQUEST['cstmsrch_submit'] ) && check_admin_referer( $plugin_basename, 'cstmsrch_nonce_name' ) ) {

			if ( isset( $_POST['bws_hide_premium_options'] ) ) {
				$hide_result = bws_hide_premium_options( $cstmsrch_options );
				$cstmsrch_options = $hide_result['options'];
			}

			$cstmsrch_post_types_enabled = array( 'post', 'page' );
			if ( ! empty( $_REQUEST['cstmsrch_post_types'] ) && is_array( $_REQUEST['cstmsrch_post_types'] ) ) {
				foreach ( $_REQUEST['cstmsrch_post_types'] as $post_type ) {
					if ( in_array( $post_type, $post_types_custom ) ) {
						$cstmsrch_post_types_enabled[] = $post_type;
					}
				}
			}

			$cstmsrch_taxonomies_enabled = array();
			if ( ! empty( $_REQUEST['cstmsrch_taxonomies'] ) && is_array( $_REQUEST['cstmsrch_taxonomies'] ) ) {
				foreach ( $_REQUEST['cstmsrch_taxonomies'] as $taxonomy ) {
					if ( in_array( $taxonomy, $taxonomies_global ) ) {
						$cstmsrch_taxonomies_enabled[] = $taxonomy;
					}
				}
			}

			$output_order = array();
			foreach ( $post_types_global as $post_type ) {
				$enabled = ( in_array( $post_type, $cstmsrch_post_types_enabled ) ) ? 1 : 0;
				$output_order[] = array(
					'name'		=> $post_type,
					'type'		=> 'post_type',
					'enabled'	=> $enabled
				);
			}
			foreach ( $taxonomies_global as $taxonomy ) {
				$enabled = ( in_array( $taxonomy, $cstmsrch_taxonomies_enabled ) ) ? 1 : 0;
				$output_order[] = array(
					'name'		=> $taxonomy,
					'type'		=> 'taxonomy',
					'enabled'	=> $enabled
				);
			}

			$cstmsrch_options['output_order'] = $output_order;
			update_option( 'cstmsrch_options', $cstmsrch_options );
			$message = __( 'Settings saved' , 'custom-search-plugin' );
		} else {
			cstmsrch_update_option();
		}

		$bws_hide_premium_options_check = bws_hide_premium_options_check( $cstmsrch_options );

		/* GO PRO */
		if ( isset( $_GET['action'] ) && 'go_pro' == $_GET['action'] ) {
			$go_pro_result = bws_go_pro_tab_check( $plugin_basename, 'cstmsrch_options' );
			if ( ! empty( $go_pro_result['error'] ) )
				$error = $go_pro_result['error'];
			elseif ( ! empty( $go_pro_result['message'] ) )
				$message = $go_pro_result['message'];
		} ?>
		<div class="wrap">
			<h1><?php _e( 'Custom Search Settings', 'custom-search-plugin' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a class="nav-tab<?php echo ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], array( 'appearance', 'go_pro' ) ) ) ? ' nav-tab-active': ''; ?>" href="admin.php?page=custom_search.php"><?php _e( 'Settings', 'custom-search-plugin' ); ?></a>
				<a class="nav-tab<?php if ( isset( $_GET['action'] ) && 'appearance' == $_GET['action'] ) echo ' nav-tab-active'; ?>" href="admin.php?page=custom_search.php&amp;action=appearance"><?php _e( 'Appearance', 'custom-search-plugin' ); ?></a>
				<a class="nav-tab bws_go_pro_tab<?php if ( isset( $_GET['action'] ) && 'go_pro' == $_GET['action'] ) echo ' nav-tab-active'; ?>" href="admin.php?page=custom_search.php&amp;action=go_pro"><?php _e( 'Go PRO', 'custom-search-plugin' ); ?></a>
			</h2>
			<div class="updated fade below-h2" <?php if ( empty( $message ) ) echo "style=\"display:none\""; ?>><p><strong><?php echo $message; ?></strong></p></div>
			<div class="error below-h2" <?php if ( "" == $error ) echo 'style="display:none"'; ?>><p><strong><?php echo $error; ?></strong></p></div>
			<?php bws_show_settings_notice();
			if ( ! empty( $hide_result['message'] ) ) { ?>
				<div class="updated fade below-h2"><p><strong><?php echo $hide_result['message']; ?></strong></p></div>
			<?php }
			if ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], array( 'appearance', 'go_pro' ) ) ) {
				if ( isset( $_POST['bws_restore_default'] ) && check_admin_referer( $plugin_basename, 'bws_settings_nonce_name' ) ) {
					bws_form_restore_default_confirm( $plugin_basename );
				} else { ?>
					<?php $post_types_select_all = $taxonomies_select_all = '';
					if ( count( $post_types_custom ) == count( $cstmsrch_post_types_enabled ) - 2 )
						$post_types_select_all = 'checked="checked"';
					if ( count( $taxonomies_global) == count( $cstmsrch_taxonomies_enabled ) )
						$taxonomies_select_all = 'checked="checked"'; ?>
					<form method="post" action="" style="margin-top: 10px;" id="cstmsrch_settings_form" class="bws_form">
						<table class="form-table">
							<tr valign="top">
								<th style="padding-top:10px;" scope="row"><?php _e( 'Enable Custom search for:', 'custom-search-plugin' ); ?></th>
								<td style="padding-top:10px;">
									<div id="cstmsrch-post-types-settings" class="cstmsrch-checkbox-section" style="margin-bottom:20px;">
										<?php if ( 0 < count( $post_types_custom ) ) { ?>
											<fieldset>
												<div class="cstmsrch_select_all_block" style="margin-bottom:10px;">
													<label>
														<input type="checkbox" <?php echo $post_types_select_all; ?> style="display:none;" class="cstmsrch_cb_select_all" /><span style="text-transform: capitalize; padding-left: 5px;"><strong><?php _e( 'post types', 'custom-search-plugin' ); ?></strong></span>
													</label>
												</div>
												<?php foreach ( $post_types_custom as $post_type ) {
													$current_object = $search_objects_custom['post_type'][ $post_type ];
													$label = $current_object->labels->name; ?>
													<label>
														<input type="checkbox" <?php echo ( in_array( $post_type, $cstmsrch_post_types_enabled ) ?  'checked="checked"' : "" ); ?> name="cstmsrch_post_types[]" class="cstmsrch_cb_select" value="<?php echo $post_type; ?>"/>
														<span style="text-transform: capitalize; padding-left: 5px;"><?php echo $label; ?></span>
													</label><br />
												<?php } ?>
											</fieldset>
										<?php } else { ?>
											<div><?php _e( 'No custom post type found.', 'custom-search-plugin' ); ?></div>
										<?php } ?>
									</div><!-- #cstmsrch-post-types-settings -->
									<div id="cstmsrch-taxonomies-settings" class="cstmsrch-checkbox-section" style="margin-bottom:20px;">
										<?php if ( 0 < count( $taxonomies_global ) ) { ?>
											<fieldset>
												<div class="cstmsrch_select_all_block" style="margin-bottom:10px;">
													<label>
														<input type="checkbox" <?php echo $taxonomies_select_all; ?> style="display:none;" class="cstmsrch_cb_select_all"  />
														<span style="text-transform: capitalize; padding-left: 5px;"><strong><?php _e( 'taxonomies', 'custom-search-plugin' ); ?></strong></span>
													</label>
												</div>
												<?php foreach ( $taxonomies_global as $taxonomy ) {
													$current_object = $search_objects_custom['taxonomy'][ $taxonomy ];
													$object_type = $current_object->object_type[0];
													$object_type_name = ( ! empty( $search_objects_custom['post_type'][ $object_type ] ) ) ? $search_objects_custom['post_type'][ $object_type ]->labels->name : '';
													$label = $current_object->labels->name; ?>
													<label>
														<input type="checkbox" <?php echo ( in_array( $taxonomy, $cstmsrch_taxonomies_enabled ) ? 'checked="checked"' : "" ); ?> name="cstmsrch_taxonomies[]" class="cstmsrch_cb_select" value="<?php echo $taxonomy; ?>"/>
														<span style="text-transform: capitalize; padding-left: 5px;">
															<?php echo "$label (" . __( 'for', 'custom-search-plugin' ) . " \"$object_type_name\")"; ?>
														</span>
													</label><br />
												<?php } ?>
											</fieldset>
										<?php } ?>
									</div><!-- #cstmsrch-taxonomies-settings -->
								</td>
							</tr>
						</table>
						<?php if ( ! $bws_hide_premium_options_check ) { ?>
							<div class="bws_pro_version_bloc">
								<div class="bws_pro_version_table_bloc">
								<button type="submit" name="bws_hide_premium_options" class="notice-dismiss bws_hide_premium_options" title="<?php _e( 'Close', 'custom-search-plugin' ); ?>"></button>
									<div class="bws_table_bg"></div>
									<table class="form-table bws_pro_version">
										<tr valign="top">
											<th style="width: 190px !important; padding-top:10px;" scope="row"><?php _e( 'Enable Custom search for:', 'custom-search-plugin' ); ?></th>
											<td width="350" style="padding-top:10px;">
												<?php $objects = array(
													$search_objects_custom['post_type']['post'],
													$search_objects_custom['post_type']['page']
												);
												foreach ( $objects as $current_object ) { ?>
													<img title="" src="<?php echo plugins_url( 'images/dragging-arrow.png', __FILE__ ); ?>" alt="" />
													<label>
														<input type="checkbox" checked="checked" disabled="disabled" />
															<span>
																<?php echo $current_object->labels->name; ?>
															</span>
													</label><br />
												<?php } ?>
												<span class="bws_info"><?php _e( 'When you drag post types and taxonomies, you affect the order of their display in the frontend on the search page.', 'custom-search-plugin' ); ?></span>
											</td>
										</tr>
										<tr valign="top">
											<th style="width: 190px !important;" scope="row"><?php _e( 'Search only by type of the current post', 'custom-search-plugin' ); ?></th>
											<td width="350">
												<input type="checkbox" disabled="disabled" /><br />
												<span class="bws_info"><?php _e( 'This option is used when you search on a single page/post/post type.', 'custom-search-plugin' ); ?></span>
											</td>
										</tr>
									</table>
								</div>
								<div class="bws_pro_version_tooltip">
									<a class="bws_button" href="https://bestwebsoft.com/products/wordpress/plugins/custom-search/?k=f9558d294313c75b964f5f6fa1e5fd3c&pn=214&v=<?php echo $cstmsrch_plugin_info["Version"]; ?>&wp_v=<?php echo $wp_version; ?>" target="_blank" title="custom-search Pro"><?php _e( 'Learn More', 'custom-search-plugin' ); ?></a>
									<div class="clear"></div>
								</div>
							</div>
						<?php } ?>
						<p class="submit">
							<input type="hidden" name="cstmsrch_submit" value="submit" />
							<input type="submit" id="bws-submit-button" class="button-primary" value="<?php _e( 'Save Changes' , 'custom-search-plugin' ) ?>" />
							<?php wp_nonce_field( $plugin_basename, 'cstmsrch_nonce_name' ); ?>
						</p>
					</form>
					<?php bws_form_restore_default_settings( $plugin_basename );
				}
			} elseif ( 'appearance' == $_GET['action'] ) { ?>
				<div class="bws_pro_version_bloc" style="margin: 10px 0;">
					<div class="bws_pro_version_table_bloc">
						<div class="bws_table_bg"></div>
						<table class="form-table  bws_pro_version">
							<tr valign="top">
								<th scope="row"><?php _e( 'Change displaying of post content on search pages', 'custom-search-plugin' ); ?></th>
								<td><input type="checkbox" disabled="disabled" /></td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e( 'Display featured image with post content', 'custom-search-plugin' ); ?></th>
								<td><input type="checkbox" disabled="disabled" /></td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e( 'Featured image size', 'custom-search-plugin' ); ?></th>
								<td><select disabled="disabled"><option>thumbnail (150x150)</option></select></td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e( 'Featured image align', 'custom-search-plugin' ); ?></th>
								<td><fieldset>
									<label><input type="radio" disabled="disabled" /><?php _e( 'Left', 'custom-search-plugin' ); ?></label><br />
									<label><input type="radio" disabled="disabled" /><?php _e( 'Right', 'custom-search-plugin' ); ?></label><br />
								</fieldset></td>
							</tr>
							<tr valign="top">
								<th scope="row"><?php _e( 'Change excerpt length', 'custom-search-plugin' ); ?></th>
								<td><input type="checkbox" disabled="disabled" />&nbsp;<?php _e( 'to', 'custom-search-plugin' ); ?>&nbsp;<input class="small-text" type="number" value="10" disabled="disabled" />&nbsp;<span><?php _e( 'words', 'custom-search-plugin' ); ?></span></td>
							</tr>
						</table>
					</div>
					<div class="bws_pro_version_tooltip">
						<a class="bws_button" href="https://bestwebsoft.com/products/wordpress/plugins/custom-search/?k=f9558d294313c75b964f5f6fa1e5fd3c&pn=214&v=<?php echo $cstmsrch_plugin_info["Version"]; ?>&wp_v=<?php echo $wp_version; ?>" target="_blank" title="custom-search Pro"><?php _e( 'Learn More', 'custom-search-plugin' ); ?></a>
						<div class="clear"></div>
					</div>
				</div>
			<?php } elseif ( 'go_pro' == $_GET['action'] ) {
				bws_go_pro_tab_show( $bws_hide_premium_options_check, $cstmsrch_plugin_info, $plugin_basename, 'custom_search.php', 'custom_search_pro.php', 'custom-search-pro/custom-search-pro.php', 'custom-search-plugin', 'f9558d294313c75b964f5f6fa1e5fd3c', '214', isset( $go_pro_result['pro_plugin_is_activated'] ) );
			}
			bws_plugin_reviews_block( $cstmsrch_plugin_info['Name'], 'custom-search-plugin' ); ?>
		</div>
	<?php }
}

/* Positioning in the page. End. */
if ( !function_exists( 'cstmsrch_action_links' ) ) {
	function cstmsrch_action_links( $links, $file ) {
		if ( ! is_network_admin() ) {
			/* Static so we don't call plugin_basename on every plugin row. */
			static $this_plugin;
			if ( ! $this_plugin ) $this_plugin = plugin_basename( __FILE__ );

			if ( $file == $this_plugin ) {
				$settings_link = '<a href="admin.php?page=custom_search.php">' . __( 'Settings', 'custom-search-plugin' ) . '</a>';
				array_unshift( $links, $settings_link );
			}
		}
		return $links;
	}
} /* End function cstmsrch_action_links */

/* Function are using to create link 'settings' on admin page. */
if ( !function_exists( 'cstmsrch_links' ) ) {
	function cstmsrch_links( $links, $file ) {
		$base = plugin_basename( __FILE__ );
		if ( $file == $base ) {
			if ( ! is_network_admin() )
				$links[] = '<a href="admin.php?page=custom_search.php">' . __( 'Settings','custom-search-plugin' ) . '</a>';
			$links[] = '<a href="https://wordpress.org/plugins/custom-search-plugin/faq/" target="_blank">' . __( 'FAQ','custom-search-plugin' ) . '</a>';
			$links[] = '<a href="https://support.bestwebsoft.com">' . __( 'Support', 'custom-search-plugin' ) . '</a>';
		}
		return $links;
	}
}

if ( ! function_exists( 'cstmsrch_admin_js' ) ) {
	function cstmsrch_admin_js() {
		global $cstmsrch_plugin_info;
		if ( isset( $_REQUEST['page'] ) && 'custom_search.php' == $_REQUEST['page'] ) {
			wp_enqueue_script( 'cstmsrch_script', plugins_url( 'js/script.js', __FILE__ ), array(), $cstmsrch_plugin_info['Version'] );
			bws_enqueue_settings_scripts();
		}
		if ( isset( $_GET['action'] ) && 'custom_code' == $_GET['action'] )
				bws_plugins_include_codemirror();
	}
}

if ( ! function_exists ( 'cstmsrch_admin_notices' ) ) {
	function cstmsrch_admin_notices() {
		global $hook_suffix, $cstmsrch_plugin_info, $cstmsrch_options;

		if ( 'plugins.php' == $hook_suffix ) {
			/* Get options from the database */
			if ( ! $cstmsrch_options )
				$cstmsrch_options = get_option( 'cstmsrch_options' );
			if ( isset( $cstmsrch_options['first_install'] ) && strtotime( '-1 week' ) > $cstmsrch_options['first_install'] )
				bws_plugin_banner( $cstmsrch_plugin_info, 'cstmsrch', 'custom-search', '22f95b30aa812b6190a4a5a476b6b628', '214', '//ps.w.org/custom-search-plugin/assets/icon-128x128.png' );
			bws_plugin_banner_to_settings( $cstmsrch_plugin_info, 'cstmsrch_options', 'custom-search-plugin', 'admin.php?page=custom_search.php' );
		}

		if ( isset( $_REQUEST['page'] ) && 'custom_search.php' == $_REQUEST['page'] ) {
			bws_plugin_suggest_feature_banner( $cstmsrch_plugin_info, 'cstmsrch_options', 'custom-search-plugin' );
		}
	}
}

/* add help tab  */
if ( ! function_exists( 'cstmsrch_add_tabs' ) ) {
	function cstmsrch_add_tabs() {
		$screen = get_current_screen();
		$args = array(
			'id' 			=> 'cstmsrch',
			'section' 		=> '200538949'
		);
		bws_help_tab( $screen, $args );
	}
}

/* Function for delete options from table `wp_options` */
if ( ! function_exists( 'delete_cstmsrch_settings' ) ) {
	function delete_cstmsrch_settings() {
		if ( ! function_exists( 'get_plugins' ) )
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$all_plugins = get_plugins();

		if ( ! array_key_exists( 'custom-search-pro/custom-search-pro.php', $all_plugins ) ) {

			if ( is_multisite() ) {
				global $wpdb;
				/* Get all blog ids */
				$blogids = $wpdb->get_col( "SELECT `blog_id` FROM $wpdb->blogs" );
				$old_blog = $wpdb->blogid;
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );
					delete_option( "cstmsrch_options" );
				}
				switch_to_blog( $old_blog );
			} else {
				delete_option( 'cstmsrch_options' );
			}
		}

		require_once( dirname( __FILE__ ) . '/bws_menu/bws_include.php' );
		bws_include_init( plugin_basename( __FILE__ ) );
		bws_delete_plugin( plugin_basename( __FILE__ ) );
	}
}

register_activation_hook( __FILE__, 'cstmsrch_plugin_activate');
add_action( 'plugins_loaded', 'cstmsrch_plugins_loaded' );
add_action( 'admin_menu', 'add_cstmsrch_admin_menu' );
add_action( 'init', 'cstmsrch_init' );
add_action( 'admin_init', 'cstmsrch_admin_init' );
add_action( 'admin_enqueue_scripts', 'cstmsrch_admin_js' );

add_filter( 'pre_get_posts', 'cstmsrch_searchfilter' );
add_filter( 'posts_join', 'cstmsrch_posts_join' );
add_filter( 'posts_groupby', 'cstmsrch_posts_groupby' );
add_filter( 'posts_where','cstmsrch_posts_where_tax' );

/* Adds "Settings" link to the plugin action page */
add_filter( 'plugin_action_links', 'cstmsrch_action_links', 10, 2 );
/* Additional links on the plugin page */
add_filter( 'plugin_row_meta', 'cstmsrch_links', 10, 2 );
add_action( 'admin_notices', 'cstmsrch_admin_notices' );