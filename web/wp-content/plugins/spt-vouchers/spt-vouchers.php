<?php
/**
 * Plugin Name: SPT Vouchers
 * Description: Gestion securisee des vouchers prepayes SPT pour WooCommerce.
 * Version: 0.3.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Author: Tealforge
 * Text Domain: spt-vouchers
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('SPT_VOUCHERS_VERSION', '0.3.0');
define('SPT_VOUCHERS_FILE', __FILE__);
define('SPT_VOUCHERS_DIR', plugin_dir_path(__FILE__));
define('SPT_VOUCHERS_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'SptVouchers\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = SPT_VOUCHERS_DIR . 'src/' . $relative . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

register_activation_hook(__FILE__, [SptVouchers\Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [SptVouchers\Plugin::class, 'deactivate']);

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            SPT_VOUCHERS_FILE,
            true
        );
    }
});

add_action('plugins_loaded', [SptVouchers\Plugin::class, 'boot']);
