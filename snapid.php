<?php
/*
* Plugin Name: SnapID™ Two-Factor Authentication
* Description: Get the most secure & convenient two-factor authentication plugin for your WordPress website. With SnapID™ you will never have to remember your username and password ever again and be more secure than ever.
* Author: TextPower Inc.
* Version: 2.0.2
* Author URI: http://www.textpower.com/
*/
session_start();

class WP_SnapID_Setup
{
	private static $__instance = null;
	private $SnapID;

	public $Version = '2.0.2';
	public $Basename;
	public $Helper;

	/**
	* Construct.
	*/
	private function __construct()
	{
		// Nothing to see here...
	}

	/**
	* Setup.
	*/
	private function setup()
	{
		require_once( 'classes/snapid_helper.php' );
		require_once( 'classes/snapid_rest.php' );
		require_once( 'classes/snapid_wp.php' );

		$this->Basename = plugin_basename( __FILE__ );
		$this->Helper = new WP_SnapID_Helper();

		add_filter( 'plugin_action_links_' . $this->Basename, array( $this, 'plugin_action_links' ) );

		// init SnapID
		$this->SnapID = new WP_SnapID( $this->Basename, $this->Helper, $this->Version );

		$this->Helper->admin_menu( $this );

		if ( is_admin() ) {
			add_action( 'show_user_profile', array( $this, 'profile_action' ) );
			add_action( 'edit_user_profile', array( $this, 'profile_action' ) );
			add_action( 'activated_plugin', array( $this, 'activated_plugin' ) );
		}

		add_action( 'login_enqueue_scripts', array( $this, 'login_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
	}

	/**
	* Singleton implementation.
	*
	* @return object
	*/
	public static function instance()
	{
		if ( !is_a( self::$__instance, 'WP_SnapID_Setup' ) ) {
			self::$__instance = new WP_SnapID_Setup;
			self::$__instance->setup();
		}
		return self::$__instance;
	}

	/**
	* Adds links to plugin page entry.
	*
	* @param $links
	* @uses admin_url
	* @return array
	*/
	public function plugin_action_links( $links )
	{
		$uninstall = sprintf( '<a href="%s" title="Uninstall and deactivate this plugin ">%s</a>', admin_url( 'options-general.php?page=snapid#snapid-uninstall-wrap' ), _x( 'Uninstall', 'Uninstall and deactivate this plugin', 'snapid' ) );
		array_unshift( $links, $uninstall );

		$settings = sprintf( '<a href="%s" title="Plugin configuration and preferences">%s</a>', admin_url( 'options-general.php?page=snapid#snapid-settings-wrap' ), _x( 'Settings', 'Plugin configuration and preferences', 'snapid' ) );
		array_unshift( $links, $settings );

		return $links;
	}

	/**
	* SnapID login assets.
	*
	* @return void
	*/
	public function login_assets()
	{
		$version = $this->Version;
		wp_enqueue_style( 'snapid_login_css', plugin_dir_url( __FILE__ ) . 'css/snapid_login.css', array(), $version );
		wp_enqueue_style( 'snapid_jquery_modal_css', plugin_dir_url( __FILE__ ) . 'jquery-modal/jquery.modal.css', array(), $version );
		wp_enqueue_style( 'snapid_css', plugin_dir_url( __FILE__ ) . 'css/snapid.css', array(), $version );
		wp_enqueue_script( 'snapid_jquery_modal_js', plugin_dir_url( __FILE__ ) . 'jquery-modal/jquery.modal.min.js', array( 'jquery' ), $version, true );
		wp_enqueue_script( 'snapid_js', plugin_dir_url( __FILE__ ) . 'js/snapid.js', array( 'jquery', 'snapid_jquery_modal_js' ), $version, true );
		wp_enqueue_script( 'snapid_login_js', plugin_dir_url( __FILE__ ) . 'js/snapid_login.js', array( 'jquery', 'snapid_jquery_modal_js', 'snapid_js' ), $version, true );

		$snapid = array();
		$snapid['ajaxurl'] = admin_url( 'admin-ajax.php' );

		if ( isset( $_GET['redirect_to'] ) ) {
			$snapid['redirect_to'] = esc_url( urldecode( $_GET['redirect_to'] ) );
		} else {
			$snapid['redirect_to'] = admin_url();
		}

		$snapid['one_step_enabled'] = $this->SnapID->OneStepEnabled;
		$snapid['two_step_enabled'] = $this->SnapID->TwoStepEnabled;

		wp_localize_script( 'snapid_js', 'snapid', $snapid );
	}

	/**
	* SnapID admin assets.
	*
	* @return void
	*/
	public function admin_assets()
	{
		$version = $this->Version;
		wp_enqueue_style( 'snapid_jquery_modal_css', plugin_dir_url( __FILE__ ) . 'jquery-modal/jquery.modal.css', array(), $version );
		wp_enqueue_style( 'snapid_css', plugin_dir_url( __FILE__ ) . 'css/snapid.css', array(), $version );
		wp_enqueue_script( 'snapid_jquery_modal_js', plugin_dir_url( __FILE__ ) . 'jquery-modal/jquery.modal.min.js', array( 'jquery' ), $version, true );
		wp_enqueue_script( 'snapid_js', plugin_dir_url( __FILE__ ) . 'js/snapid.js', array( 'jquery', 'snapid_jquery_modal_js' ), $version, true );
		wp_enqueue_style( 'snapid_admin_css', plugin_dir_url( __FILE__ ) . 'css/snapid_admin.css', array( 'snapid_css' ), $version );
		wp_enqueue_script( 'snapid_admin_js', plugin_dir_url( __FILE__ ) . 'js/snapid_admin.js', array( 'jquery', 'snapid_jquery_modal_js', 'snapid_js' ), $version, true );

		$snapid = array();
		$snapid['ajaxurl'] = admin_url( 'admin-ajax.php' );

		wp_localize_script( 'snapid_js', 'snapid', $snapid );
	}

	/**
	* Add SnapID to the admin menu.
	*
	* @action admin_menu
	* @uses add_options_page
	* @return void
	*/
	public function add_admin_menu()
	{
		add_options_page( 'SnapID&trade;', 'SnapID&trade;', 'manage_options', 'snapid', array( $this, 'add_settings_page' ) );
	}

	/**
	* Add SnapID to the network menu for multisite.
	*
	* @action network_admin_menu
	* @uses add_submenu_page
	* @return void
	*/
	public function add_network_menu()
	{
		add_submenu_page( 'settings.php', 'SnapID&trade;', 'SnapID&trade;', 'manage_options', 'snapid', array( $this, 'add_settings_page' ) );
	}

	/**
	* Add SnapID section on profile page.
	*
	* @uses wp_create_nonce, do_action
	* @return void
	*/
	public function profile_action()
	{
		if ( ! $this->SnapID->OneStepEnabled && ! $this->SnapID->TwoStepEnabled ) {
			return;
		}
		global $user_id;
		$options = array(
			'user_id' => $user_id
		);
		echo $this->Helper->get_template( 'profile_hidden_fields', $options );
		do_action( 'snapid_profile' );
	}

	/**
	* Add SnapID admin settings page.
	*
	* @uses settings_fields, do_settings_sections, do_action, submit_buttons, esc_attr
	* @return void
	*/
	public function add_settings_page()
	{
		$snapid = array(
			'settings' => '',
			'uninstall' => '',
		);
		$snapid = $this->Helper->get_actions_settings( $snapid );

		$options = array(
			'settings' => $snapid['settings'],
			'uninstall' => $snapid['uninstall'],
			'is_snapid_setup' => $this->SnapID->is_snapid_setup()
		);
		echo $this->Helper->get_template( 'settings_page', $options );
	}

	/**
	 * Activate plugin hook that redirects to settings page.
	 *
	 * @param string $plugin
	 * @return void
	 */
	public function activated_plugin( $plugin )
	{
		if ( $plugin === plugin_basename( __FILE__ ) ) {
			if( ! is_multisite() ) {
				exit( wp_safe_redirect( add_query_arg( array( 'page' => 'snapid' ), admin_url( 'options-general.php' ) ) ) );
			}
		}
	}
}

WP_SnapID_Setup::instance();

// EOF
