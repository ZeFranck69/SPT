<?php

declare(strict_types=1);

namespace SptVouchers\Email;

use DateTimeImmutable;
use SptVouchers\Database\VoucherRepository;
use Throwable;
use WC_Email;
use WC_Order;

defined('ABSPATH') || exit;

final class VoucherEmail
{
    private const CUSTOMER_EMAILS = [
        'customer_processing_order',
        'customer_completed_order',
    ];

    public function __construct(private readonly VoucherRepository $repository)
    {
    }

    public function register(): void
    {
        add_action('woocommerce_email_before_order_table', [$this, 'render'], 8, 4);
    }

    public function render(WC_Order $order, bool $sentToAdmin, bool $plainText, mixed $email): void
    {
        if (
            $sentToAdmin
            || !$email instanceof WC_Email
            || !in_array($email->id, self::CUSTOMER_EMAILS, true)
            || $order->get_meta('_spt_vouchers_sold') !== 'yes'
        ) {
            return;
        }

        try {
            $vouchers = $this->repository->orderVouchers($order->get_id());
        } catch (Throwable $exception) {
            wc_get_logger()->error(
                sprintf('Impossible de dechiffrer les vouchers de la commande #%d pour l email.', $order->get_id()),
                ['source' => 'spt-vouchers', 'exception' => $exception]
            );

            return;
        }

        if ($vouchers === []) {
            return;
        }

        if ($plainText) {
            $this->renderPlainText($order, $vouchers);

            return;
        }

        $this->renderHtml($order, $vouchers);
    }

    /** @param array<int, array{product_id: int, order_item_id: int, code: string, serial_number: string, expires_at: string}> $vouchers */
    private function renderHtml(WC_Order $order, array $vouchers): void
    {
        echo '<div style="margin:0 0 32px;">';
        echo '<h2 style="margin:0 0 8px;color:#1d1b1c;font-size:24px;line-height:1.25;">' . esc_html__('Vos codes de recharge', 'spt-vouchers') . '</h2>';
        echo '<p style="margin:0 0 20px;color:#5f5b5d;line-height:1.6;">' . esc_html__('Conservez ces informations. Chaque code ne peut être utilisé qu’une seule fois.', 'spt-vouchers') . '</p>';

        foreach ($vouchers as $index => $voucher) {
            $productName = $this->productName($order, $voucher['order_item_id'], $voucher['product_id']);
            $expiration = $this->formatExpiration($voucher['expires_at']);

            echo '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 18px;border:1px solid #dedadb;border-top:5px solid #e6141f;border-radius:8px;background:#ffffff;">';
            echo '<tr><td style="padding:20px 22px 16px;">';
            echo '<p style="margin:0 0 5px;color:#e6141f;font-size:12px;font-weight:700;text-transform:uppercase;">' . esc_html(sprintf(__('Recharge %d', 'spt-vouchers'), $index + 1)) . '</p>';
            echo '<h3 style="margin:0;color:#1d1b1c;font-size:22px;line-height:1.3;">' . esc_html($productName) . '</h3>';
            echo '</td></tr>';
            echo '<tr><td style="padding:0 22px 18px;">';
            echo '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;">';
            echo '<tr>';
            echo '<td valign="top" style="width:50%;padding:10px 10px 10px 0;border-top:1px solid #ece9ea;">' . $this->detailHtml(__('N° de série', 'spt-vouchers'), $voucher['serial_number']) . '</td>';
            echo '<td valign="top" style="width:50%;padding:10px 0 10px 10px;border-top:1px solid #ece9ea;">' . $this->detailHtml(__('Expiration', 'spt-vouchers'), $expiration) . '</td>';
            echo '</tr>';
            echo '</table>';
            echo '</td></tr>';
            echo '<tr><td style="padding:0 22px 22px;">';
            echo '<div style="border:2px solid #1d1b1c;border-radius:6px;padding:18px;background:#f7f7f7;text-align:center;">';
            echo '<p style="margin:0 0 8px;color:#5f5b5d;font-size:12px;font-weight:700;text-transform:uppercase;">' . esc_html__('Code voucher', 'spt-vouchers') . '</p>';
            echo '<p style="margin:0;color:#e6141f;font-family:Courier New,Courier,monospace;font-size:25px;font-weight:700;line-height:1.3;overflow-wrap:anywhere;">' . esc_html($voucher['code']) . '</p>';
            echo '</div>';
            echo '</td></tr>';
            echo '</table>';
        }

        echo '<p style="margin:4px 0 0;color:#5f5b5d;font-size:13px;line-height:1.6;">' . esc_html__('Pour votre sécurité, ne transmettez votre code qu’à la personne qui doit utiliser la recharge.', 'spt-vouchers') . '</p>';
        echo '</div>';
    }

    /** @param array<int, array{product_id: int, order_item_id: int, code: string, serial_number: string, expires_at: string}> $vouchers */
    private function renderPlainText(WC_Order $order, array $vouchers): void
    {
        echo "\n" . strtoupper(__('Vos codes de recharge', 'spt-vouchers')) . "\n";
        echo str_repeat('=', 34) . "\n";

        foreach ($vouchers as $index => $voucher) {
            echo sprintf(__('Recharge %1$d : %2$s', 'spt-vouchers'), $index + 1, $this->productName($order, $voucher['order_item_id'], $voucher['product_id'])) . "\n";
            echo __('N° de série', 'spt-vouchers') . ' : ' . $voucher['serial_number'] . "\n";
            echo __('Expiration', 'spt-vouchers') . ' : ' . $this->formatExpiration($voucher['expires_at']) . "\n";
            echo __('Code voucher', 'spt-vouchers') . ' : ' . $voucher['code'] . "\n\n";
        }
    }

    private function detailHtml(string $label, string $value): string
    {
        return '<p style="margin:0 0 4px;color:#767676;font-size:11px;font-weight:700;text-transform:uppercase;">' . esc_html($label) . '</p>'
            . '<p style="margin:0;color:#1d1b1c;font-size:14px;font-weight:700;line-height:1.4;">' . esc_html($value) . '</p>';
    }

    private function productName(WC_Order $order, int $orderItemId, int $productId): string
    {
        $item = $order->get_item($orderItemId);

        if ($item && method_exists($item, 'get_name')) {
            return (string) $item->get_name();
        }

        $product = wc_get_product($productId);

        return $product ? $product->get_name() : __('Recharge SPT', 'spt-vouchers');
    }

    private function formatExpiration(string $expiration): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expiration);

        return $date ? $date->format('d/m/Y') : $expiration;
    }
}
