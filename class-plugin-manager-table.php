<?php

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

Class Plugin_Manager_Table extends WP_List_Table {

	/**
	 * Plugins installed in the entire WP environment
	 * @var array
	 */
	var $installed_plugins;

	/**
	 * Plugins installed, but not network activated. Available for control on this page.
	 * @var array
	 */
	var $available_plugins;

	/**
	 * Plugins enabled for activation by single site admins
	 * @var array
	 */
	var $enabled_plugins;

	/**
	 * Count of plugins for each view of the table
	 * @var array
	 */
	var $counts;

	/**
	 * List of possible views
	 * @var array
	 */
	var $status;

	/**
	 * Get a list of CSS classes for the list table table tag.
	 *
	 * @return array List of CSS classes for the table tag.
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'fixed', 'striped', 'plugins', $this->_args['plural'] );
	}

	/**
	 * Get an associative array ( id => link ) with the list
	 * of views available on this table.
	 *
	 * @return array
	 */
	protected function get_views() {

		$views = array();

		foreach ( $this->counts as $type => $count ) {

			switch ( $type ) {
				case 'all':
					$text = 'All <span class="count">(%s)</span>';
					break;
				case 'enabled':
					$text = 'Enabled <span class="count">(%s)</span>';
					break;
				case 'disabled':
					$text = 'Disabled <span class="count">(%s)</span>';
					break;
			}

			$status_links[$type] = sprintf( "<a href='%s' %s>%s</a>",
					add_query_arg('plugin_status', $type, 'plugins.php?page=plugin-manager'),
					( $type === $this->status ) ? ' class="current"' : '',
					sprintf( $text, number_format_i18n( $count ) )
				);

		}

		return $status_links;

	}

	/**
	 * Returns an associative array containing the bulk action
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = [
			'enable-selected' => 'Enable',
			'disable-selected' => 'Disable'
		];

		return $actions;
	}
	
	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	function get_columns() {
		$columns = [
			'cb'      => '<input type="checkbox" />',
			'title'    => 'Plugin',
			'desc' => 'Description'
		];

		return $columns;
	}

	/**
	 * Render the bulk edit checkbox
	 *
	 * @param array $item
	 *
	 * @return string
	 */
	function column_cb( $item ) {
		return '<input type="checkbox" name="bulk-edit[]" value="' . $item['Slug'] . '" />';
	}

	/**
	 * Render the content of the primary Plugin Title column
	 * 
	 * @param  array $item
	 * 
	 * @return string
	 */
	function column_title( $item ) {

		$nonce = wp_create_nonce( 'manage_plugins' );

		if ( $this->is_enabled( $item['Slug'] ) ) {
			$actions = array(
				'Disable' => '<a href="plugins.php?page=plugin-manager&amp;action=disable&amp;plugin=' . $item['Slug'] . '&amp;_wpnonce=' . $nonce .'">Disable</a>'
			);
		} else {
			$actions = array(
				'Enable' => '<a href="plugins.php?page=plugin-manager&amp;action=enable&amp;plugin=' . $item['Slug'] . '&amp;_wpnonce=' . $nonce . '">Enable</a>'
			);
		}

		return '<strong>' . $item['Name'] . '</strong>' . $this->row_actions( $actions, true );

	}

	/**
	 * Render the content of the Plugin Description column
	 * 
	 * @param  array $item
	 * 
	 * @return string
	 */
	function column_desc( $item ) {

		$column = '<div class="plugin-description">';
		$column .= '<p>' . $item['Description'] . '</p>';
		$column .= '</div>';
		$column .= '<div class="second plugin-version-author-uri">Version ' . $item['Version'] . ' | By <a href="' . $item['AuthorURI'] .'">' . $item['Author'] . '</a></div>';

		return $column;

	}

	/**
	 * Sets up the data that will display in the table.
	 */
	public function prepare_items() {

		$this->_column_headers = $this->get_column_info();

		$visible_plugins = array();
		
		foreach ( $this->available_plugins as $plugin => $data ) {

			if ( ! isset( $this->status ) || 
				( $this->status === 'all' ) ||
				( $this->status === 'enabled' && $this->is_enabled( $plugin ) ) ||
				( $this->status === 'disabled' && ! $this->is_enabled( $plugin ) ) ) {
				$visible_plugins[$plugin] = $data;
				$visible_plugins[$plugin]['Slug'] = $plugin;
			}

		}

		$this->items = $visible_plugins;
	}

	/**
	 * Renders a single table row
	 * 
	 * @param  array $item
	 */
	public function single_row( $item ) {

		$class = ( $this->is_enabled( $item['Slug'] ) ) ? 'active' : 'inactive';

		echo '<tr id="' . sanitize_title( $item['Name'] ) . '" class="' . $class . '">';
		echo $this->single_row_columns( $item );
		echo '</tr>';

	}

	/**
	 * Checks whether a plugin is enabled or disabled for activation by a single site admin
	 * @param  string  $slug directory/filename string of the plugin to check
	 * @return boolean       Whether the plugin is enabled or disabled
	 */
	public function is_enabled( $slug ) {

		return isset( $this->enabled_plugins[$slug] );

	}

	/**
	 * Process any input to the page, save any changes to the enabled list, and set up our class properties
	 * 
	 */
	public function process() {

		$this->status = ( isset( $_GET['plugin_status'] ) ) ? $_GET['plugin_status'] : 'all';

		$this->enabled_plugins = get_site_option( 'mpm_enabled_plugins', array() );

		$this->installed_plugins = get_plugins();
		$this->available_plugins = array();

		foreach ( $this->installed_plugins as $plugin => $data ) {
			if ( ! is_plugin_active_for_network( $plugin ) ) {
				$this->available_plugins[$plugin] = $data;
			}
		}

		if ( isset( $this->current_action ) ) {
			$nonce = esc_attr( $_REQUEST['_wpnonce'] );

			if ( ! wp_verify_nonce( $nonce, 'manage_plugins' ) ) {
				die( "You can't do that!" );
			}
		}


		$plugin = ( isset( $_GET['plugin'] ) ) ? $_GET['plugin'] : '';
		$bulk_plugins = ( isset( $_POST['bulk-edit'] ) ) ? $_POST['bulk-edit'] : array();

		switch( $this->current_action() ) {
			case 'enable':
				if ( isset( $this->installed_plugins[$plugin] ) ) {
					if ( ! isset( $this->enabled_plugins[$plugin] ) ) {
						$this->enabled_plugins[$plugin] = time();
						ksort($this->enabled_plugins);
						update_site_option( 'mpm_enabled_plugins', $this->enabled_plugins );
					}
				}
				break;
			case 'disable':
				if ( isset( $this->installed_plugins[$plugin] ) ) {
					if ( isset( $this->enabled_plugins[$plugin] ) ) {
						unset( $this->enabled_plugins[$plugin] );
						update_site_option( 'mpm_enabled_plugins', $this->enabled_plugins );
					}
				}
				break;
			case 'enable-selected':
				foreach ( $bulk_plugins as $plugin ) {
					if ( isset( $this->installed_plugins[$plugin] ) ) {
						if ( ! isset( $this->enabled_plugins[$plugin] ) ) {
							$this->enabled_plugins[$plugin] = time();
						}
					}
				}
				ksort($this->enabled_plugins);
				update_site_option( 'mpm_enabled_plugins', $this->enabled_plugins );
				break;
			case 'disable-selected':
				foreach ( $bulk_plugins as $plugin ) {
					if ( isset( $this->installed_plugins[$plugin] ) ) {
						if ( isset( $this->enabled_plugins[$plugin] ) ) {
							unset( $this->enabled_plugins[$plugin] );
						}
					}
				}
				update_site_option( 'mpm_enabled_plugins', $this->enabled_plugins );
				break;
		}

		$this->counts = array(
			'all' => count( $this->available_plugins ),
			'enabled' => count( $this->enabled_plugins ),
			'disabled' => count( $this->available_plugins ) - count($this->enabled_plugins )
			);

	}

}