<?php

declare(strict_types=1);

namespace SptVouchers;

use SptVouchers\Admin\Menu;
use SptVouchers\Database\Schema;
use SptVouchers\Database\VoucherRepository;
use SptVouchers\Email\VoucherEmail;
use SptVouchers\Import\CsvImporter;
use SptVouchers\Security\Cipher;
use SptVouchers\WooCommerce\Integration;

defined('ABSPATH') || exit;

final class Plugin
{
    private const DAILY_HOOK = 'spt_vouchers_daily_stock_refresh';
    private const CATALOG_VERSION_OPTION = 'spt_vouchers_catalog_version';

    public static function boot(): void
    {
        Schema::maybeUpgrade();

        $cipher = new Cipher();
        $repository = new VoucherRepository($cipher);
        $importer = new CsvImporter($repository);

        add_action(self::DAILY_HOOK, [self::class, 'refreshCatalogStocks']);
        self::scheduleDailyRefresh();
        add_action('wp_loaded', [self::class, 'maybeRefreshCatalogStocks']);

        (new Integration($repository))->register();
        (new VoucherEmail($repository))->register();

        if (is_admin()) {
            (new Menu($repository, $importer, $cipher))->register();
        }
    }

    public static function activate(): void
    {
        Settings::installDefaults();
        Schema::install();
        self::addCapabilities();
        self::unscheduleLegacyScan();
        self::scheduleDailyRefresh();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::DAILY_HOOK);
        self::unscheduleLegacyScan();
    }

    public static function refreshCatalogStocks(): bool
    {
        $repository = new VoucherRepository(new Cipher());
        $repository->expireVouchers();
        $allProductsSynced = true;

        foreach (array_keys(ProductCatalog::all()) as $sku) {
            $productId = ProductCatalog::productId($sku);

            if (is_wp_error($productId)) {
                $allProductsSynced = false;
                continue;
            }

            update_post_meta($productId, '_spt_vouchers_managed', 'yes');
            $repository->syncProductStock($productId);
        }

        return $allProductsSynced;
    }

    public static function maybeRefreshCatalogStocks(): void
    {
        if (
            get_option(self::CATALOG_VERSION_OPTION) === SPT_VOUCHERS_VERSION
            && self::catalogIsManaged()
        ) {
            return;
        }

        if (self::refreshCatalogStocks()) {
            update_option(self::CATALOG_VERSION_OPTION, SPT_VOUCHERS_VERSION, false);
        }
    }

    private static function catalogIsManaged(): bool
    {
        foreach (array_keys(ProductCatalog::all()) as $sku) {
            $productId = ProductCatalog::productId($sku);

            if (
                is_wp_error($productId)
                || get_post_meta($productId, '_spt_vouchers_managed', true) !== 'yes'
            ) {
                return false;
            }
        }

        return true;
    }

    private static function scheduleDailyRefresh(): void
    {
        if (!wp_next_scheduled(self::DAILY_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::DAILY_HOOK);
        }
    }

    private static function unscheduleLegacyScan(): void
    {
        wp_clear_scheduled_hook('spt_vouchers_scan_drop_folder');
    }

    private static function addCapabilities(): void
    {
        foreach (['administrator', 'shop_manager'] as $roleName) {
            $role = get_role($roleName);
            $role?->add_cap('manage_spt_vouchers');
        }
    }
}
