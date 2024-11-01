<?php

class WP_SnapID
{
	private $SnapID;
	private $CustomerID;
	private $ApplicationID;
	private $ApplicationSubID;

	protected $Defaults;

	public $OneStepEnabled;
	public $TwoStepEnabled;
	public $Helper;

	/**
	 * Construct.
	 */
	public function __construct( $basename, $helper, $version )
	{
		$this->Basename = $basename;
		$this->Helper = $helper;
		$this->Version = $version;

		// Only run SnapID on login and admin pages.
		if ( is_admin() || $this->Helper->is_login_page() ) {
			add_action( 'init', array( $this, 'setup' ) );
		}
	}

	/**
	 * Checks if SnapID is setup.
	 *
	 * @return boolean
	 */
	public function is_snapid_setup()
	{
		if ( ! $this->SnapID  ) {
			$this->SnapID = new SnapID( $this->CustomerID, $this->ApplicationID, $this->ApplicationSubID );
		}
		$check = $this->SnapID->perform_join( '', '', '', '', '' );
		return empty( $check->errordescr ) ? true : false;
	}

	/**
	 * Gets things started.
	 *
	 * @return void
	 */
	public function setup()
	{
		$this->defaults = $this->Helper->get_defaults();
		$options = $this->Helper->get_options();

		$this->OneStepEnabled = $options['one_step_enabled'];
		$this->TwoStepEnabled = $options['two_step_enabled'];
		$this->CustomerID = $options['customer_id'];
		$this->ApplicationID = $options['app_id'];
		$this->ApplicationSubID = $options['app_sub_id'];
		$this->TermsAndConditions = $options['terms_and_conditions'];
		$this->HideVideos = $options['hide_videos'];
		$this->VersionSaved = $options['version_saved'];

		add_action( 'admin_notices', array( $this, 'plugin_shutdown_notice' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'snapid_settings', array( $this, 'snapid_settings' ) );
		add_action( 'snapid_uninstall', array( $this, 'snapid_uninstall' ) );
		add_action( 'wp_ajax_snapid_register_site', array( $this, 'ajax_register_site' ) );
		add_action( 'wp_ajax_snapid_register_user', array( $this, 'ajax_register_user' ) );
		add_action( 'wp_ajax_snapid_toggle_videos_site', array( $this, 'ajax_toggle_videos_site' ) );
		add_action( 'wp_ajax_snapid_join_check', array( $this, 'ajax_join_check' ) );

		add_filter( 'admin_body_class', array( $this, 'admin_body_classes' ) );

		if ( ! $this->SnapID  ) {
			$this->SnapID = new SnapID( $this->CustomerID, $this->ApplicationID, $this->ApplicationSubID );
		}

		// Let's check that the creditials entered are valid

		if ( $this->is_snapid_setup() && $this->TermsAndConditions && ( $this->OneStepEnabled || $this->TwoStepEnabled ) ) {
			// All is good

			// Check for updates plugin wants to make
			$this->plugin_update( $options );

			if ( is_admin() ) {
				add_action( 'snapid_profile', array( $this, 'add_profile_fields' ) );
			}

			add_action( 'wp_ajax_snapid_remove', array( $this, 'ajax_remove' ) );

			add_action( 'wp_ajax_snapid_keyid_check', array( $this, 'ajax_keyid_check' ) );
			add_action( 'wp_ajax_nopriv_snapid_keyid_check', array( $this, 'ajax_keyid_check' ) );

			add_action( 'wp_ajax_snapid_two_step_check', array( $this, 'ajax_two_step_check' ) );
			add_action( 'wp_ajax_nopriv_snapid_two_step_check', array( $this, 'ajax_two_step_check' ) );

			add_action( 'wp_ajax_snapid_authenticate', array( $this, 'ajax_authenticate' ) );
			add_action( 'wp_ajax_nopriv_snapid_authenticate', array( $this, 'ajax_authenticate' ) );

			add_action( 'login_message', array( $this, 'login_message' ) );

			add_action( 'admin_notices', array( $this, 'setup_notice' ) );

			if ( $this->TwoStepEnabled ) {
				add_filter( 'wp_authenticate_user', array( $this, 'two_step_authenticate' ), 10, 2 );

				add_action( 'login_form', array( $this, 'login_form' ) );
			}
			add_filter( 'login_body_class', array( $this, 'login_classes' ) );
		} else {
			// Nag until set up correctly.
			$notice = $this->Helper->get_notice();
			if ( $notice ) {
				add_action( $notice, array( $this, 'config_nag' ) );
			}
		}
	}

	/**
	 * Check for updates plugin requests to be made.
	 *
	 * @return void
	 */
	private function plugin_update( $options )
	{
		$update = false;

		// 2.0 - SnapID user meta is no longer serialized. It is now string.
		if ( version_compare( $this->VersionSaved, '2.0', '<' ) ) {
			$this->data_less_than_2_0();
			$update = true;
		}

		if ( $update ) {
			$options['version_saved'] = sanitize_text_field( $this->Version );
			$this->Helper->update_options( $options );
		}
	}

	/**
	 * Plugin data updates required for 2.0.
	 * User post meta is now string of user proxy without credentials (no longer needed).
	 *
	 * @return void
	 */
	private function data_less_than_2_0()
	{
		$users = get_users(
			array(
				'meta_key' => '_snapid_user_proxy',
			)
		);
		foreach ( $users as $user ) {
			$user_proxy = false;
			$metas = get_user_meta( $user->ID, '_snapid_user_proxy', false );
			if ( empty( $metas ) || ! is_array( $metas ) ) {
				continue;
			}
			foreach ( $metas as $meta ) {
				if ( is_string( $meta ) ) {
					continue;
				}
				if ( $meta['customer_id'] === $this->CustomerID && $meta['app_id'] === $this->ApplicationID && $meta['app_sub_id'] === $this->ApplicationSubID ) {
					$user_proxy = $this->sanitize_user_proxy( $meta['user_proxy'] );
					update_user_meta( $user->ID, '_snapid_user_proxy', $user_proxy, $meta );
				} else {
					delete_user_meta( $user->ID, '_snapid_user_proxy', $meta );
				}
			}
		}
	}

	/**
	 * Function to set the user meta args.
	 *
	 * @param string $user_proxy
	 * @return array
	 */
	private function sanitize_user_proxy( $user_proxy )
	{
		if ( ! is_string( $user_proxy ) ) {
			return false;
		}
		return sanitize_text_field( $user_proxy );
	}

	/**
	 * Get the right user proxy for the credentials when data is serialized.
	 * Removes old user proxies tied to old credentials.
	 * This function is for updating versions of SnapID less than version 2.0.
	 *
	 * @param integer $user_id
	 * @return mixed
	 */
	private function get_user_proxy( $user_id )
	{
		$user_proxy = get_user_meta( $user_id, '_snapid_user_proxy', true );
		if ( empty( $user_proxy ) || ! is_string( $user_proxy ) ) {
			return false;
		}
		return $user_proxy;
	}

	/**
	* Add body class to SnapID settings page.
	*
	* @param string $classes
	* @return string
	*/
	public function admin_body_classes( $classes )
	{
		$screen = get_current_screen();

		if ( 'settings_page_snapid' === $screen->id && $this->HideVideos ) {
			return $classes . ' snapid-hide-videos';
		}

		return $classes;
	}

	/**
	 * Nag when setup is not configured or configured correctly.
	 *
	 * @action admin_notices
	 * @return void
	 */
	public function config_nag()
	{
		$message = '';
		$options = array(
			'id' => 'snapid-config-nag',
			'message' => ''
		);

		if ( ! $this->is_snapid_setup() ) {
			$message = 'SnapID&trade; is not configured correctly.';
			if ( empty( $this->CustomerID ) || empty( $this->ApplicationID ) ) {
				$message = 'SnapID&trade; is not configured.';
			}
			$options['message'] = '<strong>' . $message . '</strong> Visit the <a href="' . admin_url( 'options-general.php?page=snapid' ) . '">settings page</a> to continue setup.';
		} else if ( false === $this->OneStepEnabled && false === $this->TwoStepEnabled ) {
			$options['message'] = '<strong>SnapID&trade; has no roles set for One-Step Login or Two-Step Login</strong>. Please <a href="' . admin_url( 'options-general.php?page=snapid' ) . '">visit the settings page</a> to continue the setup.';
		}

		if ( ! empty( $options['message'] ) ) {
			echo $this->Helper->get_template( 'message', $options );
		}
	}

	/**
	 * Notify users to set up SnapID.
	 *
	 * @action admin_notices
	 * @return void
	 */
	public function setup_notice()
	{
		$current_user = wp_get_current_user();
		$check = $this->check_user_role( $current_user->ID );
		if ( !$check || $this->get_user_proxy( $current_user->ID ) ) {
			return;
		}
		if ( '2' === $check ) {
			$message = 'Your user is required to use <strong>SnapID&trade; Two-Step Login</strong>. Please <a href="' . admin_url( 'profile.php#snapid' ) . '">go to your profile</a> to complete the setup.';
		} else if ( '1' === $check ) {
			$message = 'Your user may use <strong>SnapID&trade; One-Step Login</strong>. Please <a href="' . admin_url( 'profile.php#snapid' ) . '">go to your profile</a> to complete the setup.';
		} else {
			return;
		}
		$options = array(
			'id' => 'snapid-profile-nag',
			'message' => $message
		);
		echo $this->Helper->get_template( 'message', $options );
	}

	/**
	 * Test that user authenticates.
	 *
	 * @uses get_user_by, wp_check_password, $this->Helper->wp_send_json_success, $this->Helper->wp_send_json_error
	 * @return json
	 */
	public function ajax_two_step_check()
	{
		$user = get_user_by( 'login', $_POST['log'] );
		if ( $user && $test_user = wp_check_password( $_POST['pwd'], $user->data->user_pass, $user->ID ) && $this->get_user_proxy( $user->ID ) && $this->check_user_role( $user->ID ) == '2' ) {
			$_SESSION['snapid_login'] = $this->Helper->get_session( 'snapid_login' );
			$_SESSION['snapid_login']['two_step_user_proxy_check'] = $this->get_user_proxy( $user->ID );
			$this->Helper->wp_send_json_success( array() );
		}
		$this->Helper->wp_send_json_error();
	}

	/**
	 * Check if a user's role is enabled for SnapID.
	 *
	 * @uses get_option
	 * @return mixed
	 */
	public function check_user_role( $user_id )
	{
		if ( ! $user_id ) {
			return false;
		}
		$user = get_user_by( 'id', $user_id );
		$options = $this->Helper->get_options();

		foreach ( $user->roles as $role ) {
			if ( isset( $options[$role] ) ) {
				if ( ( $options[$role] == '2' && $this->TwoStepEnabled ) || ( $options[$role] == '1' && $this->OneStepEnabled ) ) {
					return $options[$role];
				}
			}
		}
		return false;
	}

	/**
	 * Backend final authentication before granting access.
	 *
	 * @uses sanitize_text_field, WP_Error
	 * @return object
	 */
	public function two_step_authenticate( $user, $password )
	{
		// Check that the password is correct, if not, send 'em back
		if( ! wp_check_password( $password, $user->data->user_pass, $user->ID ) ) {
			return $user;
		}

		$snapid_meta = $this->get_user_proxy( $user->ID );
		$role = $this->check_user_role( $user->ID );

		if ( isset( $snapid_meta ) && ! empty( $snapid_meta ) && $this->TwoStepEnabled && isset( $role ) && $role == '2' ) {

			$loginaccessidentifier = sanitize_text_field( $_SESSION['snapid_login']['loginaccessidentifier'] );
			$snapidkey = sanitize_text_field( $_SESSION['snapid_login']['snapidkey'] );
			$keycheckid = sanitize_text_field( $_SESSION['snapid_login']['keycheckid'] );

			$this->Helper->unset_sessions(); // Done with the sessions.

			$response = $this->SnapID->perform_matchtouser( $loginaccessidentifier, $snapidkey, $keycheckid, '', '', '' );
			if ( $response->errordescr == '' ) {
				return $user;
			} else {
				wp_logout();
				return new WP_Error( 'invalid_snapid_user_proxy', __( '<strong>Error</strong>: SnapID&trade; Two-Step Authentication is required for this user.', 'snapid' ) );
			}
		}
		return $user;
	}

	/**
	 * Add SnapID admin settings section.
	 *
	 * @uses esc_attr, get_option
	 * @return void
	 */
	public function snapid_settings()
	{
		do_settings_fields( 'snapid', 'snapid_settings' );
		$options = $this->Helper->get_options();
		$options['is_snapid_setup'] = $this->is_snapid_setup();
		$options['auth_modal'] = $this->Helper->get_template( 'auth_modal', array( 'action' => 'registration' ) );
		$options['profile_hidden_fields'] = $this->Helper->get_template( 'profile_hidden_fields', array( 'user_id' => get_current_user_id() ) );

		echo $this->Helper->get_template( 'settings', $options );
	}

	/**
	 * Add SnapID admin uninstall section.
	 *
	 * @uses wp_create_nonce
	 * @return void
	 */
	public function snapid_uninstall()
	{
		echo $this->Helper->get_template( 'uninstall' );
	}

	/**
	 * Register plugin settings.
	 *
	 * @action admin_init
	 * @return void
	 */
	public function register_settings()
	{
		$this->Helper->register_settings( $this );
	}

	/**
	 * Validate SnapID options.
	 *
	 * @param $options
	 * @uses santize_text_field
	 * @return array
	 */
	public function validate_options( $options )
	{
		global $wp_roles;

		$options_validated = array();
		foreach ( $options as $key => $value ) {
			if ( isset( $this->defaults[$key] ) ) {
				switch ( $key ) {
					case 'one_step_enabled':
					case 'two_step_enabled':
					case 'terms_and_conditions':
						$options_validated[$key] = (bool) $value;
						break;
					default:
						$options_validated[$key] = sanitize_text_field( $value );
				}
			}
		}

		// Check that roles were actually selected after enabling One-Step or Two-Step login.
		// If not, set the One-Step or Two-Step back to disabled.
		$role_call = array();
		foreach ( $wp_roles->get_names() as $role ) {
			$role_name = strtolower( before_last_bar( $role ) );
			if( isset( $options_validated[$role_name] ) ) {
				$role_call[] = $options_validated[$role_name];
			} else {
				$options_validated[$role_name] = 0; // If not selected, set to 0 to prevent default from overwriting value on load.
			}
		}
		if ( empty( $role_call ) ) {
			$options_validated['one_step_enabled'] = false;
			$options_validated['two_step_enabled'] = false;
		} else {
			if ( false === array_search( 1, $role_call ) ) {
				$options_validated['one_step_enabled'] = false;
			}
			if ( false === array_search( 2, $role_call ) ) {
				$options_validated['two_step_enabled'] = false;
			}
		}
		return $options_validated;
	}

	/**
	 * Uninstall and deactivates SnapID.
	 *
	 * @uses current_user_can, wp_verify_nonce, delete_option, get_users, delete_user_meta
	 * @return void
	 */
	public function uninstall()
	{
		if ( ! isset( $_POST['_wpnonce'] ) ) {
			wp_die( 'You cannot do this action' );
		}

		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'snapid-uninstall' ) ) {
			wp_die( 'You cannot do this action' );
		}

