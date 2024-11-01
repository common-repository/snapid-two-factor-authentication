<?php

class WP_SnapID_Helper
{
	protected $key = 'snapid_options';

	protected $defaults = array(
		'one_step_enabled'     => true,
		'two_step_enabled'     => false,
		'customer_id'          => '',
		'app_id'               => '',
		'app_sub_id'           => '',
		'terms_and_conditions' => false,
		'hide_videos'          => false,
		'version_saved'        => '',
	);

	/**
	 * Construct.
	 */
	public function __construct()
	{
		// Nothing to see here...
	}

	/**
	 * Get default values.
	 *
	 * @return array
	 */
	public function get_defaults()
	{
		global $wp_roles;

		$defaults = $this->defaults;

		foreach ( $wp_roles->get_names() as $role ) {
			$role_name = strtolower( before_last_bar( $role ) );
			$defaults[sanitize_text_field( $role_name )] = 1;
		}
		return $defaults;
	}

	/**
	 * Helper for admin_menu.
	 *
	 * @param object $snapid
	 */
	public function admin_menu( $snapid )
	{
		add_action( 'admin_menu', array( $snapid, 'add_admin_menu' ) );
	}

	/**
	 * Check if the page is the login page.
	 *
	 * @return boolean
	 */
	public function is_login_page()
	{
		$login_page = substr( $_SERVER['PHP_SELF'], -12);
		return $login_page == 'wp-login.php';
	}

	/**
	 * Helper for settings and uninstall links.
	 *
	 * @param array $snapid
	 * @return array
	 */
	public function get_actions_settings( $snapid )
	{
		$snapid['settings'] = 'options.php';
		$snapid['uninstall'] = 'admin-post.php?action=snapid_uninstall';
		return $snapid;
	}

	/**
	 * Helper for get options.
	 *
	 * @return array
	 */
	public function get_options()
	{
		$defaults = $this->get_defaults();
		$options = get_option( $this->key );
		$options = wp_parse_args( $options, $defaults );
		return $options;
	}

	/**
	 * Helper for adding and updating options.
	 *
	 * @param array $options
	 * @return boolean
	 */
	public function update_options( $options )
	{
		return update_option( $this->key, $options );
	}

	/**
	 * Helper function for when SnapID is in process of being registered.
	 *
	 * @return [type] [description]
	 */
	public function snapid_register_helper( $snap_id )
	{
		if ( isset( $_SESSION['snapid_register_site'] ) ) {
			$options = $_SESSION['snapid_register_site'];
			return new SnapID( $options['customer_id'], $options['app_id'], $options['app_sub_id'] );
		}
		return $snap_id;
	}

	/**
	 * Helper to get templates.
	 *
	 * @param string $path
	 * @param array $variables
	 * @return string
	 */
	public function get_template( $template, $options = array() )
	{
		ob_start();
		require( plugin_dir_path( __FILE__ ) . '../templates/' . $template . '.php' );
		return ob_get_clean();
	}

	/**
	 * Helper for get notice.
	 *
	 * @return mixed
	 */
	public function get_notice()
	{
		if ( current_user_can( 'manage_options' ) ) {
			return 'admin_notices';
		}
		return false;
	}

	/**
	 * Helper for register settings.
	 *
	 * @param object $snapid
	 * @param array $options
	 */
	public function register_settings( $snapid )
	{
		register_setting( 'snapid_settings', $this->key, array( $snapid, 'validate_options' ) );
		add_action( 'admin_post_snapid_uninstall', array( $snapid, 'uninstall' ) );
	}

	/**
	 * Helper for delete options.
	 *
	 * @return boolean
	 */
	public function delete_options()
	{
		return delete_option( $this->key );
	}

	/**
	 * Helper to get the session array.
	 *
	 * @param string $session
	 * @return array
	 */
	public function get_session( $session )
	{
		return isset( $_SESSION[$session] ) ? $_SESSION[$session] : array();
	}

	/**
	 * Helper for admin_url.
	 *
	 * @param string $url
	 * @return string
	 */
	public function admin_url( $url )
	{
		return admin_url( $url );
	}

	/**
	 * Unset sessions.
	 */
	public function unset_sessions()
	{
		unset( $_SESSION['snapid_login'] );
		unset( $_SESSION['snapid_register'] );
	}

	/**
	 * Helper for JSON success response.
	 *
	 * @param array $data
	 * @return void
	 */
	public function wp_send_json_success( $data = array(), $unset = false )
	{
		if ( $unset ) {
			$this->unset_sessions(); // Unset sessions.
		}
		wp_send_json_success( $data );
	}

	/**
	 * Helper for JSON error response.
	 *
	 * @param array $data
	 * @return void
	 */
	public function wp_send_json_error( $data = array() )
	{
		$this->unset_sessions(); // Error response. Unset sessions.
		wp_send_json_error( $data );
	}
}

// EOF
