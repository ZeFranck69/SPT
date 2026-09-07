<?php

declare(strict_types=1);

namespace SptVouchers;

use WP_Error;

defined('ABSPATH') || exit;

final class ProductCatalog
{
    /** @return array<string, array{label: string, file_prefix: string}> */
    public static function all(): array
    {
        return [
            'papito-voix-1000' => ['label' => 'Papito Voix 1 000 F', 'file_prefix' => 'PAPITO_1000_'],
            'papito-voix-3000' => ['label' => 'Papito Voix 3 000 F', 'file_prefix' => 'PAPITO_3000_'],
            'papito-voix-5000' => ['label' => 'Papito Voix 5 000 F', 'file_prefix' => 'PAPITO_5000_'],
            'neti-data-1000' => ['label' => 'Neti Data 1 000 F', 'file_prefix' => 'NETI_1000_'],
            'neti-data-3000' => ['label' => 'Neti Data 3 000 F', 'file_prefix' => 'NETI_3000_'],
            'neti-data-5000' => ['label' => 'Neti Data 5 000 F', 'file_prefix' => 'NETI_5000_'],
        ];
    }

    public static function productId(string $sku): int|WP_Error
    {
        if (!isset(self::all()[$sku]) || !function_exists('wc_get_product_id_by_sku')) {
            return new WP_Error('unknown_product', __('Produit de recharge inconnu.', 'spt-vouchers'));
        }

        $productId = (int) wc_get_product_id_by_sku($sku);

        if ($productId <= 0 || !wc_get_product($productId)) {
            return new WP_Error(
                'product_not_found',
                sprintf(__('Le produit WooCommerce avec le SKU %s est introuvable.', 'spt-vouchers'), $sku)
            );
        }

        return $productId;
    }

    public static function skuFromFilename(string $filename): ?string
    {
        if (!preg_match('/^(PAPITO|NETI)_(1000|3000|5000)_/i', basename($filename), $matches)) {
            return null;
        }

        $family = strtoupper($matches[1]);
        $amount = $matches[2];

        return $family === 'PAPITO' ? "papito-voix-{$amount}" : "neti-data-{$amount}";
    }
}
