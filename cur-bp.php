<?php
/*
Plugin Name: Confirm User Registration - BuddyPress Tweaks
Description: Adds some usability tweaks when using Confirm User Registration with BuddyPress.
Version: 0.1
Author: r-a-y
Author URI: http://profiles.wordpress.org/r-a-y
License: GPLv2 or later
*/

add_action( 'bp_include', array( 'CUR_BP', 'init' ) );

/**
 * Confirm User Registration - BuddyPress Addon.
 */
class CUR_BP {
	/**
	 * Static initializer.
	 */
	public static function init() {
		return new self();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// CUR doesn't exist, so stop now!
		if ( ! class_exists( 'Confirm_User_Registration' ) ) {
			return;
		}

		add_action( 'bp_screens', array( $this, 'modify_bp_activation_message' ), 9 );

		// Set up admin area if in the WP dashboard
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			CUR_BP_Admin_Settings::init();
			add_action( 'confirm-user-registration-auth-user', 'bp_core_new_user_activity' );
		}
	}

	public function modify_bp_activation_message() {
		// stop if not on registration / activation page
		if ( ! bp_is_register_page() && ! bp_is_activation_page() ) {
			return false;
		}

		// registration
		if ( bp_is_register_page() ) {
			// @todo auto-activate account in the future
			//
			// multisite - hook into these filters and grab the key to activate user
			// to disable email as well remember to return false
			//apply_filters('wpmu_signup_user_notification', $user, $user_email, $key
			//apply_filters('wpmu_signup_blog_notification', $domain, $path, $title, $user, $user_email, $key
			//
			// single-site
			// $meta_key = 'activation_key'
			//apply_filters( "update_user_metadata", null, $object_id, $meta_key, $meta_value, $prev_value );
			//
			// disable single-site activation email
			//add_filter( 'bp_core_signup_send_activation_key', '__return_false' );


			// future support to notify admin for single-site
			//add_action( 'bp_core_signup_user', array( $this, 'bp_notify_admin' );

			// alter activation email? might be too confusing...
			//add_filter( 'bp_core_signup_send_validation_email_message',        array( $this, 'modify_registration_email' ) );
			//add_filter( 'bp_core_activation_signup_user_notification_message', array( $this, 'modify_registration_email' ) );

		// activation
		} else {
			add_filter( 'wpmu_welcome_user_notification', '__return_false' );
			add_filter( 'gettext',                        array( $this, 'modify_activation_text' ), 10, 2 );
			add_action( 'bp_after_activate_content',      array( $this, 'add_signup_blurb' ) );

			// do not record "became a registered member" activity item
			// we'll add it back when an admin confirms the user
			remove_action( 'bp_core_activated_user', 'bp_core_new_user_activity' );
		}

	}

	protected function registration_text_addition() {
		$options = self::get_settings();
		return isset( $options['registration_text'] ) ? $options['registration_text'] : false;
	}

	public function add_signup_blurb() {
		if ( ! bp_account_was_activated() ) {
			return;
		}

		echo apply_filters( 'comment_text', $this->registration_text_addition() );
	}

	public function modify_activation_text( $translated_text, $untranslated_text ) {
		switch ( $untranslated_text ) {
			case 'Your account was activated successfully! You can now <a href="%s">log in</a> with the username and password you provided when you signed up.':
			case 'Your account was activated successfully! You can now log in with the username and password you provided when you signed up.' :
				return __( 'Your account was just activated.', 'cur-bp' );
				break;
		}

		/*
		 * Some text that needs to be changed for multisite sans BP.
		 *
		 * Future release stuff!
		 *
		printf( __( '%s is your new username' ), $user_name);
		_e( 'But, before you can start using your new username, <strong>you must activate it</strong>.' );
		printf( __( 'Check your inbox at <strong>%s</strong> and click the link given.' ), $user_email );
		_e( 'If you do not activate your username within two days, you will have to sign up again.' );
		_e('Your account is now active!');
		printf( __('Your account has been activated. You may now <a href="%1$s">log in</a> to the site using your chosen username of &#8220;%2$s&#8221;. Please check your email inbox at %3$s for your password and login instructions. If you do not receive an email, please check your junk or spam folder. If you still do not receive an email within an hour, you can <a href="%4$s">reset your password</a>.'), network_site_url( 'wp-login.php', 'login' ), $signup->user_login, $signup->user_email, wp_lostpassword_url() );
		printf( __('Your site at <a href="%1$s">%2$s</a> is active. You may now log in to your site using your chosen username of &#8220;%3$s&#8221;. Please check your email inbox at %4$s for your password and login instructions. If you do not receive an email, please check your junk or spam folder. If you still do not receive an email within an hour, you can <a href="%5$s">reset your password</a>.'), 'http://' . $signup->domain, $signup->domain, $signup->user_login, $signup->user_email, wp_lostpassword_url() );
		*/

		return $translated_text;
	}

	public function modify_registration_email( $content = '' ) {
		if ( $text = $this->registration_text_addition() ) {
			$content .= "\n\n" . $this->registration_text_addition();
		}

		return $content;
	}

	/**
	 * Get our settings.
	 *
	 * Sets defaults if not set.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = bp_get_option( 'cur_bp' );

		// set defaults
		if ( is_string( $settings ) ) {
			$settings = array();
		}

		// always have an error message
		if ( empty( $settings['registration_text'] ) ) {
			$settings['registration_text'] = __( 'Please note that an administrator still needs to approve your account before you can login.  You will receive an email once your account has been approved.', 'confirm-user-registration' );
		}

		return $settings;
	}

}

/**
 * Admin settings for the plugin.
 */
