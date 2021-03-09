<?php

final class WP_Factor_Telegram_Plugin {

	/**
	 * Get an instance
	 *
	 * @var WP_Factor_Telegram_Plugin
	 */

	private static $instance;

	/**
	 * Namespace for prefixed setting
	 *
	 * @var string
	 */

	private $namespace = "tg_col";

	/**
	 * Cookie Name
	 *
	 * @var string
	 */

	private $cookie_name = "auth_tg_cookie";

	/**
	 * Check cookie Name
	 *
	 * @var string
	 */

	private $check_cookie_name = "check_tg_cookie";

	/**
	 * @var WP_Telegram
	 */

	private $telegram;

	/**
	 * Get WP Factor Telegram
	 *
	 * @return WP_Factor_Telegram_Plugin
	 */

	public static function get_instance() {
		if ( empty( self::$instance )
		     && ! ( self::$instance instanceof WP_Factor_Telegram_Plugin )
		) {
			self::$instance = new WP_Factor_Telegram_Plugin;
			self::$instance->includes();
			self::$instance->telegram = new WP_Telegram;
			self::$instance->add_hooks();

			do_action( "wp_factor_telegram_loaded" );
		}

		return self::$instance;
	}

	/**
	 * Include classes
	 */

	public function includes() {
		require_once( dirname( WP_FACTOR_TG_FILE )
		              . "/includes/class-wp-telegram.php" );
	}


	/**
	 * Get authentication code
	 *
	 * @param  int  $length
	 *
	 * @return string
	 */

	private function get_auth_code( $length = 8 ) {
		$pool = array_merge( range( 0, 9 ), range( 'a', 'z' );

		$key = "";

		for ( $i = 0; $i < $length; $i ++ ) {
			$key .= $pool[ mt_rand( 0, count( $pool ) - 1 ) ];
		}

		return $key;
	}

	/**
	 * Show to factor login html
	 *
	 * @param $user
	 */

	private function show_two_factor_login( $user ) {
		$auth_code = $this->get_auth_code();

		setcookie( $this->cookie_name, null, strtotime( '-1 day' ) );
		setcookie( $this->cookie_name, sha1( $auth_code ),
			time() + ( 60 * 20 ) );

		$chat_id = $this->get_user_chatid( $user->ID );
		$this->telegram->send_tg_token( $auth_code, $chat_id );

		$redirect_to = isset( $_REQUEST['redirect_to'] )
			? $_REQUEST['redirect_to'] : $_SERVER['REQUEST_URI'];

		$this->login_html( $user, $redirect_to );
	}

	/**
	 * Authentication page
	 *
	 * @param $user
	 */

	private function authentication_page( $user ) {
		require_once( ABSPATH . '/wp-admin/includes/template.php' );
		?>

        <p class="notice notice-warning">
			<?php
			_e( "Enter the code sent to your Telegram account.",
				"two-factor-login-telegram" );
			?>
        </p>
        <p>
            <label for="authcode" style="padding-top:1em">
				<?php
				_e( "Authentication code:", "two-factor-login-telegram" );
				?>
            </label>
            <input type="text" name="authcode" id="authcode" class="input"
                   value="" size="5"/>
        </p>
		<?php
		submit_button( __( 'Login with Telegram',
			'two-factor-login-telegram' ) );
	}

	/**
	 * Login HTML Page
	 *
	 * @param          $user
	 * @param          $redirect_to
	 * @param  string  $error_msg
	 */

	private function login_html( $user, $redirect_to, $error_msg = '' ) {
		$rememberme = 0;
		if ( isset( $_REQUEST['rememberme'] ) && $_REQUEST['rememberme'] ) {
			$rememberme = 1;
		}

		login_header();

		if ( ! empty( $error_msg ) ) {
			echo '<div id="login_error"><strong>' . esc_html( $error_msg )
			     . '</strong><br /></div>';
		}
		// Filter hook to add a custom logo to the 2FA login screen
		$plugin_logo = apply_filters( 'two_factor_login_telegram_logo',
			plugins_url( 'assets/img/plugin_logo.png', WP_FACTOR_TG_FILE ) );
		?>

        <style>
            body.login div#login h1 a {
                background-image: url("<?php echo $plugin_logo; ?>");
            }
        </style>

        <form name="validate_tg" id="loginform" action="<?php
		echo esc_url( site_url( 'wp-login.php?action=validate_tg',
			'login_post' ) ); ?>" method="post" autocomplete="off">
            <input type="hidden" name="wp-auth-id" id="wp-auth-id" value="<?php
			echo esc_attr( $user->ID ); ?>"/>
            <input type="hidden" name="redirect_to" value="<?php
			echo esc_attr( $redirect_to ); ?>"/>
            <input type="hidden" name="rememberme" id="rememberme" value="<?php
			echo esc_attr( $rememberme ); ?>"/>

			<?php
			$this->authentication_page( $user ); ?>
        </form>

        <p id="backtoblog">
            <a href="<?php
			echo esc_url( home_url( '/' ) ); ?>" title="<?php
			__( "Are you lost?", "two-factor-login-telegram" ); ?>"><?php
				echo sprintf( __( '&larr; Back to %s',
					'two-factor-login-telegram' ),
					get_bloginfo( 'title', 'display' ) ); ?></a>
        </p>

		<?php
		do_action( 'login_footer' ); ?>
        <div class="clear"></div>
        </body>

        </html>
		<?php
	}

