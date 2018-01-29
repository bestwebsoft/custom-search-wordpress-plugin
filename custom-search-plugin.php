<?php
/*
Plugin Name: Custom Search by BestWebSoft
Plugin URI: https://bestwebsoft.com/products/wordpress/plugins/custom-search/
Description: Add custom post types to WordPress website search results.
Author: BestWebSoft
Text Domain: custom-search-plugin
Domain Path: /languages
Version: 1.39
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
		global $submenu, $wp_version, $cstmsrch_plugin_info;

		$settings = add_menu_page( __( 'Custom Search Settings', 'custom-search-plugin' ), 'Custom Search', 'manage_options', 'custom_search.php', 'cstmsrch_settings_page', 'none' );

		add_submenu_page( 'custom_search.php', __( 'Custom Search Settings', 'custom-search-plugin' ), __( 'Settings', 'custom-search-plugin'), 'manage_options', 'custom_search.php', 'cstmsrch_settings_page' );

		add_submenu_page( 'custom_search.php', 'BWS Panel', 'BWS Panel', 'manage_options', 'cstmsrch-bws-panel', 'bws_add_menu_render' );

		if ( isset( $submenu['custom_search.php'] ) ) {
			$submenu['custom_search.php'][] = array(
				'<span style="color:#d86463"> ' . __( 'Upgrade to Pro', 'custom-search-plugin' ) . '</span>',
				'manage_options',
				'https://bestwebsoft.com/products/wordpress/plugins/custom-search/?k=f9558d294313c75b964f5f6fa1e5fd3c&pn=214&v=' . $cstmsrch_plugin_info["Version"] . '&wp_v=' . $wp_version );
		}
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
		global $cstmsrch_plugin_info;

		require_once( dirname( __FILE__ ) . '/bws_menu/bws_include.php' );
		bws_include_init( plugin_basename( __FILE__ ) );

		$is_admin = ( is_admin() && ! ( defined( 'DOING_AJAX' ) && ! isset( $_REQUEST['pagenow'] ) ) );
		if ( ! $is_admin ) {
			add_filter( 'pre_get_posts', 'cstmsrch_searchfilter' );
			add_filter( 'posts_join', 'cstmsrch_posts_join' );
			add_filter( 'posts_groupby', 'cstmsrch_posts_groupby' );
			add_filter( 'posts_where','cstmsrch_posts_where_tax' );
		}

		if ( empty( $cstmsrch_plugin_info ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			$cstmsrch_plugin_info = get_plugin_data( __FILE__ );
		}

		if ( ! is_admin() || ( isset( $_GET['page'] ) && 'custom_search.php' == $_GET['page'] ) ) {
			register_cstmsrch_settings();
		}
		/* Function check if plugin is compatible with current WP version */
		bws_wp_min_version_check( plugin_basename( __FILE__ ), $cstmsrch_plugin_info, '3.9' );
	}
}

if ( ! function_exists( 'cstmsrch_admin_init' ) ) {
	function cstmsrch_admin_init() {
		global $bws_plugin_info, $cstmsrch_plugin_info;
		if ( empty( $bws_plugin_info ) ) {
			$bws_plugin_info = array( 'id' => '81', 'version' => $cstmsrch_plugin_info['Version'] );
		}
	}
}

if ( ! function_exists( 'cstmsrch_default_options' ) ) {
	function cstmsrch_default_options() {
		global $cstmsrch_plugin_info;

		$cstmsrch_options_default = array(
			'plugin_option_version'		=> $cstmsrch_plugin_info['Version'],
			'output_order'				=> array(
											array( 'name' => 'post', 'type' => 'post_type', 'enabled' => 1 ),
											array( 'name' => 'page', 'type' => 'post_type', 'enabled' => 1 ),
										),
			'first_install'				=> strtotime( "now" ),
			'display_settings_notice'	=> 1,
			'suggest_feature_banner'	=> 1,
		);

		return $cstmsrch_options_default;
	}
}

/* Function create column in table wp_options for option of this plugin. If this column exists - save value in variable. */
if ( ! function_exists( 'register_cstmsrch_settings' ) ) {
	function register_cstmsrch_settings() {
		global $cstmsrch_options, $bws_plugin_info, $cstmsrch_plugin_info, $cstmsrch_is_registered;

		$cstmsrch_is_registered = true;
		$cstmsrch_options_default = cstmsrch_default_options();

		/* Install the option defaults */
		if ( ! get_option( 'cstmsrch_options' ) ) {
			add_option( 'cstmsrch_options', $cstmsrch_options_default );
		}

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
							if ( ! isset( $cstmsrch_options[ $key ][ $key2 ] ) ) {
								$cstmsrch_options[ $key ][ $key2 ] = $cstmsrch_options_default[ $key ][ $key2 ];
							}
						}
					}
				}
			}

			$cstmsrch_options['plugin_option_version'] = $cstmsrch_plugin_info['Version'];
			/* show pro features */
			$cstmsrch_options['hide_premium_options'] = array();
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
		if ( empty( $cstmsrch_options ) ) {
			$cstmsrch_options = get_option( 'cstmsrch_options' );
		}
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
 * Change WP_Query for querying only necessary post types in search query
 * @param    object  $query   WP_Query object
 * @return   object  $query   WP_Query object
 */
