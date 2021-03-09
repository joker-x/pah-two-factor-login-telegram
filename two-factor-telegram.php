<?php

/**
 * Plugin Name: PAH WP 2FA with Telegram
 * Plugin URI: https://github.com/joker-x/pah-two-factor-login-telegram
 * Description: This plugin enables two factor authentication with Telegram by increasing your website security and sends an alert every time a wrong login occurs.
 * Version: 1.9.1
 * Requires at least: 4.7.4
 * Requires PHP: 7.0
 * Author: dueclic
 * Author URI: https://github.com/joker-x
 * Text Domain: two-factor-login-telegram
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

__(
    'This plugin enables two factor authentication with Telegram by increasing your website security and sends an alert every time a wrong login occurs.',
    'two-factor-login-telegram'
);

error_reporting(E_ERROR);

if ( ! defined('ABSPATH')) {
    die;
}

/**
 *
 * Full path to the WP Two Factor Telegram File
 *
 */

define('WP_FACTOR_TG_FILE', __FILE__);

define('WP_FACTOR_TG_GETME_TRANSIENT', 'tg_wp_factor_valid_bot');

/**
 *
 * The main plugin class
 *
 */

require_once("includes/class-wp-factor-telegram-plugin.php");

function WFT()
{
    return WP_Factor_Telegram_Plugin::get_instance();
}

WFT();

