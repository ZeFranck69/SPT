<?php

declare(strict_types=1);

namespace SptVouchers\Database;

defined('ABSPATH') || exit;

final class Schema
{
    private const VERSION = '0.2.0';
    private const OPTION = 'spt_vouchers_db_version';

    public static function table(string $name): string
    {
        global $wpdb;

        return $wpdb->prefix . 'spt_voucher_' . $name;
    }

    public static function maybeUpgrade(): void
    {
        if (get_option(self::OPTION) !== self::VERSION) {
            self::install();
        }
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $vouchers = self::table('codes');
        $imports = self::table('imports');
        $notifications = self::table('notifications');

        dbDelta("CREATE TABLE {$vouchers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            code_ciphertext longtext NOT NULL,
            code_hash char(64) NOT NULL,
            code_last4 varchar(12) NOT NULL DEFAULT '',
            serial_number varchar(64) NULL,
            expires_at date NULL,
            source_file varchar(255) NOT NULL DEFAULT '',
            batch_reference varchar(191) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'available',
            order_id bigint(20) unsigned NULL,
            order_item_id bigint(20) unsigned NULL,
            reserved_at datetime NULL,
            sold_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code_hash (code_hash),
            UNIQUE KEY serial_number (serial_number),
            KEY product_status_expiration (product_id,status,expires_at),
            KEY order_id (order_id),
            KEY order_item_id (order_item_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$imports} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NULL,
            source_file varchar(255) NOT NULL,
            file_hash char(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'processing',
            rows_total int(10) unsigned NOT NULL DEFAULT 0,
            rows_imported int(10) unsigned NOT NULL DEFAULT 0,
            rows_skipped int(10) unsigned NOT NULL DEFAULT 0,
            rows_failed int(10) unsigned NOT NULL DEFAULT 0,
            error_summary longtext NULL,
            created_by bigint(20) unsigned NULL,
            started_at datetime NOT NULL,
            finished_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY file_hash (file_hash),
            KEY product_id (product_id),
            KEY status (status),
            KEY started_at (started_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$notifications} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            channel varchar(20) NOT NULL,
            provider varchar(100) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL,
            external_id varchar(191) NOT NULL DEFAULT '',
            attempt_count int(10) unsigned NOT NULL DEFAULT 1,
            response_summary text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_channel (order_id,channel),
            KEY status (status)
        ) {$charset};");

        update_option(self::OPTION, self::VERSION, false);
    }
}
