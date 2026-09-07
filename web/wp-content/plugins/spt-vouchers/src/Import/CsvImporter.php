<?php

declare(strict_types=1);

namespace SptVouchers\Import;

use DateTimeImmutable;
use SptVouchers\Database\VoucherRepository;
use SptVouchers\ProductCatalog;
use SptVouchers\Settings;
use SplFileObject;
use Throwable;
use WP_Error;

defined('ABSPATH') || exit;

final class CsvImporter
{
    public function __construct(private readonly VoucherRepository $repository)
    {
    }

    /** @return array<string, int|string>|WP_Error */
    public function import(string $path, int $productId, string $sku, string $sourceName): array|WP_Error
    {
        if (!$this->repository->isEncryptionConfigured()) {
            return new WP_Error(
                'encryption_key_missing',
                __('La cle de chiffrement doit etre configuree avant tout import.', 'spt-vouchers')
            );
        }

        if (!is_file($path) || !is_readable($path)) {
            return new WP_Error('csv_unreadable', __('Le fichier CSV est introuvable ou illisible.', 'spt-vouchers'));
        }

        if (strtolower(pathinfo($sourceName, PATHINFO_EXTENSION)) !== 'csv') {
            return new WP_Error('invalid_extension', __('Le fichier doit utiliser l extension .csv.', 'spt-vouchers'));
        }

        $detectedSku = ProductCatalog::skuFromFilename($sourceName);

        if ($detectedSku !== null && $detectedSku !== $sku) {
            return new WP_Error(
                'filename_product_mismatch',
                __('Le nom du fichier correspond a une autre recharge que le champ selectionne.', 'spt-vouchers')
            );
        }

        $maxBytes = Settings::getInt('max_file_size_mb') * MB_IN_BYTES;

        if ($maxBytes > 0 && filesize($path) > $maxBytes) {
            return new WP_Error('csv_too_large', __('Le fichier CSV depasse la taille maximale autorisee.', 'spt-vouchers'));
        }

        $fileHash = hash_file('sha256', $path);

        if ($fileHash === false) {
            return new WP_Error('csv_hash_failed', __('Impossible de calculer l empreinte du fichier.', 'spt-vouchers'));
        }

        $importId = $this->repository->createImport($productId, $sourceName, $fileHash);

        if (is_wp_error($importId)) {
            return $importId;
        }

        $stats = ['total' => 0, 'imported' => 0, 'skipped' => 0, 'failed' => 0];
        $errors = [];

        try {
            $file = new SplFileObject($path, 'r');
            $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
            $file->setCsvControl('|', '"', '\\');
            $line = 0;

            while (!$file->eof()) {
                $line++;
                $values = $file->fgetcsv();

                if (!is_array($values) || $values === [null] || $this->isEmptyRow($values)) {
                    continue;
                }

                $stats['total']++;
                $validation = $this->validateRow($values, $line);

                if (is_wp_error($validation)) {
                    $stats['failed']++;
                    $errors[] = $validation->get_error_message();
                    continue;
                }

                [$code, $serialNumber, $expiresAt] = $validation;
                $result = $this->repository->insert(
                    $productId,
                    $code,
                    $serialNumber,
                    $expiresAt,
                    $sourceName,
                    false
                );

                if (is_wp_error($result)) {
                    if (in_array($result->get_error_code(), ['duplicate_voucher', 'duplicate_serial'], true)) {
                        $stats['skipped']++;
                    } else {
                        $stats['failed']++;
                        $errors[] = sprintf(__('Ligne %1$d : %2$s', 'spt-vouchers'), $line, $result->get_error_message());
                    }
                    continue;
                }

                $stats['imported']++;
            }

            $this->repository->syncProductStock($productId);
            $status = $stats['failed'] > 0 ? 'completed_errors' : 'completed';
            $this->repository->finishImport($importId, [
                'status' => $status,
                'rows_total' => $stats['total'],
                'rows_imported' => $stats['imported'],
                'rows_skipped' => $stats['skipped'],
                'rows_failed' => $stats['failed'],
                'error_summary' => implode("\n", array_slice($errors, 0, 100)),
            ]);

            return array_merge($stats, ['status' => $status, 'import_id' => $importId]);
        } catch (Throwable $exception) {
            $this->repository->finishImport($importId, [
                'status' => 'failed',
                'rows_total' => $stats['total'],
                'rows_imported' => $stats['imported'],
                'rows_skipped' => $stats['skipped'],
                'rows_failed' => $stats['failed'],
                'error_summary' => sanitize_textarea_field($exception->getMessage()),
            ]);

            return new WP_Error('csv_import_failed', $exception->getMessage());
        }
    }

    /** @param array<int, mixed> $values
     *  @return array{string, string, string}|WP_Error
     */
    private function validateRow(array $values, int $line): array|WP_Error
    {
        if (count($values) !== 3) {
            return new WP_Error('invalid_columns', sprintf(__('Ligne %d : trois colonnes sont attendues.', 'spt-vouchers'), $line));
        }

        $code = trim((string) $values[0]);
        $serialNumber = trim((string) $values[1]);
        $expiresAt = trim((string) $values[2]);

        if (!preg_match('/^\d{14}$/', $code)) {
            return new WP_Error('invalid_code', sprintf(__('Ligne %d : le voucher doit contenir 14 chiffres.', 'spt-vouchers'), $line));
        }

        if (!preg_match('/^\d{12}$/', $serialNumber)) {
            return new WP_Error('invalid_serial', sprintf(__('Ligne %d : le numero de serie doit contenir 12 chiffres.', 'spt-vouchers'), $line));
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expiresAt);
        $dateErrors = DateTimeImmutable::getLastErrors();

        if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $expiresAt) {
            return new WP_Error('invalid_expiration', sprintf(__('Ligne %d : la date d expiration doit suivre le format AAAA-MM-JJ.', 'spt-vouchers'), $line));
        }

        $today = new DateTimeImmutable(current_time('Y-m-d', true));

        if ($date < $today) {
            return new WP_Error('expired_voucher', sprintf(__('Ligne %d : le voucher est deja expire.', 'spt-vouchers'), $line));
        }

        return [$code, $serialNumber, $expiresAt];
    }

    /** @param array<int, mixed> $values */
    private function isEmptyRow(array $values): bool
    {
        return count(array_filter($values, static fn ($value): bool => trim((string) $value) !== '')) === 0;
    }
}
