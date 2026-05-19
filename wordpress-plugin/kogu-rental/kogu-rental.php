<?php
/**
 * Plugin Name: 工具レンタル
 * Plugin URI:  https://your-domain.com
 * Description: インパクトドライバーレンタルサイト — 在庫管理・Stripe決済・メール通知
 * Version:     1.0.0
 * Author:      Your Name
 * Text Domain: kogu-rental
 * Requires Plugins:
 */

defined( 'ABSPATH' ) || exit;

define( 'KOGU_VERSION',    '1.4.0' );
define( 'KOGU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KOGU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// ── Autoload classes ──────────────────────────────────────────────────────────
require_once KOGU_PLUGIN_DIR . 'includes/class-database.php';
require_once KOGU_PLUGIN_DIR . 'includes/class-inventory.php';
require_once KOGU_PLUGIN_DIR . 'includes/class-rental-manager.php';
require_once KOGU_PLUGIN_DIR . 'includes/class-stripe-handler.php';
require_once KOGU_PLUGIN_DIR . 'includes/class-email-handler.php';
require_once KOGU_PLUGIN_DIR . 'includes/class-cron.php';
require_once KOGU_PLUGIN_DIR . 'admin/class-admin.php';
require_once KOGU_PLUGIN_DIR . 'public/class-public.php';

// ── Activation / Deactivation ─────────────────────────────────────────────────
register_activation_hook( __FILE__, [ 'Kogu_Database', 'install' ] );
register_deactivation_hook( __FILE__, [ 'Kogu_Cron', 'deactivate' ] );

// ── Boot ──────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function () {
    Kogu_Admin::init();
    Kogu_Public::init();
    Kogu_Cron::init();
} );