	/**
	 * Show telegram login
	 *
	 * @param $user_login
	 * @param $user
	 */

	public function tg_login( $user_login, $user ) {
		if ( get_option( $this->namespace )['enabled'] === '1'
		     && get_the_author_meta( "tg_wp_factor_enabled", $user->ID ) === "1"
		) {
			wp_clear_auth_cookie();

			$this->show_two_factor_login( $user );
			exit;
		}
	}

	private function is_valid_authcode( $authcode, $cookie = false ) {
		if ( $cookie === false ) {
			$cookie = $this->cookie_name;
		}

		if ( $_COOKIE[ $cookie ] === sha1( $authcode ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate telegram auth code login
	 */

	public function validate_tg() {
		if ( ! isset( $_POST['wp-auth-id'] ) ) {
			return;
		}

		$user = get_userdata( $_POST['wp-auth-id'] );
		if ( ! $user ) {
			return;
		}

		if ( true !== $this->is_valid_authcode( $_REQUEST['authcode'] ) ) {
			do_action( 'wp_factor_telegram_failed', $user->user_login );

			$auth_code = $this->get_auth_code();

			setcookie( $this->cookie_name, null, strtotime( '-1 day' ) );
			setcookie( $this->cookie_name, sha1( $auth_code ),
				time() + ( 60 * 20 ) );

			$chat_id = $this->get_user_chatid( $user->ID );
			$this->telegram->send_tg_token( $auth_code, $chat_id );

			$this->login_html( $user, $_REQUEST['redirect_to'],
				__( 'Wrong verification code, we just sent a new code, please try again!',
					'two-factor-login-telegram' ) );
			exit;
		}

		$rememberme = false;
		if ( isset( $_REQUEST['rememberme'] ) && $_REQUEST['rememberme'] ) {
			$rememberme = true;
		}

		wp_set_auth_cookie( $user->ID, $rememberme );

		$redirect_to = apply_filters( 'login_redirect',
			$_REQUEST['redirect_to'], $_REQUEST['redirect_to'], $user );
		wp_safe_redirect( $redirect_to );

		exit;
	}

	public function configure_tg() {
		require( dirname( WP_FACTOR_TG_FILE ) . "/sections/configure_tg.php" );
	}

	public function tg_load_menu() {
		add_options_page( __( "Two-Factor Authentication with Telegram",
			"two-factor-login-telegram" ),
			__( "Two-Factor Authentication with Telegram",
				"two-factor-login-telegram" ), "manage_options", "tg-conf",
			array(
				$this,
				"configure_tg",
			) );
	}

	function delete_transients( $option_name, $old_value, $new_value ) {
		if ( $this->namespace === $option_name ) {
			delete_transient( WP_FACTOR_TG_GETME_TRANSIENT );
		}
	}

	function tg_register_settings() {
		register_setting( $this->namespace, $this->namespace, '' );

		add_settings_section( $this->namespace . '_section',
			__( 'Telegram Configuration', "two-factor-login-telegram" ), '',
			$this->namespace . '.php' );

		$field_args = array(
			'type'      => 'text',
			'id'        => 'bot_token',
			'name'      => 'bot_token',
			'desc'      => __( 'Bot Token', "two-factor-login-telegram" ),
			'std'       => '',
			'label_for' => 'bot_token',
			'class'     => 'css_class',
		);

		add_settings_field( 'bot_token',
			__( 'Bot Token', "two-factor-login-telegram" ), array(
				$this,
				'tg_display_setting',
			), $this->namespace . '.php', $this->namespace . '_section',
			$field_args );

		$field_args = array(
			'type'      => 'text',
			'id'        => 'chat_id',
			'name'      => 'chat_id',
			'desc'      => __( 'Chat ID (Telegram) for failed login report.',
				"two-factor-login-telegram" ),
			'std'       => '',
			'label_for' => 'chat_id',
			'class'     => 'css_class',
		);

		add_settings_field( 'chat_id',
			__( 'Chat ID', "two-factor-login-telegram" ), array(
				$this,
				'tg_display_setting',
			), $this->namespace . '.php', $this->namespace . '_section',
			$field_args );

		$field_args = array(
			'type'      => 'checkbox',
			'id'        => 'enabled',
			'name'      => 'enabled',
			'desc'      => __( 'Select this checkbox to enable the plugin.',
				'two-factor-login-telegram' ),
			'std'       => '',
			'label_for' => 'enabled',
			'class'     => 'css_class',
		);

        $field_args = array(
                'type'      => 'checkbox',
                'id'        => 'telegram_webhook_enabled',
                'name'      => 'telegram_webhook_enabled',
                'desc'      => __( 'Activa webhook de Telegram para este bot.',
                        'two-factor-login-telegram' ),
                'std'       => '',
                'label_for' => 'telegram_webhook_enabled',
                'class'     => 'css_class',
        );
        add_settings_field( 'telegram_webhook_enabled',
                __( 'Activar webhook', 'two-factor-login-telegram' ), array(
                        $this,
                        'tg_display_setting',
                ), $this->namespace . '.php', $this->namespace . '_section',
                $field_args );

        $field_args = array(
        'type'      => 'text',
        'id'        => 'telegram_webhook',
        'name'      => 'telegram_webhook',
        'desc'      => __( 'URL generada para el webhook',
                       "two-factor-login-telegram" ),
                'std'       => '',
                'label_for' => 'telegram_webhook',
                'class'     => 'css_class',
        );
        add_settings_field( 'telegram_webhook',
                __( 'URL webhook', "two-factor-login-telegram" ), array(
                        $this,
                       'tg_display_setting',
                ), $this->namespace . '.php', $this->namespace . '_section',
	            $field_args );


		add_settings_field( 'enabled',
			__( 'Enable plugin?', 'two-factor-login-telegram' ), array(
				$this,
				'tg_display_setting',
			), $this->namespace . '.php', $this->namespace . '_section',
			$field_args );

		$field_args = array(
			'type'      => 'checkbox',
			'id'        => 'show_site_name',
			'name'      => 'show_site_name',
			'desc'      => __( 'Select this checkbox to show site name on failed login attempt message.<br>This option is useful when you use same bot in several sites.',
				'two-factor-login-telegram' ),
			'std'       => '',
			'label_for' => 'show_site_name',
			'class'     => 'css_class',
		);

		add_settings_field( 'show_site_name',
			__( 'Show site name?', 'two-factor-login-telegram' ), array(
				$this,
				'tg_display_setting',
			), $this->namespace . '.php', $this->namespace . '_section',
			$field_args );

		$field_args = array(
			'type'      => 'checkbox',
			'id'        => 'show_site_url',
			'name'      => 'show_site_url',
			'desc'      => __( 'Select this checkbox to show site URL on failed login attempt message.<br>This option is useful when you use same bot in several sites.',
				'two-factor-login-telegram' ),
			'std'       => '',
			'label_for' => 'show_site_url',
			'class'     => 'css_class',
		);

		add_settings_field( 'show_site_url',
			__( 'Show site URL?', 'two-factor-login-telegram' ), array(
				$this,
				'tg_display_setting',
			), $this->namespace . '.php', $this->namespace . '_section',
			$field_args );
	}

	public function tg_display_setting( $args ) {
		extract( $args );

		$option_name = $this->namespace;

		$options                = get_option( $option_name );

		/** @var $type */
		/** @var $id */
		/** @var $desc */
		/** @var $class */

		switch ( $type ) {
			case 'text':
				$options[ $id ] = stripslashes( $options[ $id ] );
				$options[ $id ] = esc_attr( $options[ $id ] );
				echo "<input class='regular-text $class' type='text' id='$id' name='"
				     . $option_name . "[$id]' value='$options[$id]' />";

				if ( $id == "bot_token" ) {
					?>
                    <button id="checkbot" class="button-secondary"
                            type="button"><?php
						echo __( "Check",
							"two-factor-login-telegram" ) ?></button>
					<?php
				}

				echo ( $desc != '' )
					? '<br /><p class="wft-settings-description" id="' . $id
					  . '_desc">' . $desc . '</p>' : "";
				break;

			case 'checkbox':

				$options[ $id ] = stripslashes( $options[ $id ] );
				$options[ $id ] = esc_attr( $options[ $id ] );
				?>
                <label for="<?php
				echo $id; ?>">
                    <input class="regular-text <?php
					echo $class; ?>" type="checkbox" id="<?php
					echo $id; ?>" name="<?php
					echo $option_name; ?>[<?php
					echo $id; ?>]" value="1" <?php
					echo checked( 1, $options[ $id ] ); ?> />
					<?php
					echo ( $desc != '' ) ? $desc : ""; ?>
                </label>
				<?php
				break;
			case 'textarea':

				wp_editor(
					$options[ $id ],
					$id,
					array(
						'textarea_name' => $option_name . "[$id]",
						'style'         => 'width: 200px',
					)
				);

				break;
		}
	}

	/**
	 * Action links
	 *
	 * @param $links
	 *
	 * @return array
	 */

	public function action_links( $links ) {
		/** @noinspection PhpUndefinedConstantInspection */

		$plugin_links = array(
			'<a href="' . admin_url( 'options-general.php?page=tg-conf' ) . '">'
			. __( 'Settings', 'two-factor-login-telegram' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	public function settings_error_set_chatid() {
		if ( get_current_screen()->id != "profile" ) {
			?>
            <div class="notice notice-warning is-dismissible">
                <p><?php
					printf( __( 'Do you want to configure Two Factor Authentication with Telegram?  <a href="%s">click here</a>!',
						"two-factor-login-telegram" ),
						admin_url( "profile.php" ) );
					?></p>
            </div>
			<?php
		}
	}

	public function settings_error_not_valid_bot() {
		if ( get_current_screen()->id != "settings_page_tg-conf" ) {
			?>
            <div class="notice notice-error is-dismissible">
                <p><?php
					printf( __( 'Do you want to configure Two Factor Authentication with Telegram?  <a href="%s">click here</a>!',
						"two-factor-login-telegram" ),
						admin_url( "options-general.php?page=tg-conf" ) ); ?>
                </p>
            </div>
			<?php
		}
	}

	/**
	 * @param $user
	 */

	public function tg_add_two_factor_fields( $user ) {
		?>
        <h3 id="wptl"><?php
			_e( 'Two-Factor Authentication with Telegram',
				'two-factor-login-telegram' ); ?></h3>

        <table class="form-table">

            <tr>
                <th>
                    <label for="tg_wp_factor_enabled"><?php
						_e( 'Enable Two-Factor Authentication',
							'two-factor-login-telegram' ); ?>
                    </label>
                </th>
                <td colspan="2">
                    <input type="hidden" name="tg_wp_factor_valid"
                           id="tg_wp_factor_valid" value="<?php
					echo (int) ( esc_attr( get_the_author_meta( 'tg_wp_factor_enabled', $user->ID ) ) === "1" || 
                           $_GET['tg_wp_factor_enabled'] == '1' ); ?>">
                    <input type="checkbox" name="tg_wp_factor_enabled"
                           id="tg_wp_factor_enabled" value="1"
                           class="regular-text" <?php
					echo checked( esc_attr( get_the_author_meta( 'tg_wp_factor_enabled',
						$user->ID ) ), 1 ); ?> /><br/>
                </td>
            </tr>

            <tr>

                <td colspan="3">

					<?php
					$username = $this->telegram->get_me()->username;
					?>

                    <!--<div>

                        <ol>
                            <li>
								<?php
								printf( __( 'Open Telegram and start a conversation with %s',
									"two-factor-login-telegram" ),
									'<a href="https://telegram.me/WordPressLoginBot" target="_blank">@WordpressLoginBot</a>' );
								?>
                            </li>

                            <li>
								<?php
								printf( __( 'Type command %s to obtain your Chat ID.',
									"two-factor-login-telegram" ),
									'<code>/get_id</code>' );
								?>
                            </li>
                            <li>
								<?php
								_e( "Inside of the answer you'll find your <strong>Chat ID</strong>",
									'two-factor-login-telegram' );
								?>
                            </li>

                            <li><?php
								printf( __( 'Now, open a conversation with %s and press on <strong>Start</strong>',
									'two-factor-login-telegram' ),
									'<a href="https://telegram.me/' . $username
									. '">@' . $username . '</a>' );
								?></li>
                            <li><?php
								_e( 'You can go :) Insert your Chat ID and press <strong>Submit code</strong>',
									'two-factor-plugin' ); ?></li>
                        </ol>

                        </p>
                    </div>-->
                </td>

            </tr>

            <tr>
                <th>
                    <label for="tg_wp_factor_chat_id"><?php
						_e( 'Telegram Chat ID',
							'two-factor-login-telegram' ); ?>
                    </label></th>
                <td>
                    <input type="text" name="tg_wp_factor_chat_id"
	                    id="tg_wp_factor_chat_id" value="<?php
			                if (is_numeric($_GET['tg_wp_factor_chat_id'])) {
				                echo esc_attr($_GET['tg_wp_factor_chat_id']);
			                } else {
				                echo esc_attr( get_the_author_meta( 'tg_wp_factor_chat_id',
					                $user->ID ) ); 
			                }?>" class="regular-text"/><br/>
                    <span class="description"><?php
						_e( 'Put your Telegram Chat ID',
							'two-factor-login-telegram' ); ?></span>
                </td>
                <td>
                    <button class="button" id="tg_wp_factor_chat_id_send"><?php
						_e( "Submit code",
							"two-factor-login-telegram" ); ?></button>
                </td>
            </tr>

            <tr id="factor-chat-confirm">
                <th>
                    <label for="tg_wp_factor_chat_id_confirm"><?php
						_e( 'Confirmation code',
							'two-factor-login-telegram' ); ?>
                    </label></th>
                <td>
                    <input type="text" name="tg_wp_factor_chat_id_confirm"
                           id="tg_wp_factor_chat_id_confirm" value=""
                           class="regular-text"/><br/>
                    <span class="description"><?php
						_e( 'Please enter the confirmation code you received on Telegram',
							'two-factor-login-telegram' ); ?></span>
                </td>
                <td>
                    <button class="button" id="tg_wp_factor_chat_id_check"><?php
						_e( "Validate",
							"two-factor-login-telegram" ); ?></button>
                </td>
            </tr>
            <tr id="factor-chat-response">
                <td colspan="3">
                    <div class="wpft-notice wpft-notice-warning">
                        <p></p>
                    </div>
                </td>
            </tr>
        </table>
		<?php
	}

	public function load_tg_lib() {
		$screen = get_current_screen();
		if ( in_array( $screen->id, [ "profile", "settings_page_tg-conf" ] ) ) {
			wp_register_style( "tg_lib_css",
				plugins_url( "assets/css/wp-factor-telegram-plugin.css",
					dirname( __FILE__ ) ) );
			wp_enqueue_style( "tg_lib_css" );

			wp_register_script( "tg_lib_js",
				plugins_url( "assets/js/wp-factor-telegram-plugin.js",
					dirname( __FILE__ ) ), array( 'jquery' ), '1.0.0', true );

			wp_localize_script( "tg_lib_js", "tlj", array(

				"ajax_error" => __( 'Ooops! Server failure, try again! ',
					'two-factor-login-telegram' ),
				"spinner"    => admin_url( "/images/spinner.gif" ),

			) );

			wp_enqueue_script( "tg_lib_js" );

			wp_enqueue_script( 'jquery-ui-accordion' );
			wp_enqueue_script(
				'custom-accordion',
				plugins_url( 'assets/js/wp-factor-telegram-accordion.js',
					dirname( __FILE__ ) ),
				array( 'jquery' )
			);

			wp_register_style( 'jquery-custom-style',
				plugins_url( '/assets/jquery-ui-1.11.4.custom/jquery-ui.css',
					dirname( __FILE__ ) ), array(), '1', 'screen' );
			wp_enqueue_style( 'jquery-custom-style' );
		}
	}

	public function hook_tg_lib() {
		$screen = get_current_screen();
		if ( in_array( $screen->id, [ "profile", "settings_page_tg-conf" ] ) ):

			?>

            <script>
                (function ($) {

                    $(document).ready(function () {
                        WP_Factor_Telegram_Plugin.init();
$("#telegram_webhook").prop('readonly', true);
$("#telegram_webhook_enabled").on( 'change', function() {
  var bot_token = $('#bot_token').val();
  if( $(this).is(':checked') && bot_token != "" ) {
    $("#telegram_webhook").val("<?php echo get_site_url()."/wp-json/telegram/"; ?>"+bot_token+"/");    
  } else {
    $("#telegram_webhook").val("");
  }
});
                    });

                })(jQuery);
            </script>

		<?php
		endif;
	}

	public function send_token_check() {
		$response = array(
			'type' => 'error',
			'msg'  => __( 'Please fill Chat ID field.',
				'two-factor-login-telegram' ),
		);


		if ( empty( $_POST['chat_id'] ) || !is_numeric($_POST['chat_id'] )) {
			die( json_encode( $response ) );
		}

		$auth_code = $this->get_auth_code();

		setcookie( $this->check_cookie_name, null, strtotime( '-1 day' ) );
		setcookie( $this->check_cookie_name, sha1( $auth_code ),
			time() + ( 60 * 20 ) );

		$tg = $this->telegram;
		$send
		    = $tg->send( sprintf( __( "This is the validation code to use WP Two Factor with Telegram: %s",
			"two-factor-login-telegram" ), "<code>".$auth_code."</code>" ), $_POST['chat_id'] );

		if ( ! $send ) {
			$response['msg']
				= sprintf( __( "Error (%s): validation code was not sent, try again!",
				'two-factor-login-telegram' ), $tg->lastError );
		} else {
			$response['type'] = "success";
			$response['msg']  = __( "Validation code was successfully sent",
				'two-factor-login-telegram' );
		}

		die( json_encode( $response ) );
	}

	public function check_bot() {
		$response = array(
			'type' => 'error',
			'msg'  => __( 'This bot does not exists.',
				'two-factor-login-telegram' ),
		);

		if ( ! isset( $_POST['bot_token'] ) || $_POST['bot_token'] == "" ) {
			die( json_encode( $response ) );
		}

		$tg = $this->telegram;
		$me = $tg->set_bot_token( $_POST['bot_token'] )->get_me();

		if ( $me === false ) {
			die( json_encode( $response ) );
		}

		$response = array(
			'type' => 'success',
			'msg'  => __( 'This bot exists.', 'two-factor-login-telegram' ),
			'args' => array(
				'id'         => $me->id,
				'first_name' => $me->first_name,
				'username'   => $me->username,
			),
		);

		die( json_encode( $response ) );
	}

	public function send_email() {
		$response = array(
			'type' => 'error',
			'msg'  => __( 'Ooops! Server failure, try again!',
				'two-factor-login-telegram' ),
		);

		$request
			= wp_remote_post( "https://www.dueclic.com/plugins/collect_data.php",
			array(
				'body' => array(
					'your_email'   => $_POST['your_email'],
					'your_name'    => $_POST['your_name'],
					'your_message' => $_POST['your_message'],
					'auth_key'     => 'sendEmail',
					'plugin_name'  => 'wtfawt',
				),
			) );

		if ( ! is_wp_error( $request ) ) {
			$api = json_decode( wp_remote_retrieve_body( $request ), true );

			if ( $api['type'] == "success" ) {
				$response['type'] = "success";
				$response['msg']  = __( 'Thanks for the support.',
					'two-factor-login-telegram' );
				die( json_encode( $response ) );
			}

			switch ( $api['msg'] ) {
				case "missing_name":
					$api['msg'] = __( 'Error: Please, insert a valid name.',
						'two-factor-login-telegram' );
					break;
				case "server_failure":
					$api['msg'] = __( 'Ooops! Server failure, try again!',
						'two-factor-login-telegram' );
					break;
				case "email_wrong":
					$api['msg'] = __( 'Error: Please, insert a valid email.',
						'two-factor-login-telegram' );
					break;
			}
		}


		die( json_encode( $response ) );
	}

	public function token_check() {
		$response = array(
			'type' => 'error',
			'msg'  => __( 'The token entered is wrong.',
				'two-factor-login-telegram' ),
		);


		if ( ! isset( $_POST['token'] ) || $_POST['token'] == "" ) {
			die( json_encode( $response ) );
		}


		if ( ! $this->is_valid_authcode( $_POST['token'],
			$this->check_cookie_name )
		) {
			$response['msg'] = __( 'Validation code entered is wrong.',
				'two-factor-login-telegram' );
		} else {
			$response['type'] = "success";
			$response['msg']  = __( "Validation code is correct.",
				'two-factor-login-telegram' );
		}

		die( json_encode( $response ) );
	}

	public function tg_save_custom_user_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		if ( $_POST['tg_wp_factor_valid'] == 0
		     || $_POST['tg_wp_factor_chat_id'] == ""
		) {
			//return false;
		}

		update_user_meta( $user_id, 'tg_wp_factor_chat_id',
			$_POST['tg_wp_factor_chat_id'] );
		update_user_meta( $user_id, 'tg_wp_factor_enabled',
			$_POST['tg_wp_factor_enabled'] );

		return true;
	}

	public function is_valid_bot() {
		$valid_bot_transient = WP_FACTOR_TG_GETME_TRANSIENT;

		if ( ( $is_valid_bot = get_transient( $valid_bot_transient ) )
		     === false
		) {
			$is_valid_bot = $this->telegram->get_me() !== false;
			var_dump( $is_valid_bot );
			set_transient( $valid_bot_transient, $is_valid_bot, 60 * 60 * 24 );
		}

		return boolval( $is_valid_bot );
	}

	public function get_user_chatid( $user_id = false ) {
		if ( $user_id === false ) {
			$user_id = get_current_user_id();
		}

		return get_the_author_meta( "tg_wp_factor_chat_id", $user_id );
	}

	public function is_setup_chatid( $user_id = false ) {
		$chat_id = $this->get_user_chatid( $user_id );

		return $chat_id !== false;
	}


	function ts_footer_admin_text() {
		return __( ' | This plugin is powered by', 'two-factor-login-telegram' )
		       . ' <a href="https://www.dueclic.com/" target="_blank">dueclic</a>. <a class="social-foot" href="https://www.facebook.com/dueclic/"><span class="dashicons dashicons-facebook bg-fb"></span></a>';
	}

	function ts_footer_version() {
		return "";
	}

	public function change_copyright() {
		add_filter( 'admin_footer_text', array( $this, 'ts_footer_admin_text' ),
			11 );
		add_filter( 'update_footer', array( $this, 'ts_footer_version' ), 11 );
	}

	public function activate() {
		$response
			= wp_remote_post( "https://www.dueclic.com/plugins/collect_data.php",
			array(
				'body' => array(
					'auth_key'    => 'collectData',
					'plugin_name' => 'wtfawt',
					'plugin_host' => $_SERVER['HTTP_HOST'],
				),
			) );

		return true;
	}


	/**
	 * Add hooks
	 */

	public function add_hooks() {
		register_activation_hook( WP_FACTOR_TG_FILE,
			array( $this, 'activate' ) );
		add_action( 'wp_login', array( $this, 'tg_login' ), 10, 2 );
		add_action( 'wp_login_failed',
			array( $this->telegram, 'send_tg_failed_login' ), 10, 2 );
		add_action( 'login_form_validate_tg', array( $this, 'validate_tg' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'tg_register_settings' ) );
			add_action( "admin_menu", array( $this, 'tg_load_menu' ) );
			add_filter( "plugin_action_links_"
			            . plugin_basename( WP_FACTOR_TG_FILE ),
				array( $this, 'action_links' ) );
			add_action( 'updated_option', array( $this, 'delete_transients' ),
				10, 3 );
		}

		if ( ! $this->is_valid_bot() ) {
			add_action( 'admin_notices',
				array( $this, 'settings_error_not_valid_bot' ) );
		}

		if ( $this->is_valid_bot() && ! $this->is_setup_chatid() ) {
			add_action( 'admin_notices',
				array( $this, 'settings_error_set_chatid' ) );
		}

		add_action( 'show_user_profile',
			array( $this, 'tg_add_two_factor_fields' ) );
		add_action( 'edit_user_profile',
			array( $this, 'tg_add_two_factor_fields' ) );

		add_action( 'personal_options_update',
			array( $this, 'tg_save_custom_user_profile_fields' ) );
		add_action( 'edit_user_profile_update',
			array( $this, 'tg_save_custom_user_profile_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_tg_lib' ) );
		add_action( 'admin_footer', array( $this, 'hook_tg_lib' ) );
		add_action( 'wp_ajax_send_token_check',
			array( $this, 'send_token_check' ) );
		add_action( 'wp_ajax_token_check', array( $this, 'token_check' ) );
		add_action( 'wp_ajax_check_bot', array( $this, 'check_bot' ) );
		add_action( 'wp_ajax_send_email', array( $this, 'send_email' ) );

		add_action( "tft_copyright", array( $this, "change_copyright" ) );
		
		add_action( 'update_option', array($this, 'set_telegram_webhook'), 10, 3 );

		if ( $this->is_valid_bot() ) {
			add_action( 'rest_api_init', function () {
				register_rest_route('telegram', '/'.get_option( $this->namespace )['bot_token'], array(
					'methods' => WP_REST_Server::ALLMETHODS,
					'callback' => array($this, 'telegram_webhook_endpoint')
				));
			});
		}
	}

	// joker
	public function telegram_webhook_endpoint(WP_REST_REQUEST $request) {
		$user_id=$request['message']['from']['id'];
		$first_name=$request['message']['from']['first_name'];
		if (empty($user_id)) {
			return false;
		}
		$wpusers = new WP_User_Query(array(
			'fields' => 'ID',
			'meta_key' => 'tg_wp_factor_chat_id',
			'meta_value' => $user_id
		));
		$wpusers = $wpusers->get_results();
		$url = get_site_url()."/wp-admin/profile.php?tg_wp_factor_chat_id=".$user_id."&tg_wp_factor_enabled=1#wptl";
		$msg = '<b>¡Hola '.$first_name.'!</b>'."\n\n";
		if (empty($wpusers)) {
			$msg .= '🔴 <b>No existe ningún usuario de wordpress vinculado a este teléfono</b>'."\n\n";
		} else {
			foreach ($wpusers as $wpuser) {
				$wpuser = get_userdata($wpuser);
				$tg_wp_factor_enabled = get_user_meta($wpuser->ID, 'tg_wp_factor_enabled', true);
				if ($tg_wp_factor_enabled == '1') {
					$msg .= '🟢  <b>'.$wpuser->user_email.' está vinculado a este teléfono y tiene activada la autentificación en dos pasos.</b>'."\n\n";
				} else {
					$msg .= '🟠  <b>'.$wpuser->user_email.' está vinculado a este teléfono pero no tiene activada la autentificación en dos pasos.</b>'."\n\n";
				}
			}
		}
		$msg .= 'He sido programado para ayudarte a activar la autentificación en dos pasos con Telegram en la web del <a href="'.get_site_url().'">'.get_bloginfo('name').'</a>.'."\n\n";
	       	$msg .= 'La idea es sencilla: A la hora de entrar en el panel de administración además de tu nombre de usuario y contraseña te pedirá un código que te enviaré a tu teléfono.'."\n\n";
		$msg .= 'El botón <i>"Vincular dispositivo"</i> te llevará a la página de tu perfil. Una vez dentro sólo necesitas hacer click en el botón <i>"Actualizar perfil"</i> situado al final de la página para que quede activo.';
		$this->telegram->send($msg, $user_id, "Vincular dispositivo", $url);
		//error_log( json_encode($request) );
		return true;
	}

	public function set_telegram_webhook( $option_name, $old, $new ){

		if ( !($this->is_valid_bot()) || strcasecmp( $option_name, $this->namespace ) != 0) {
			return;
		}
		if ($old['telegram_webhook'] == $new['telegram_webhook']) {
			return;
		}
		$this->telegram->set_webhook($new['telegram_webhook']);

	}
	
}
