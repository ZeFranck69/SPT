<?php

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

if (!defined('SPT_VOUCHERS_REMOVE_DATA') || constant('SPT_VOUCHERS_REMOVE_DATA') !== true) {
    return;
}

global $wpdb;

foreach (['codes', 'imports', 'notifications'] as $suffix) {
    $table = $wpdb->prefix . 'spt_voucher_' . $suffix;
    $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

delete_option('spt_vouchers_settings');
delete_option('spt_vouchers_db_version');
delete_option('spt_vouchers_catalog_version');

foreach (['administrator', 'shop_manager'] as $roleName) {
    $role = get_role($roleName);
    $role?->remove_cap('manage_spt_vouchers');
}