class CUR_BP_Admin_Settings {

	/**
	 * Static initializer.
	 */
	public static function init() {
		return new self();
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'load-users_page_confirm-user-registration', array( $this, 'screen' ) );
	}

	public function add_fields() {
	?>

		<tr>
			<th colspan="2"><h3><?php _e( 'BuddyPress', 'cur-bp' ); ?></h3></th>
		</tr>

		<!--
		<?PHP // NOT READY YET!! ?>
		<tr>
			<th scope="row"><?php _e( 'Disable Email Activation', 'cur-bp' ); ?></th>
			<td>
				<label><input name="bp-activation" type="checkbox" id="bp-activation" value="1" /> If this is checked, new users do not need to activate their account via email.</label>
				<p class="description">By default, new users need to activate their account via email and an admin will still need to confirm their account before they can login.  To remove the email activation step, check this box.</p>
			</td>
		</tr>
		-->

		<tr>
			<th><label for="registration_text"><?php _e( 'Registration Text', 'cur-bp' )?></label></th>
			<td>
				<textarea name="registration_text" rows="8" cols="80" id="registration_text"><?php echo $this->settings['registration_text']; ?></textarea>
				<p class="description"><?php _e( 'This text is shown after a user activates their account via email to inform the user that their account is still awaiting admin approval.', 'cur-bp' ); ?></p>

			</td>
		</tr>

	<?php
	}

	/**
	 * Sets up the screen.
	 *
	 * This method handles saving for our custom form fields and grabs our settings
	 * only when on the "Users > Confirm User Registration" admin page.
	 */
	public function screen() {
		// not on settings tab? stop now!
		if ( ! isset( $_GET['tab'] ) || 'settings' != $_GET['tab'] ) {
			return;
		}

		// save
		if ( ! empty( $_POST['save-confirm-user-registration-settings-nonce'] ) && wp_verify_nonce( $_POST['save-confirm-user-registration-settings-nonce'] ) ) {

			// sanitize before saving
			$retval = array();
			$retval['registration_text'] = wp_filter_kses( $_POST['registration_text'] );

			bp_update_option( 'cur_bp', $retval );
		}

		// get settings
		$this->settings = CUR_BP::get_settings();

		// add fields
		add_action( 'confirm-user-registration-options', array( $this, 'add_fields' ), 0 );

		//add_action( 'confirm-user-registration-auth-user', array( $this, 'bp_activate_user' ) );
	}

}
