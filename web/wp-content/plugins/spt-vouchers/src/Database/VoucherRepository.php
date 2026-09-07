<?php

declare(strict_types=1);

namespace SptVouchers\Database;

use RuntimeException;
use SptVouchers\Security\Cipher;
use Throwable;
use WP_Error;

defined('ABSPATH') || exit;

final class VoucherRepository
{
    public function __construct(private readonly Cipher $cipher)
    {
    }

    public function isEncryptionConfigured(): bool
    {
        return $this->cipher->isConfigured();
    }

    public function insert(
        int $productId,
        string $code,
        string $serialNumber,
        string $expiresAt,
        string $sourceFile,
        bool $syncStock = true
    ): int|WP_Error {
        global $wpdb;

        if ($productId <= 0 || trim($code) === '' || trim($serialNumber) === '' || $expiresAt === '') {
            return new WP_Error('invalid_voucher', __('Les donnees du voucher sont incompletes.', 'spt-vouchers'));
        }

        $codeHash = $this->cipher->fingerprint($code);

        try {
            $inserted = $wpdb->insert(
                Schema::table('codes'),
                [
                    'product_id' => $productId,
                    'code_ciphertext' => $this->cipher->encrypt($code),
                    'code_hash' => $codeHash,
                    'code_last4' => $this->cipher->lastFour($code),
                    'serial_number' => $serialNumber,
                    'expires_at' => $expiresAt,
                    'source_file' => sanitize_file_name($sourceFile),
                    'batch_reference' => pathinfo(sanitize_file_name($sourceFile), PATHINFO_FILENAME),
                    'status' => 'available',
                    'created_at' => current_time('mysql', true),
                    'updated_at' => current_time('mysql', true),
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        } catch (Throwable $exception) {
            return new WP_Error('voucher_insert_failed', $exception->getMessage());
        }

        if ($inserted === false) {
            if ($this->codeExists($codeHash)) {
                return new WP_Error('duplicate_voucher', __('Ce numero de voucher existe deja.', 'spt-vouchers'));
            }

            if ($this->serialExists($serialNumber)) {
                return new WP_Error('duplicate_serial', __('Ce numero de serie existe deja.', 'spt-vouchers'));
            }

            return new WP_Error('voucher_insert_failed', __('Le voucher n a pas pu etre enregistre.', 'spt-vouchers'));
        }

        update_post_meta($productId, '_spt_vouchers_managed', 'yes');

        if ($syncStock) {
            $this->syncProductStock($productId);
        }

        return (int) $wpdb->insert_id;
    }

    /** @return array<int, array<string, mixed>> */
    public function list(int $page = 1, int $perPage = 50, string $status = ''): array
    {
        global $wpdb;

        $this->expireVouchers();
        $table = Schema::table('codes');
        $offset = max(0, ($page - 1) * $perPage);
        $where = '';
        $params = [];

        if (in_array($status, ['available', 'reserved', 'sold', 'expired'], true)) {
            $where = 'WHERE status = %s';
            $params[] = $status;
        }

        $sql = "SELECT id, product_id, code_last4, serial_number, expires_at, source_file,
                batch_reference, status, order_id, reserved_at, sold_at, created_at
            FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
        $params[] = $perPage;
        $params[] = $offset;

        return $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];
    }

    public function count(string $status = ''): int
    {
        global $wpdb;

        $this->expireVouchers();
        $table = Schema::table('codes');

        if (in_array($status, ['available', 'reserved', 'sold', 'expired'], true)) {
            return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", $status));
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    public function availableCount(int $productId): int
    {
        global $wpdb;

        $this->expireVouchers();
        $today = current_time('Y-m-d', true);

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Schema::table('codes') . "
                WHERE product_id = %d AND status = 'available'
                AND (expires_at IS NULL OR expires_at >= %s)",
            $productId,
            $today
        ));
    }

    /** @return array<int, array<string, int|string>> */
    public function stockSummary(): array
    {
        global $wpdb;

        $this->expireVouchers();
        $table = Schema::table('codes');

        return $wpdb->get_results(
            "SELECT product_id,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) AS available,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) AS reserved,
                SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) AS sold,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) AS expired
            FROM {$table} GROUP BY product_id ORDER BY product_id ASC",
            ARRAY_A
        ) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function listImports(int $limit = 50): array
    {
        global $wpdb;

        $table = Schema::table('imports');

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit),
            ARRAY_A
        ) ?: [];
    }

    public function createImport(int $productId, string $filename, string $hash): int|WP_Error
    {
        global $wpdb;

        $inserted = $wpdb->insert(
            Schema::table('imports'),
            [
                'product_id' => $productId,
                'source_file' => sanitize_file_name($filename),
                'file_hash' => $hash,
                'status' => 'processing',
                'created_by' => get_current_user_id() ?: null,
                'started_at' => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s']
        );

        if ($inserted === false) {
            $code = str_contains(strtolower($wpdb->last_error), 'duplicate') ? 'duplicate_file' : 'import_log_failed';

            return new WP_Error($code, __('Ce fichier a deja ete importe ou ne peut pas etre journalise.', 'spt-vouchers'));
        }

        return (int) $wpdb->insert_id;
    }

    /** @param array<string, int|string|null> $data */
    public function finishImport(int $importId, array $data): void
    {
        global $wpdb;

        $wpdb->update(
            Schema::table('imports'),
            array_merge($data, ['finished_at' => current_time('mysql', true)]),
            ['id' => $importId]
        );
    }

    public function expireVouchers(): int
    {
        global $wpdb;

        $table = Schema::table('codes');
        $today = current_time('Y-m-d', true);
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'expired', updated_at = %s
                WHERE status = 'available' AND expires_at IS NOT NULL AND expires_at < %s",
            current_time('mysql', true),
            $today
        ));

        return max(0, (int) $updated);
    }

    public function reserve(int $productId, int $quantity, int $orderId, int $orderItemId): true|WP_Error
    {
        global $wpdb;

        if ($quantity <= 0) {
            return true;
        }

        $this->expireVouchers();
        $table = Schema::table('codes');
        $today = current_time('Y-m-d', true);
        $wpdb->query('START TRANSACTION');

        try {
            $alreadyReserved = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE order_item_id = %d AND status IN ('reserved','sold')",
                $orderItemId
            ));

            if ($alreadyReserved === $quantity) {
                $wpdb->query('COMMIT');

                return true;
            }

            if ($alreadyReserved > 0) {
                throw new RuntimeException(__('Attribution partielle detectee pour cette ligne de commande.', 'spt-vouchers'));
            }

            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$table} WHERE product_id = %d AND status = 'available'
                    AND (expires_at IS NULL OR expires_at >= %s)
                    ORDER BY expires_at ASC, id ASC LIMIT %d FOR UPDATE",
                $productId,
                $today,
                $quantity
            ));

            if (count($ids) !== $quantity) {
                throw new RuntimeException(__('Stock de vouchers insuffisant.', 'spt-vouchers'));
            }

            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $now = current_time('mysql', true);
            $params = array_merge([$orderId, $orderItemId, $now, $now], array_map('intval', $ids));
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET status = 'reserved', order_id = %d, order_item_id = %d,
                    reserved_at = %s, updated_at = %s WHERE id IN ({$placeholders}) AND status = 'available'",
                ...$params
            ));

            if ($updated !== $quantity) {
                throw new RuntimeException(__('La reservation atomique des vouchers a echoue.', 'spt-vouchers'));
            }

            $wpdb->query('COMMIT');
            $this->syncProductStock($productId);

            return true;
        } catch (Throwable $exception) {
            $wpdb->query('ROLLBACK');

            return new WP_Error('voucher_reservation_failed', $exception->getMessage());
        }
    }

    public function markOrderSold(int $orderId): int
    {
        global $wpdb;

        $table = Schema::table('codes');
        $productIds = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT product_id FROM {$table} WHERE order_id = %d AND status = 'reserved'",
            $orderId
        ));
        $now = current_time('mysql', true);
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'sold', sold_at = %s, updated_at = %s
                WHERE order_id = %d AND status = 'reserved'",
            $now,
            $now,
            $orderId
        ));

        foreach ($productIds as $productId) {
            $this->syncProductStock((int) $productId);
        }

        return max(0, (int) $updated);
    }

    public function releaseOrder(int $orderId): int
    {
        global $wpdb;

        $table = Schema::table('codes');
        $productIds = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT product_id FROM {$table} WHERE order_id = %d AND status = 'reserved'",
            $orderId
        ));
        $today = current_time('Y-m-d', true);
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = CASE WHEN expires_at IS NOT NULL AND expires_at < %s THEN 'expired' ELSE 'available' END,
                order_id = NULL, order_item_id = NULL, reserved_at = NULL, updated_at = %s
                WHERE order_id = %d AND status = 'reserved'",
            $today,
            current_time('mysql', true),
            $orderId
        ));

        foreach ($productIds as $productId) {
            $this->syncProductStock((int) $productId);
        }

        return max(0, (int) $updated);
    }

    /** @return array<int, string> */
    public function orderCodes(int $orderId): array
    {
        global $wpdb;

        $rows = $wpdb->get_col($wpdb->prepare(
            'SELECT code_ciphertext FROM ' . Schema::table('codes') . " WHERE order_id = %d AND status = 'sold' ORDER BY id ASC",
            $orderId
        ));
        $codes = [];

        foreach ($rows as $payload) {
            $codes[] = $this->cipher->decrypt((string) $payload);
        }

        return $codes;
    }

    public function syncProductStock(int $productId): void
    {
        if (!function_exists('wc_get_product')) {
            return;
        }

        $available = $this->availableCount($productId);
        $product = wc_get_product($productId);

        if (!$product) {
            return;
        }

        $product->set_manage_stock(true);
        $product->set_stock_quantity($available);
        $product->set_stock_status($available > 0 ? 'instock' : 'outofstock');
        $product->save();
    }

    private function codeExists(string $codeHash): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . Schema::table('codes') . ' WHERE code_hash = %s LIMIT 1',
            $codeHash
        ));
    }

    private function serialExists(string $serialNumber): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . Schema::table('codes') . ' WHERE serial_number = %s LIMIT 1',
            $serialNumber
        ));
    }
}
