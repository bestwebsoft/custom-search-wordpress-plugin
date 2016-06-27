<?php
/*
Plugin Name: Custom Search by BestWebSoft
Plugin URI: http://bestwebsoft.com/products/
Description: Custom Search Plugin designed to search for site custom types.
Author: BestWebSoft
Text Domain: custom-search-plugin
Domain Path: /languages
Version: 1.33
Author URI: http://bestwebsoft.com/
License: GPLv2 or later
*/
 
/*  © Copyright 2016  BestWebSoft  ( http://support.bestwebsoft.com )

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
		$settings = add_submenu_page( 'bws_panel', __( 'Custom Search Settings', 'custom-search-plugin' ), 'Custom search', 'manage_options', "custom_search.php", 'cstmsrch_settings_page' );
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
		bws_wp_min_version_check( plugin_basename( __FILE__ ), $cstmsrch_plugin_info, '3.8', '3.1' );

		/* Call register settings function */
		if ( ! is_admin() || ( isset( $_GET['page'] ) && "custom_search.php" == $_GET['page'] ) )
			register_cstmsrch_settings();
	}
}

if ( ! function_exists( 'cstmsrch_admin_init' ) ) {
	function cstmsrch_admin_init() {
		global $bws_plugin_info, $cstmsrch_plugin_info, $cstmsrch_options;
		if ( ! isset( $bws_plugin_info ) || empty( $bws_plugin_info ) )			
			$bws_plugin_info = array( 'id' => '81', 'version' => $cstmsrch_plugin_info["Version"] );		
	}
}

/* Function create column in table wp_options for option of this plugin. If this column exists - save value in variable. */
if ( ! function_exists( 'register_cstmsrch_settings' ) ) {
	function register_cstmsrch_settings() {
		global $cstmsrch_options, $bws_plugin_info, $cstmsrch_plugin_info, $cstmsrch_options_default;

		$cstmsrch_options_default = array(
			'plugin_option_version'		=>	$cstmsrch_plugin_info["Version"],
			'post_types'				=>	array(),
			'first_install'				=>	strtotime( "now" ),
			'display_settings_notice'	=>	1,
			'suggest_feature_banner'	=>	1,
		);

		/* Install the option defaults */
		if ( false !== get_option( 'bws_custom_search' ) ) {
			$cstmsrch_options_default = get_option( 'bws_custom_search' );
			delete_option( 'bws_custom_search' );
		}
		if ( ! get_option( 'cstmsrch_options' ) )
			add_option( 'cstmsrch_options', $cstmsrch_options_default );

		$cstmsrch_options = get_option( 'cstmsrch_options' );

		/* Array merge incase this version has added new options */
		if ( ! isset( $cstmsrch_options['plugin_option_version'] ) || $cstmsrch_options['plugin_option_version'] != $cstmsrch_plugin_info["Version"] ) {
			if ( ! isset( $cstmsrch_options['post_types'] ) ) {
				unset( $cstmsrch_options['plugin_option_version'] );
				$cstmsrch_options_default['post_types'] = $cstmsrch_options;
				$cstmsrch_options = array();
			}
			$cstmsrch_options_default['display_settings_notice'] = 0;
			$cstmsrch_options = array_merge( $cstmsrch_options_default, $cstmsrch_options );
			$cstmsrch_options['plugin_option_version'] = $cstmsrch_plugin_info["Version"];
			/* show pro features */
			$cstmsrch_options['hide_premium_options'] = array();
			update_option( 'cstmsrch_options', $cstmsrch_options );
		}
	}
}

if ( ! function_exists( 'cstmsrch_searchfilter' ) ) {
	function cstmsrch_searchfilter( $query ) {
		global $cstmsrch_options;
		if ( empty( $cstmsrch_options ) )
			$cstmsrch_options = get_option( 'cstmsrch_options' );

		if ( $query->is_search && ! empty( $query->query['s'] ) && ! is_admin() ) {
			$cstmsrch_post_standart_types	=	array( 'post', 'page', 'attachment' );
			$cstmsrch_result_merge			=	array_merge( $cstmsrch_post_standart_types, $cstmsrch_options['post_types'] );
			$query->set( 'post_type', $cstmsrch_result_merge );
		}
		return $query;
	}
}

