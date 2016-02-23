<?php
/*
Plugin Name: Multisite Plugin Manager
Plugin URI: http://github.com/earnjam/multisite-plugin-manager
Description: Manage the ability for site administrators to activate plugins on individual sites on a multisite network
Version: 1.0
Author: William Earnhardt
Author URI: http://wearnhardt.com
Network: true
*/

class MultisitePluginManager {

	// WP_List_Table object;
	public $plugin_manager_table;

	function __construct() {

		// Hooks for the dashboard settings page
		add_action( 'network_admin_menu', array( &$this, 'add_menu' ) );

		// Hooks for hiding disabled plugins
		add_filter( 'all_plugins', array( &$this, 'remove_plugins' ) );
		add_filter( 'plugin_row_meta' , array( &$this, 'remove_plugin_meta' ), 10, 2 );
		add_action( 'admin_init', array( &$this, 'remove_plugin_update_row' ) );

	}

	/**
	 * Adds our submenu page to the network admin for controlling plugin visibility
	 */
	function add_menu() {
		$hook = add_submenu_page( 'plugins.php', 'Plugin Availability', 'Plugin Availability', 'manage_network_options', 'plugin-manager', array( &$this, 'plugin_management_page' ) );

		add_action( "load-$hook", [ $this, 'screen_option' ] );
	}

	/**
	 * Initialize our WP_List_Table and adds any screen options
	 */
	function screen_option() {

		require dirname( __FILE__ ) . '/class-plugin-manager-table.php';

		$this->plugin_manager_table = new Plugin_Manager_Table();

	}

	/**
	 * Plugin settings page
	 */
	public function plugin_management_page() {
		?>
		<div class="wrap">
			<h1>Manage Plugin Availability</h1>
			<p>Set whether plugins are available for activation by administrators on individual sites. Excludes network activated and must-use plugins.</p> 
			<?php $this->plugin_manager_table->process(); ?>
			<?php $this->plugin_manager_table->views(); ?>
			<form method="post">
				<?php
				$this->plugin_manager_table->prepare_items();
				$this->plugin_manager_table->display(); 
				?>
			</form>
		</div>
	<?php 
	}

	/**
	 * Removes any plugin meta information for single site admins
	 * @param  array $plugin_meta The array having default links for the plugin.
	 * @param  string $plugin_file The name of the plugin file.
	 * @return array              Filtered array having default links for the plugin.
	 */
	function remove_plugin_meta( $plugin_meta, $plugin_file ) {

		if ( is_network_admin() || is_super_admin() ) {
			return $plugin_meta;
		} else {
			remove_all_actions( "after_plugin_row_$plugin_file" );
			return array();
		}

	}

	/**
	 * Removes any hooks that fire after plugin rows for single site admins
	 */
	function remove_plugin_update_row() {
		if ( ! is_network_admin() && ! is_super_admin() ) {
			remove_all_actions( 'after_plugin_row' );
		}
	}


	/**
	 * Hides plugins that are disabled and inactive from single site admins
	 * @param  array $all_plugins List of installed plugins
	 * @return array              Filtered list of installed plugins
	 */
	function remove_plugins( $all_plugins ) {

		// Don't filter Super Admins
		if ( is_super_admin() ) { 
			return $all_plugins;			
		}

		$enabled_plugins = get_site_option( 'mpm_enabled_plugins', array() );
		$active_plugins = get_option( 'active_plugins', array() );

		foreach ( (array)$all_plugins as $plugin => $data) {

			if ( ! isset( $enabled_plugins[$plugin] ) && ! in_array( $plugin, $active_plugins ) ) {
				unset( $all_plugins[$plugin] ); //remove plugin
			}

		}

		return $all_plugins;
	}

}

$multisite_plugin_manager = new MultisitePluginManager();