if ( ! function_exists( 'cstmsrch_searchfilter' ) ) {
	function cstmsrch_searchfilter( $query ) {
		global $cstmsrch_is_registered, $cstmsrch_post_types_enabled;

		if ( empty( $cstmsrch_is_registered ) ) {
			register_cstmsrch_settings();
		}
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

			if ( ! $cstmsrch_is_registered ) {
				register_cstmsrch_settings();
			}

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
			if ( ! is_search() || false !== strpos( $groupby, $groupby_id ) ) {
				return $groupby;
			}
			/* if groupby was empty, using ours */
			if ( ! strlen( trim( $groupby ) ) ) {
				return $groupby_id;
			}
			/* if groupby wasn't empty, append ours */
			return $groupby . ", " . $groupby_id;
		}
		return $groupby;
	}
}

/* Display settings page */
if ( ! function_exists( 'cstmsrch_settings_page' ) ) {
	function cstmsrch_settings_page() {
		require_once( dirname( __FILE__ ) . '/includes/class-cstmsrch-settings.php' );
		$page = new Cstmsrch_Settings_Tabs( plugin_basename( __FILE__ ) ); ?>
		<div class="wrap">
			<h1><?php _e( 'Custom Search Settings', 'custom-search-plugin' ); ?></h1>
			<?php $page->display_content(); ?>
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
			if ( ! is_network_admin() ) {
				$links[] = '<a href="admin.php?page=custom_search.php">' . __( 'Settings','custom-search-plugin' ) . '</a>';
			}
			$links[] = '<a href="https://wordpress.org/plugins/custom-search-plugin/faq/" target="_blank">' . __( 'FAQ','custom-search-plugin' ) . '</a>';
			$links[] = '<a href="https://support.bestwebsoft.com">' . __( 'Support', 'custom-search-plugin' ) . '</a>';
		}
		return $links;
	}
}

if ( ! function_exists( 'cstmsrch_admin_js' ) ) {
	function cstmsrch_admin_js() {
		global $cstmsrch_plugin_info;
		wp_enqueue_style( 'cstmsrch_admin_page_stylesheet', plugins_url( 'css/admin_page.css', __FILE__ ) );
		if ( isset( $_REQUEST['page'] ) && 'custom_search.php' == $_REQUEST['page'] ) {
			wp_enqueue_script( 'cstmsrch_script', plugins_url( 'js/script.js', __FILE__ ), array(), $cstmsrch_plugin_info['Version'] );
			wp_enqueue_style( 'cstmsrch_stylesheet', plugins_url( 'css/style.css', __FILE__ ) );
			bws_enqueue_settings_scripts();
			bws_plugins_include_codemirror();
		}
	}
}

if ( ! function_exists ( 'cstmsrch_admin_notices' ) ) {
	function cstmsrch_admin_notices() {
		global $hook_suffix, $cstmsrch_plugin_info, $cstmsrch_options;

		if ( 'plugins.php' == $hook_suffix ) {
			/* Get options from the database */
			if ( ! $cstmsrch_options ) {
				$cstmsrch_options = get_option( 'cstmsrch_options' );
			}
			if ( isset( $cstmsrch_options['first_install'] ) && strtotime( '-1 week' ) > $cstmsrch_options['first_install'] ) {
				bws_plugin_banner( $cstmsrch_plugin_info, 'cstmsrch', 'custom-search', '22f95b30aa812b6190a4a5a476b6b628', '214', '//ps.w.org/custom-search-plugin/assets/icon-128x128.png' );
			}
			bws_plugin_banner_to_settings( $cstmsrch_plugin_info, 'cstmsrch_options', 'custom-search-plugin', 'admin.php?page=custom_search.php' );
		}

		if ( isset( $_REQUEST['page'] ) && 'custom_search.php' == $_REQUEST['page'] ) {
			bws_plugin_suggest_feature_banner( $cstmsrch_plugin_info, 'cstmsrch_options', 'custom-search-plugin' );
		}
	}
}

/* add help tab */
if ( ! function_exists( 'cstmsrch_add_tabs' ) ) {
	function cstmsrch_add_tabs() {
		$screen = get_current_screen();
		$args = array(
			'id'		=> 'cstmsrch',
			'section'	=> '200538949'
		);
		bws_help_tab( $screen, $args );
	}
}

/* Function for delete options from table `wp_options` */
if ( ! function_exists( 'delete_cstmsrch_settings' ) ) {
	function delete_cstmsrch_settings() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		$all_plugins = get_plugins();

		if ( ! array_key_exists( 'custom-search-pro/custom-search-pro.php', $all_plugins ) ) {

			if ( is_multisite() ) {
				global $wpdb;
				/* Get all blog ids */
				$blogids = $wpdb->get_col( "SELECT `blog_id` FROM $wpdb->blogs" );
				$old_blog = $wpdb->blogid;
				foreach ( $blogids as $blog_id ) {
					switch_to_blog( $blog_id );
					delete_option( 'cstmsrch_options' );
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

/* Adds "Settings" link to the plugin action page */
add_filter( 'plugin_action_links', 'cstmsrch_action_links', 10, 2 );
/* Additional links on the plugin page */
add_filter( 'plugin_row_meta', 'cstmsrch_links', 10, 2 );
add_action( 'admin_notices', 'cstmsrch_admin_notices' );