/* Function is forming page of the settings of this plugin */
if ( ! function_exists( 'cstmsrch_settings_page' ) ) {
	function cstmsrch_settings_page() {
		global $wpdb, $cstmsrch_options, $cstmsrch_plugin_info, $wp_version, $cstmsrch_options_default;

		$message = $error = '';
		$plugin_basename  = plugin_basename( __FILE__ );
		$args             = array( '_builtin' => false );
		$cstmsrch_result  = get_post_types( $args );
		if ( isset( $_REQUEST['cstmsrch_submit'] ) && check_admin_referer( $plugin_basename, 'cstmsrch_nonce_name' ) ) {

			if ( isset( $_POST['bws_hide_premium_options'] ) ) {
				$hide_result = bws_hide_premium_options( $cstmsrch_options );
				$cstmsrch_options = $hide_result['options'];
			}

			$cstmsrch_options['post_types'] = isset( $_REQUEST['cstmsrch_options'] ) ? $_REQUEST['cstmsrch_options'] : array();
			update_option( 'cstmsrch_options', $cstmsrch_options );
			$message = __( "Settings saved" , 'custom-search-plugin' );
		} else {
			$args = array( '_builtin' => false );
			$cstmsrch_result = get_post_types( $args );
			if ( empty( $cstmsrch_result ) ) {
				$cstmsrch_options['post_types'] = array();
				update_option( 'cstmsrch_options', $cstmsrch_options );
			}
		}

		$bws_hide_premium_options_check = bws_hide_premium_options_check( $cstmsrch_options );

		if ( isset( $_POST['bws_restore_confirm'] ) && check_admin_referer( $plugin_basename, 'bws_settings_nonce_name' ) ) {
			$cstmsrch_options = $cstmsrch_options_default;
			update_option( 'cstmsrch_options', $cstmsrch_options );
			$message =  __( 'All plugin settings were restored.', 'custom-search-plugin' );
		}
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
				<a class="nav-tab<?php echo ! isset( $_GET['action'] ) ? ' nav-tab-active': ''; ?>" href="admin.php?page=custom_search.php"><?php _e( 'Settings', 'custom-search-plugin' ); ?></a>
				<a class="nav-tab<?php if ( isset( $_GET['action'] ) && 'appearance' == $_GET['action'] ) echo ' nav-tab-active'; ?>" href="admin.php?page=custom_search.php&amp;action=appearance"><?php _e( 'Appearance', 'custom-search-plugin' ); ?></a>
				<a class="nav-tab bws_go_pro_tab<?php if ( isset( $_GET['action'] ) && 'go_pro' == $_GET['action'] ) echo ' nav-tab-active'; ?>" href="admin.php?page=custom_search.php&amp;action=go_pro"><?php _e( 'Go PRO', 'custom-search-plugin' ); ?></a>
			</h2>
			<div class="updated fade below-h2" <?php if ( empty( $message ) ) echo "style=\"display:none\""; ?>><p><strong><?php echo $message; ?></strong></p></div>
			<div class="error below-h2" <?php if ( "" == $error ) echo 'style="display:none"'; ?>><p><strong><?php echo $error; ?></strong></p></div>
			<?php bws_show_settings_notice();
			if ( ! empty( $hide_result['message'] ) ) { ?>
				<div class="updated fade below-h2"><p><strong><?php echo $hide_result['message']; ?></strong></p></div>
			<?php }
			if ( ! isset( $_GET['action'] ) ) {
				if ( isset( $_POST['bws_restore_default'] ) && check_admin_referer( $plugin_basename, 'bws_settings_nonce_name' ) ) {
					bws_form_restore_default_confirm( $plugin_basename );
				} else {
					if ( 0 < count( $cstmsrch_result ) ) { ?>
						<form method="post" action="" style="margin-top: 10px;" id="cstmsrch_settings_form" class="bws_form">
							<table class="form-table">
								<tr valign="top">
									<th scope="row"><?php _e( 'Enable Custom search for:', 'custom-search-plugin' ); ?></th>
									<td>
										<?php $cstmsrch_new_result = array_values( $cstmsrch_result );
										 	$cstmsrch_select_all = '';
											if ( ! array_diff( $cstmsrch_new_result, $cstmsrch_options['post_types'] ) )
												$cstmsrch_select_all = 'checked="checked"'; ?>
										<div id="cstmsrch_div_select_all" style="display:none;"><label><input id="cstmsrch_select_all" type="checkbox" <?php echo $cstmsrch_select_all; ?> /><span style="text-transform: capitalize; padding-left: 5px;"><strong><?php _e( 'All', 'custom-search-plugin' ); ?></strong></span></label></div>
										<?php foreach ( $cstmsrch_result as $value ) { ?>
											<label><input type="checkbox" <?php echo ( in_array( $value, $cstmsrch_options['post_types'] ) ?  'checked="checked"' : "" ); ?> name="cstmsrch_options[]" value="<?php echo $value; ?>"/><span style="text-transform: capitalize; padding-left: 5px;"><?php echo $value; ?></span></label><br />
										<?php } ?>
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
												<th style="width: 190px !important;" scope="row"><?php _e( 'Enable Custom search for:', 'custom-search-plugin' ); ?></th>
												<td width="350">
													<img title="" src="<?php echo plugins_url( 'images/dragging-arrow.png', __FILE__ ); ?>" alt="" />
													<label><input type="checkbox" checked="checked" name="cstmsrchpr_options[]" value="post" disabled="disabled" />&nbsp;<span><?php _e( 'Post', 'custom-search-plugin' ); ?></span></label><br />
													<img title="" src="<?php echo plugins_url( 'images/dragging-arrow.png', __FILE__ ); ?>" alt="" />
													<label><input type="checkbox" checked="checked" name="cstmsrchpr_options[]" value="page" disabled="disabled" />&nbsp;<span><?php _e( 'Page', 'custom-search-plugin' ); ?></span></label><br />
													<span class="bws_info"><?php _e( 'When you drag post types, you affect the order of their display in the frontend on the search page.', 'custom-search-plugin' ); ?></span>
												</td>
											</tr>
											<tr valign="top">
												<th style="width: 190px !important;" scope="row"><?php _e( 'Search only by type of the current post', 'custom-search-plugin' ); ?></th>
												<td width="350">
													<input type="checkbox" value="1" name="by_current_post_type" disabled="disabled" /><br />
													<span class="bws_info"><?php _e( 'This option is used when you search on a single page/post/post type.', 'custom-search-plugin' ); ?></span>	
												</td>
											</tr>
										</table>
									</div>
									<div class="bws_pro_version_tooltip">
										<div class="bws_info">
											<?php _e( 'Unlock premium options by upgrading to Pro version', 'custom-search-plugin' ); ?>
										</div>
										<a class="bws_button" href="http://bestwebsoft.com/products/custom-search/?k=f9558d294313c75b964f5f6fa1e5fd3c&pn=214&v=<?php echo $cstmsrch_plugin_info["Version"]; ?>&wp_v=<?php echo $wp_version; ?>" target="_blank" title="custom-search Pro"><?php _e( 'Learn More', 'custom-search-plugin' ); ?></a>
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
					} else { ?>
						<p><?php _e( 'No custom post type found.', 'custom-search-plugin' ); ?></p>
					<?php }
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
						<div class="bws_info">
							<?php _e( 'Unlock premium options by upgrading to Pro version', 'custom-search-plugin' ); ?>
						</div>
						<a class="bws_button" href="http://bestwebsoft.com/products/custom-search/?k=f9558d294313c75b964f5f6fa1e5fd3c&pn=214&v=<?php echo $cstmsrch_plugin_info["Version"]; ?>&wp_v=<?php echo $wp_version; ?>" target="_blank" title="custom-search Pro"><?php _e( 'Learn More', 'custom-search-plugin' ); ?></a>
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
				$links[]	=	'<a href="admin.php?page=custom_search.php">' . __( 'Settings','custom-search-plugin' ) . '</a>';
			$links[]	=	'<a href="http://wordpress.org/plugins/custom-search-plugin/faq/" target="_blank">' . __( 'FAQ','custom-search-plugin' ) . '</a>';
			$links[]	=	'<a href="http://support.bestwebsoft.com">' . __( 'Support', 'custom-search-plugin' ) . '</a>';
		}
		return $links;
	}
}

if ( ! function_exists( 'cstmsrch_admin_js' ) ) {
	function cstmsrch_admin_js() {
		if ( isset( $_REQUEST['page'] ) && 'custom_search.php' == $_REQUEST['page'] )
			wp_enqueue_script( 'cstmsrch_script', plugins_url( 'js/script.js', __FILE__ ) );
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
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			global $wpdb;
			/* Get all blog ids */
			$blogids = $wpdb->get_col( "SELECT `blog_id` FROM $wpdb->blogs" );
			$old_blog = $wpdb->blogid;
			foreach ( $blogids as $blog_id ) {
				switch_to_blog( $blog_id );
				delete_option( "cstmsrch_options" );
			}
			switch_to_blog( $old_blog );
		}
		delete_option( 'cstmsrch_options' );

		require_once( dirname( __FILE__ ) . '/bws_menu/bws_include.php' );
		bws_include_init( plugin_basename( __FILE__ ) );
		bws_delete_plugin( plugin_basename( __FILE__ ) );
	}
}

register_activation_hook( __FILE__, 'register_cstmsrch_settings');
add_action( 'plugins_loaded', 'cstmsrch_plugins_loaded' );
add_action( 'admin_menu', 'add_cstmsrch_admin_menu' );
add_action( 'init', 'cstmsrch_init' );
add_action( 'admin_init', 'cstmsrch_admin_init' );
add_action( 'admin_enqueue_scripts', 'cstmsrch_admin_js' );

add_filter( 'pre_get_posts', 'cstmsrch_searchfilter' );
/* Adds "Settings" link to the plugin action page */
add_filter( 'plugin_action_links', 'cstmsrch_action_links', 10, 2 );
/* Additional links on the plugin page */
add_filter( 'plugin_row_meta', 'cstmsrch_links', 10, 2 );
add_action( 'admin_notices', 'cstmsrch_admin_notices');
register_uninstall_hook( __FILE__, 'delete_cstmsrch_settings');