		if ( isset( $_POST['snapid-delete-settings'] ) ) {
			$this->Helper->delete_options();
			$users = get_users(
				array(
					'meta_key' => '_snapid_user_proxy',
				)
			);
			foreach ( $users as $user ) {
				$meta = get_user_meta( $user->ID, '_snapid_user_proxy', true );
				$this->SnapID->perform_remove( $meta );
				delete_user_meta( $user->ID, '_snapid_user_proxy', $meta );
			}
		}

		// Kills all SnapID sessions.
		$this->Helper->unset_sessions();
		unset( $_SESSION['snapid_register_site'] );

		deactivate_plugins( $this->Basename );

		exit( wp_safe_redirect( add_query_arg( array( 'deactivate' => 'true' ), $this->Helper->admin_url( 'plugins.php' ) ) ) );
	}

	/**
	 * Add SnapID registration to profile.
	 *
	 * @return void
	 */
	public function add_profile_fields()
	{
		global $user_id;
		$role = $this->check_user_role( $user_id );
		switch ( $role ) {
			case '1':
				$role_type = 'One-Step';
				break;
			case '2':
				$role_type = 'Two-Step';
				break;
			default:
				$role = false;
				$role_type = '';
		}
		$options = array(
			'snapid_user' => $this->get_user_proxy( $user_id ),
			'role' => $role,
			'role_type' => $role_type,
			'auth_modal' => $this->Helper->get_template( 'auth_modal', array( 'action' => 'registration' ) ),
		);
		echo $this->Helper->get_template( 'profile', $options );
	}

	/**
	 * SnapID login.
	 *
	 * @return void
	 */
	public function login_message()
	{
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'lostpassword' ) {
			return;
		}
		echo '<input type="hidden" id="snapid-nonce" value="' . wp_create_nonce( 'snapid-authenticate' ) . '" />' . "\n";
		if ( $this->OneStepEnabled ) {
			?>
			<button id="snapid-login" class="button-snapid"><span>Sign in with SnapID&trade;</span></button>
			<h3 class="snapid-or">OR</h3>
			<?php
		}
		echo $this->Helper->get_template( 'auth_modal', array( 'action' => 'login' ) );
	}

	/**
	 * Powered by SnapID.
	 *
	 * @return void
	 */
	public function login_form()
	{
		echo $this->Helper->get_template( 'login_form' );
	}

	/**
	 * Add body classes to login page.
	 *
	 * @return void
	 */
	public function login_classes( $classes )
	{
		if ( $this->TwoStepEnabled ) {
			$classes[] = 'snapid-two-step';
		}
		if ( $this->OneStepEnabled ) {
			$classes[] = 'snapid-one-step';
		}
		return $classes;
	}

	/**
	 * Login user after authenticated.
	 *
	 * @param $user_data, $response
	 * @return bool
	 */
	public function snapid_login_user( $user_data, $response )
	{
		$args = array(
			'meta_key' => '_snapid_user_proxy',
			'meta_query' => array(
				'key' => '_snapid_user_proxy',
				'value' => $user_data->userproxy,
				'compare' => '=',
			),
			'number' => 1,
		);

		$get_users = get_users( $args );

		if ( ! $get_users ) {
			$response->errordescr = 'Sorry, something went wrong...';
			$this->Helper->wp_send_json_success( $response );
		}
		$get_user = $get_users[0];
		$user_id = intval( $get_user->ID );

		// Check that user_proxy is also part of this application setup for this user.
		$check_user_proxy = $this->get_user_proxy( $user_id );
		if ( $check_user_proxy !== $user_data->userproxy ) {
			$response->errordescr = 'Sorry, something went wrong...';
			$this->Helper->wp_send_json_success( $response );
		}

		// Check that this user's role can use One-Step Login
		$role = $this->check_user_role( $user_id );
		if ( ! $role ) {
			$response->errordescr = 'Sorry, you are not configured to use SnapID&trade; One-Step Login. Contact your administrator to set it up.';
			$this->Helper->wp_send_json_success( $response ); // Sent as a success for handling reasons
		} else if ( $role == '2' && ! $_SESSION['snapid_login']['two_step'] ) {
			$response->errordescr = 'You have two-step login enabled for your account. Login first using your username and password.';
			$this->Helper->wp_send_json_success( $response ); // Sent as success for handling reasons
		}

		$user = get_user_by( 'id', $user_id );
		if ( $user && $role == '1') {
			wp_set_current_user( $user_id, $user->user_login );
			wp_set_auth_cookie( $user_id );
			do_action( 'wp_login', $user->user_login, $user );
		}
		return true;
	}

	/**
	 * Ajax SnapID site registration endpoint.
	 *
	 * @action wp_ajax_snapid_register_site
	 * @uses check_ajax_referer, wp_get_current_user, current_user_can, $this->Helper->wp_send_json_error, $this->Helper->wp_send_json_success
	 * @return json
	 */
	public function ajax_register_site()
	{
		$current_user = wp_get_current_user();

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'You do not have permission to do this.' ) );
		}

		if ( ! check_ajax_referer( 'snapid_settings-options', 'nonce', false ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}

		// If we have a session already for registering, let's use that, if not get one.
		if ( ! isset( $_SESSION['snapid_register_site'] ) ) {
			$url = 'https://secure.textkey.com/snapid/siteregistration/snapidsitecredentials.php';

			$args = array(
				'firstname'    => $current_user->user_firstname,
				'lastname'     => $current_user->user_lastname,
				'company'      => get_bloginfo( 'name' ),
				'url'          => home_url(),
				'contactemail' => get_bloginfo( 'admin_email' ),
				'adminemail'   => $current_user->user_email
			);

			$data = array(
				'body'    => wp_json_encode( $args )
			);

			$response = wp_remote_post( $url, $data );

			if ( is_wp_error( $response ) ) {
				$this->Helper->wp_send_json_error();
			}

			$creds = $response['body'];

			$creds = json_decode( $creds );

			if ( isset( $creds->error ) ) {
				$this->Helper->wp_send_json_error( array( 'errordescr' => esc_html( $creds->error ) ) );
			}

			$options = $this->Helper->get_options();

			$options['customer_id'] = sanitize_text_field( $creds->customeridentifier );
			$options['app_id'] = sanitize_text_field( $creds->applicationidentifier );
			$options['app_sub_id'] = sanitize_text_field( $creds->applicationsubid );
			$options['one_step_enabled'] = true;
			$options['terms_and_conditions'] = true;

			$_SESSION['snapid_register_site'] = $options;
		} else {
			$options = $_SESSION['snapid_register_site'];
		}

		$this->Helper->wp_send_json_success( $options );
	}

	/**
	 * Ajax endpoint to toggle instructional videos on settings page.
	 *
	 * @return json
	 */
	public function ajax_toggle_videos_site()
	{
		$current_user = wp_get_current_user();

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'You do not have permission to do this.' ) );
		}

		if ( ! check_ajax_referer( 'snapid_settings-options', 'nonce', false ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}

		$hide_videos = intval( $_POST['hide_videos'] );
		$options = $this->Helper->get_options();

		$options['hide_videos'] = $hide_videos;
		$this->Helper->update_options( $options );

		$this->Helper->wp_send_json_success();
	}

	/**
	 * Ajax SnapID user registration endpoint.
	 *
	 * @action wp_ajax_snapid_register_user
	 * @uses check_ajax_referer, wp_get_current_user, current_user_can, $this->Helper->wp_send_json_error, $this->Helper->wp_send_json_success
	 * @return json
	 */
	public function ajax_register_user()
	{
		$current_user = wp_get_current_user();

		if ( !check_ajax_referer( 'snapid-register', 'nonce', false ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}
		if ( isset( $_POST['user_id'] ) && intval( $_POST['user_id'] ) ) {
			$user_id = $_POST['user_id'];
			$user = get_userdata( $user_id );
			$user_email = $user->user_email;
		} else {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'You do not have permission to do this.' ) );
		}
		if ( $current_user->ID != $user_id && ! current_user_can( 'manage_options' ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'You do not have permission to do this.' ) );
		}

		$this->SnapID = $this->Helper->snapid_register_helper( $this->SnapID );

		$response = $this->SnapID->perform_join( '', '', '', $user_email, '' );

		if ( ! $response ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}
		if ( isset( $response->errordescr ) && $response->errordescr != '' ) {
			$this->Helper->wp_send_json_error( $response );
		}

		$_SESSION['snapid_register'] = $this->Helper->get_session( 'snapid_register' );
		$_SESSION['snapid_register']['keycheckid'] = sanitize_text_field( $response->keycheckid );
		$_SESSION['snapid_register']['joincode'] = sanitize_text_field( $response->joincode );
		$_SESSION['snapid_register']['tocode'] = sanitize_text_field( $response->tocode );
		$_SESSION['snapid_register']['user_id'] = intval( $_POST['user_id'] );

		foreach ( $_SESSION['snapid_register'] as $item ) {
			if ( ! $item || empty( $item ) ) {
				$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
			}
		}

		$this->Helper->wp_send_json_success( array( 'tocode' => $response->tocode, 'joincode' => $response->joincode ) );
	}

	/**
	 * Ajax check for register.
	 *
	 * @action wp_ajax_snapid_keyid_check, wp_ajax_nopriv_snapid_keyid_check
	 * @return json
	 */
	public function ajax_join_check()
	{
		if ( ! check_ajax_referer( 'snapid-register', 'nonce', false ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}
		if ( ! isset( $_POST['response'] ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}
		$data = $_POST['response'];

		if ( ! $data ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}

		$keycheckid = sanitize_text_field( $_SESSION['snapid_register']['keycheckid'] );
		$joincode = sanitize_text_field( $_SESSION['snapid_register']['joincode'] );
		$user_id = intval( $_SESSION['snapid_register']['user_id'] );

		$this->SnapID = $this->Helper->snapid_register_helper( $this->SnapID );

		$response = $this->SnapID->perform_checkJoin( $keycheckid );

		if ( !$response ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}

		if ( $response->errordescr != '' ) {
			$this->Helper->wp_send_json_error( $response );
		}

		if ( $response->keyreceived ) {
			$user_data = $this->SnapID->perform_getUserProxy( $joincode );
			if ( $user_data->userwasalreadyjoined ) {
				// Check the site for an actual user using the userproxy.
				// If no one is using it, we can register the device for this site.
				$meta = $this->sanitize_user_proxy( $user_data->userproxy );
				$args = array( 'number' => 1, 'meta_key' => '_snapid_user_proxy', 'meta_value' => $meta );
				$user_query = new WP_User_Query( $args );
				$results = $user_query->get_results();
				if ( !empty( $results ) ) {
					$response->errordescr = 'This phone number was already used to register a user on SnapID&trade; for this website. Please use a different phone number.';
					$this->Helper->wp_send_json_error( $response );
				}
			}
			if ( $user_id && $user_data && $user_data->errordescr == '' ) {
				$meta = $this->sanitize_user_proxy( $user_data->userproxy );
				$snapid_meta = add_user_meta( $user_id, '_snapid_user_proxy', $meta, false );
				if ( $snapid_meta ) {
					$response->errordescr = 'Account successfully linked to SnapID&trade;.';

					// Save site options on first user register.
					if ( isset( $_SESSION['snapid_register_site'] ) ) {
						$options = $_SESSION['snapid_register_site'];
						unset( $_SESSION['snapid_register_site'] );
						$this->Helper->update_options( $options );

						// Send out credentials via email.
						$url = 'https://secure.textkey.com/snapid/siteregistration/snapidsitesendemail.php';

						$args = array(
							'customeridentifier'    => $options['customer_id'],
							'applicationidentifier' => $options['app_id']
						);

						$data = array(
							'body'    => wp_json_encode( $args )
						);

						wp_remote_post( $url, $data );
					}
					$this->Helper->wp_send_json_success( $response );
				} else {
					$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
				}
			}
		}
		$this->Helper->wp_send_json_success( $response );
	}

	/**
	 * Ajax remove registered user.
	 *
	 * @return json
	 */
	public function ajax_remove()
	{
		if ( !check_ajax_referer( 'snapid-register', 'nonce', false ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}

		$user_id = intval( $_POST['user_id'] );

		$response = $this->remove_snapid_user( $user_id );
		if ( ! $response || $response->errordescr ) {
			$this->Helper->wp_send_json_error( $response );
		} else {
			$this->Helper->wp_send_json_success( $response );
		}
	}

	/**
	 * Remove user from SnapID.
	 *
	 * @return array
	 */
	public function remove_snapid_user( $user_id )
	{
		if ( !$user_id || intval( $user_id ) == 0 ) {
			return array( 'errordescr' => 'This is not a valid user' );
		}
		$snapid_user = $this->get_user_proxy( $user_id );
		if ( !$snapid_user ) {
			return array( 'errordescr' => 'This user is not set up with SnapID&trade;' );
		}
		$response = $this->SnapID->perform_remove( $snapid_user );
		if ( !$response || ( isset( $response->errorDesc ) && !empty( $response->errorDesc ) ) ) {
			return array( 'errordescr' => 'This user is not set up with SnapID&trade;' );
		} else {
			$meta = $this->sanitize_user_proxy( $snapid_user );
			delete_user_meta( $user_id, '_snapid_user_proxy', $meta );
			return $response;
		}
	}

	/**
	 * Ajax authentication.
	 *
	 * @action wp_ajax_snapid_authenticate, wp_ajax_nopriv_snapid_authenticate
	 * @return json
	 */
	public function ajax_authenticate()
	{
		if ( ! check_ajax_referer( 'snapid-authenticate', 'nonce', false ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}
		$response = $this->SnapID->perform_issueSnapIDChallenge( '', '', '', '' );
		if ( ! $response ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}
		if ( isset( $response->errordescr ) && $response->errordescr != '' ) {
			$this->Helper->wp_send_json_error( $response );
		}

		$two_step = ($_GET['two_step'] === 'true');

		$loginaccessidentifier = $response->loginaccessidentifier ? $response->loginaccessidentifier : null;
		$snapidkey = $response->snapidkey ? $response->snapidkey : null;
		$keycheckid = $response->keycheckid ? $response->keycheckid : null;

		$_SESSION['snapid_login'] = $this->Helper->get_session( 'snapid_login' );
		$_SESSION['snapid_login']['loginaccessidentifier'] = $loginaccessidentifier;
		$_SESSION['snapid_login']['snapidkey'] = $snapidkey;
		$_SESSION['snapid_login']['keycheckid'] = $keycheckid;
		$_SESSION['snapid_login']['two_step'] = $two_step;


		$this->Helper->wp_send_json_success( array( 'tocode' => $response->tocode, 'snapidkey' => $response->snapidkey ) );
	}

	/**
	 * Ajax check for authentication.
	 *
	 * @action wp_ajax_snapid_keyid_check, wp_ajax_nopriv_snapid_keyid_check
	 * @return json
	 */
	public function ajax_keyid_check()
	{
		if ( !check_ajax_referer( 'snapid-authenticate', 'nonce', false ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Cheatin\', huh?' ) );
		}
		if ( ! isset( $_POST['response'] ) ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}
		$data = $_POST['response'];

		if ( ! $data ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}

		$loginaccessidentifier = sanitize_text_field( $_SESSION['snapid_login']['loginaccessidentifier'] );
		$snapidkey = sanitize_text_field( $_SESSION['snapid_login']['snapidkey'] );
		$keycheckid = sanitize_text_field( $_SESSION['snapid_login']['keycheckid'] );

		$response = $this->SnapID->perform_checkKey( $keycheckid );

		if ( ! $response ) {
			$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
		}

		if ( $response->errordescr != '' ) {
			$this->Helper->wp_send_json_error( $response );
		}

		if ( $response->keyreceived ) {

			$user_data = $this->SnapID->perform_matchtouser( $loginaccessidentifier, $snapidkey, $keycheckid, '', '', '' );

			if ( isset( $_SESSION['snapid_login']['two_step_user_proxy_check'] ) ) {
				// Check that the userproxy we have matches the user who is logging in.
				if ( $_SESSION['snapid_login']['two_step_user_proxy_check'] !== $user_data->userproxy ) {
					unset( $_SESSION['snapid_login'] ); // Done with this session now, so let's get rid of it.
					$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, this does not match the phone we have on record.' ) );
				}
			}

			if ( $user_data && empty( $user_data->errordescr ) ) {
				$this->snapid_login_user( $user_data, $response );
				$response->errordescr = ''; // Success! Logging you in to WordPress.
			} else if ( ! empty( $user_data->errordescr ) && ! $user_data->userexists ) {
				$this->Helper->wp_send_json_error( array( 'errordescr' => 'Oops - you don\'t have a SnapID&trade; account yet. Login normally and follow instructions in the "Profile" section to sign up.' ) );
			} else if ( ! empty( $user_data->errordescr ) ) {
				$this->Helper->wp_send_json_error( array( 'errordescr' => $user_data->errordescr ) );
			} else {
				$this->Helper->wp_send_json_error( array( 'errordescr' => 'Sorry, something went wrong...' ) );
			}
		}

		$this->Helper->wp_send_json_success( $response );
	}

	/**
	 * Shutdown notice of SnapID plugin.
	 */
	public function plugin_shutdown_notice()
	{
		?>
		<div class="notice notice-error">
			<p>Thank you for choosing SnapID&trade; for Two-Factor Authentication. <strong>Unfortunately, we will be shutting down this free service as of May 1, 2020.</strong> Please disable this plugin prior to that date to prevent issues. We apologize for the inconvenience.</p>
		</div>
		<?php
	}

}

// EOF
