<?php

declare(strict_types=1);

namespace SptVouchers\WooCommerce;

use Exception;
use SptVouchers\Database\VoucherRepository;
use SptVouchers\Settings;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

defined('ABSPATH') || exit;

final class Integration
{
    public function __construct(private readonly VoucherRepository $repository)
    {
    }

    public function register(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_filter('spt_vouchers_available_stock', [$this, 'availableStock'], 10, 2);
        add_filter('woocommerce_is_purchasable', [$this, 'isPurchasable'], 10, 2);
        add_filter('woocommerce_variation_is_purchasable', [$this, 'isPurchasable'], 10, 2);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateAddToCart'], 10, 5);
        add_filter('woocommerce_order_item_quantity', [$this, 'preventNativeStockReduction'], 10, 3);
        add_filter('woocommerce_order_item_needs_processing', [$this, 'orderItemNeedsProcessing'], 10, 3);
        add_action('woocommerce_checkout_order_created', [$this, 'reserveOrder']);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'reserveOrder']);
        add_action('woocommerce_pre_payment_complete', [$this, 'completeOrderBeforePayment']);
        add_action('woocommerce_payment_complete', [$this, 'completeOrder']);
        add_action('woocommerce_order_status_processing', [$this, 'completeOrder'], 1);
        add_action('woocommerce_order_status_completed', [$this, 'completeOrder'], 1);
        add_action('woocommerce_order_status_failed', [$this, 'releaseOrder']);
        add_action('woocommerce_order_status_cancelled', [$this, 'releaseOrder']);
    }

    public function availableStock(mixed $stock, int $productId): mixed
    {
        if (!$this->isManagedProduct($productId)) {
            return $stock;
        }

        return $this->repository->availableCount($productId);
    }

    public function isPurchasable(bool $purchasable, WC_Product $product): bool
    {
        if (!$this->isManagedProduct($product->get_id())) {
            return $purchasable;
        }

        return $purchasable
            && Settings::getBool('enable_reservations')
            && $this->repository->availableCount($product->get_id()) > 0
            && $this->repository->canDecryptAvailableVoucher($product->get_id());
    }

    /** @param array<string, mixed> $variations */
    public function validateAddToCart(
        bool $passed,
        int $productId,
        int|float $quantity,
        int $variationId = 0,
        array $variations = []
    ): bool {
        $targetId = $variationId > 0 ? $variationId : $productId;

        if (!$this->isManagedProduct($targetId)) {
            return $passed;
        }

        if (!Settings::getBool('enable_reservations')) {
            wc_add_notice(__('La vente de cette recharge est temporairement desactivee.', 'spt-vouchers'), 'error');

            return false;
        }

        if (!$this->repository->canDecryptAvailableVoucher($targetId)) {
            wc_add_notice(__('Cette recharge est temporairement indisponible. Contactez le support.', 'spt-vouchers'), 'error');

            return false;
        }

        if ((int) $quantity > $this->repository->availableCount($targetId)) {
            wc_add_notice(__('Le stock de vouchers est insuffisant pour cette quantite.', 'spt-vouchers'), 'error');

            return false;
        }

        return $passed;
    }

    public function preventNativeStockReduction(int|float $quantity, WC_Order $order, WC_Order_Item_Product $item): int|float
    {
        $product = $item->get_product();

        return $product && $this->isManagedProduct($product->get_id()) ? 0 : $quantity;
    }

    public function orderItemNeedsProcessing(bool $needsProcessing, WC_Product $product, int $orderId): bool
    {
        return $this->isManagedProduct($product->get_id()) ? false : $needsProcessing;
    }

    /** @throws Exception */
    public function reserveOrder(WC_Order $order): void
    {
        if (!Settings::getBool('enable_reservations') || $order->get_meta('_spt_vouchers_reserved') === 'yes') {
            return;
        }

        $reservedAny = false;

        foreach ($order->get_items('line_item') as $itemId => $item) {
            $productId = $item->get_variation_id() ?: $item->get_product_id();

            if (!$this->isManagedProduct($productId)) {
                continue;
            }

            if (!$this->repository->canDecryptAvailableVoucher($productId)) {
                $this->repository->releaseOrder($order->get_id());
                throw new Exception(__('Les vouchers disponibles ne peuvent pas etre dechiffres.', 'spt-vouchers'));
            }

            $result = $this->repository->reserve($productId, $item->get_quantity(), $order->get_id(), (int) $itemId);

            if (is_wp_error($result)) {
                $this->repository->releaseOrder($order->get_id());
                $order->add_order_note(sprintf(
                    __('Echec de reservation des vouchers : %s', 'spt-vouchers'),
                    $result->get_error_message()
                ));
                throw new Exception($result->get_error_message());
            }

            $reservedAny = true;
        }

        if ($reservedAny) {
            $order->update_meta_data('_spt_vouchers_reserved', 'yes');
            $order->save_meta_data();
            $order->add_order_note(__('Vouchers reserves. Aucun code n a encore ete communique au client.', 'spt-vouchers'));
        }
    }

    public function completeOrder(int $orderId): void
    {
        if (!Settings::getBool('enable_reservations')) {
            return;
        }

        $order = wc_get_order($orderId);

        if (!$order || !$order->is_paid()) {
            return;
        }

        $this->fulfillOrder($order);
    }

    public function completeOrderBeforePayment(int $orderId): void
    {
        if (!Settings::getBool('enable_reservations')) {
            return;
        }

        $order = wc_get_order($orderId);

        if ($order) {
            $this->fulfillOrder($order);
        }
    }

    /** @throws Exception */
    private function fulfillOrder(WC_Order $order): void
    {
        if (
            $order->get_meta('_spt_vouchers_sold') === 'yes'
            || $order->get_meta('_spt_vouchers_reserved') !== 'yes'
        ) {
            return;
        }

        $expected = 0;

        foreach ($order->get_items('line_item') as $item) {
            $productId = $item->get_variation_id() ?: $item->get_product_id();

            if ($this->isManagedProduct($productId)) {
                $expected += (int) $item->get_quantity();
            }
        }

        $reserved = $this->repository->orderReservedVouchers($order->get_id());

        if ($expected <= 0 || count($reserved) !== $expected) {
            throw new Exception(__('Le nombre de vouchers reserves ne correspond pas a la commande.', 'spt-vouchers'));
        }

        $sold = $this->repository->markOrderSold($order->get_id());

        if ($sold !== $expected) {
            throw new Exception(__('La vente des vouchers n a pas pu etre finalisee integralement.', 'spt-vouchers'));
        }

        $order->update_meta_data('_spt_vouchers_sold', 'yes');
        $order->save_meta_data();
        $order->add_order_note(sprintf(
            _n('%d voucher marque vendu.', '%d vouchers marques vendus.', $sold, 'spt-vouchers'),
            $sold
        ));
    }

    public function releaseOrder(int $orderId): void
    {
        if (!Settings::getBool('enable_reservations')) {
            return;
        }

        $released = $this->repository->releaseOrder($orderId);

        if ($released <= 0) {
            return;
        }

        $order = wc_get_order($orderId);

        if ($order) {
            $order->delete_meta_data('_spt_vouchers_reserved');
            $order->save_meta_data();
            $order->add_order_note(sprintf(
                _n('%d voucher libere.', '%d vouchers liberes.', $released, 'spt-vouchers'),
                $released
            ));
        }
    }

    private function isManagedProduct(int $productId): bool
    {
        return get_post_meta($productId, '_spt_vouchers_managed', true) === 'yes';
    }
}
