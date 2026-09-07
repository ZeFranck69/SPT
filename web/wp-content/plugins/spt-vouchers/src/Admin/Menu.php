<?php

declare(strict_types=1);

namespace SptVouchers\Admin;

use SptVouchers\Database\VoucherRepository;
use SptVouchers\Import\CsvImporter;
use SptVouchers\ProductCatalog;
use SptVouchers\Security\Cipher;
use SptVouchers\Settings;

defined('ABSPATH') || exit;

final class Menu
{
    public function __construct(
        private readonly VoucherRepository $repository,
        private readonly CsvImporter $importer,
        private readonly Cipher $cipher
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenus']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_post_spt_vouchers_import', [$this, 'handleImport']);
        add_action('admin_notices', [$this, 'configurationNotice']);
    }

    public function addMenus(): void
    {
        add_menu_page(
            __('Vouchers SPT', 'spt-vouchers'),
            __('Vouchers SPT', 'spt-vouchers'),
            'manage_spt_vouchers',
            'spt-vouchers',
            [$this, 'renderDashboard'],
            'dashicons-tickets-alt',
            56
        );
        add_submenu_page('spt-vouchers', __('Tableau de bord', 'spt-vouchers'), __('Tableau de bord', 'spt-vouchers'), 'manage_spt_vouchers', 'spt-vouchers', [$this, 'renderDashboard']);
        add_submenu_page('spt-vouchers', __('Vouchers', 'spt-vouchers'), __('Vouchers', 'spt-vouchers'), 'manage_spt_vouchers', 'spt-vouchers-list', [$this, 'renderVouchers']);
        add_submenu_page('spt-vouchers', __('Importer', 'spt-vouchers'), __('Importer', 'spt-vouchers'), 'manage_spt_vouchers', 'spt-vouchers-imports', [$this, 'renderImports']);
        add_submenu_page('spt-vouchers', __('Reglages', 'spt-vouchers'), __('Reglages', 'spt-vouchers'), 'manage_spt_vouchers', 'spt-vouchers-settings', [$this, 'renderSettings']);
    }

    public function registerSettings(): void
    {
        register_setting('spt_vouchers', Settings::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [Settings::class, 'sanitize'],
            'default' => Settings::defaults(),
        ]);
    }

    public function enqueueAssets(string $hook): void
    {
        if (!str_contains($hook, 'spt-vouchers')) {
            return;
        }

        wp_enqueue_style('spt-vouchers-admin', SPT_VOUCHERS_URL . 'assets/admin.css', [], SPT_VOUCHERS_VERSION);
    }

    public function configurationNotice(): void
    {
        if (!current_user_can('manage_spt_vouchers') || $this->cipher->isConfigured()) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('SPT Vouchers : configurez SPT_VOUCHERS_ENCRYPTION_KEY dans wp-config.php avant tout import.', 'spt-vouchers');
        echo '</p></div>';
    }

    public function handleImport(): void
    {
        $this->guard();
        check_admin_referer('spt_vouchers_import');

        if (!$this->cipher->isConfigured()) {
            $this->redirectWithNotice('error', __('La cle de chiffrement doit etre configuree avant tout import.', 'spt-vouchers'));
        }

        $files = $_FILES['spt_voucher_files'] ?? null;

        if (!is_array($files)) {
            $this->redirectWithNotice('error', __('Aucun fichier n a ete transmis.', 'spt-vouchers'));
        }

        $messages = [];
        $errors = [];
        $processed = 0;

        foreach (ProductCatalog::all() as $sku => $config) {
            $uploadError = (int) ($files['error'][$sku] ?? UPLOAD_ERR_NO_FILE);

            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($uploadError !== UPLOAD_ERR_OK) {
                $errors[] = sprintf(__('%s : echec du televersement (code %d).', 'spt-vouchers'), $config['label'], $uploadError);
                continue;
            }

            $tmpName = (string) ($files['tmp_name'][$sku] ?? '');
            $sourceName = sanitize_file_name((string) ($files['name'][$sku] ?? ''));

            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                $errors[] = sprintf(__('%s : fichier temporaire invalide.', 'spt-vouchers'), $config['label']);
                continue;
            }

            $productId = ProductCatalog::productId($sku);

            if (is_wp_error($productId)) {
                $errors[] = $productId->get_error_message();
                continue;
            }

            $result = $this->importer->import($tmpName, $productId, $sku, $sourceName);

            if (is_wp_error($result)) {
                $errors[] = sprintf(__('%1$s : %2$s', 'spt-vouchers'), $config['label'], $result->get_error_message());
                continue;
            }

            $processed++;
            $messages[] = sprintf(
                __('%1$s : %2$d importe(s), %3$d doublon(s), %4$d erreur(s).', 'spt-vouchers'),
                $config['label'],
                $result['imported'],
                $result['skipped'],
                $result['failed']
            );
        }

        if ($processed === 0 && $errors === []) {
            $errors[] = __('Selectionnez au moins un fichier CSV.', 'spt-vouchers');
        }

        $type = $errors === [] ? 'success' : ($processed > 0 ? 'warning' : 'error');
        $this->redirectWithNotice($type, implode(' ', array_merge($messages, $errors)));
    }

    public function renderDashboard(): void
    {
        $this->guard();
        $stocks = $this->repository->stockSummary();
        ?>
        <div class="wrap spt-vouchers-admin">
            <h1><?php esc_html_e('Vouchers SPT', 'spt-vouchers'); ?></h1>
            <div class="spt-vouchers-admin__stats">
                <?php $this->stat(__('Disponibles', 'spt-vouchers'), $this->repository->count('available')); ?>
                <?php $this->stat(__('Reserves', 'spt-vouchers'), $this->repository->count('reserved')); ?>
                <?php $this->stat(__('Vendus', 'spt-vouchers'), $this->repository->count('sold')); ?>
                <?php $this->stat(__('Expires', 'spt-vouchers'), $this->repository->count('expired')); ?>
            </div>

            <h2><?php esc_html_e('Stocks par recharge', 'spt-vouchers'); ?></h2>
            <?php $this->renderStockTable($stocks); ?>

            <div class="spt-vouchers-admin__notice">
                <strong><?php esc_html_e('Attribution des codes', 'spt-vouchers'); ?></strong>
                <p><?php esc_html_e('Les emails et SMS contenant les codes ne sont pas encore actifs. L attribution automatique reste desactivee tant que le paiement n est pas valide en recette.', 'spt-vouchers'); ?></p>
            </div>
        </div>
        <?php
    }

    public function renderVouchers(): void
    {
        $this->guard();
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        $page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $rows = $this->repository->list($page, 50, $status);
        ?>
        <div class="wrap spt-vouchers-admin">
            <h1><?php esc_html_e('Vouchers', 'spt-vouchers'); ?></h1>
            <ul class="subsubsub">
                <?php foreach (['' => __('Tous', 'spt-vouchers'), 'available' => __('Disponibles', 'spt-vouchers'), 'reserved' => __('Reserves', 'spt-vouchers'), 'sold' => __('Vendus', 'spt-vouchers'), 'expired' => __('Expires', 'spt-vouchers')] as $key => $label) : ?>
                    <li><a class="<?php echo $status === $key ? 'current' : ''; ?>" href="<?php echo esc_url(add_query_arg(['page' => 'spt-vouchers-list', 'status' => $key], admin_url('admin.php'))); ?>"><?php echo esc_html($label); ?></a> | </li>
                <?php endforeach; ?>
            </ul>
            <table class="widefat striped spt-vouchers-admin__table">
                <thead><tr><th>ID</th><th><?php esc_html_e('Produit', 'spt-vouchers'); ?></th><th><?php esc_html_e('Code masque', 'spt-vouchers'); ?></th><th><?php esc_html_e('Numero de serie', 'spt-vouchers'); ?></th><th><?php esc_html_e('Expiration', 'spt-vouchers'); ?></th><th><?php esc_html_e('Statut', 'spt-vouchers'); ?></th><th><?php esc_html_e('Commande', 'spt-vouchers'); ?></th><th><?php esc_html_e('Fichier', 'spt-vouchers'); ?></th></tr></thead>
                <tbody>
                <?php if ($rows === []) : ?><tr><td colspan="8"><?php esc_html_e('Aucun voucher.', 'spt-vouchers'); ?></td></tr><?php endif; ?>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $row['id']); ?></td>
                        <td><?php echo esc_html(get_the_title((int) $row['product_id']) ?: '#' . $row['product_id']); ?></td>
                        <td><code><?php echo esc_html('**********' . $row['code_last4']); ?></code></td>
                        <td><code><?php echo esc_html((string) $row['serial_number']); ?></code></td>
                        <td><?php echo esc_html((string) $row['expires_at']); ?></td>
                        <td><span class="spt-vouchers-admin__status spt-vouchers-admin__status--<?php echo esc_attr((string) $row['status']); ?>"><?php echo esc_html((string) $row['status']); ?></span></td>
                        <td><?php echo $row['order_id'] ? esc_html('#' . $row['order_id']) : '&ndash;'; ?></td>
                        <td><?php echo esc_html((string) $row['source_file']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function renderImports(): void
    {
        $this->guard();
        $this->renderQueryNotice();
        $rows = $this->repository->listImports();
        ?>
        <div class="wrap spt-vouchers-admin">
            <h1><?php esc_html_e('Importer les vouchers', 'spt-vouchers'); ?></h1>
            <p><?php esc_html_e('Chaque fichier doit contenir, sans en-tete : voucher|numero de serie|date d expiration.', 'spt-vouchers'); ?></p>
            <form class="spt-vouchers-admin__import-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="spt_vouchers_import">
                <?php wp_nonce_field('spt_vouchers_import'); ?>
                <div class="spt-vouchers-admin__import-grid">
                    <?php foreach (ProductCatalog::all() as $sku => $config) : ?>
                        <?php $productId = ProductCatalog::productId($sku); ?>
                        <div class="spt-vouchers-admin__import-card">
                            <label for="spt-file-<?php echo esc_attr($sku); ?>"><?php echo esc_html($config['label']); ?></label>
                            <span><?php echo esc_html($config['file_prefix'] . '...csv'); ?></span>
                            <input id="spt-file-<?php echo esc_attr($sku); ?>" type="file" name="spt_voucher_files[<?php echo esc_attr($sku); ?>]" accept=".csv,text/csv,text/plain" <?php disabled(is_wp_error($productId)); ?>>
                            <?php if (is_wp_error($productId)) : ?><p class="description spt-vouchers-admin__error"><?php echo esc_html($productId->get_error_message()); ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php submit_button(__('Importer les fichiers selectionnes', 'spt-vouchers'), 'primary', 'submit', false, $this->cipher->isConfigured() ? [] : ['disabled' => 'disabled']); ?>
            </form>

            <h2><?php esc_html_e('Historique des imports', 'spt-vouchers'); ?></h2>
            <table class="widefat striped spt-vouchers-admin__table">
                <thead><tr><th>ID</th><th><?php esc_html_e('Produit', 'spt-vouchers'); ?></th><th><?php esc_html_e('Fichier', 'spt-vouchers'); ?></th><th><?php esc_html_e('Statut', 'spt-vouchers'); ?></th><th><?php esc_html_e('Total', 'spt-vouchers'); ?></th><th><?php esc_html_e('Importes', 'spt-vouchers'); ?></th><th><?php esc_html_e('Doublons', 'spt-vouchers'); ?></th><th><?php esc_html_e('Erreurs', 'spt-vouchers'); ?></th><th><?php esc_html_e('Date', 'spt-vouchers'); ?></th></tr></thead>
                <tbody>
                <?php if ($rows === []) : ?><tr><td colspan="9"><?php esc_html_e('Aucun import.', 'spt-vouchers'); ?></td></tr><?php endif; ?>
                <?php foreach ($rows as $row) : ?>
                    <tr title="<?php echo esc_attr((string) $row['error_summary']); ?>">
                        <td><?php echo esc_html((string) $row['id']); ?></td>
                        <td><?php echo $row['product_id'] ? esc_html(get_the_title((int) $row['product_id'])) : '&ndash;'; ?></td>
                        <td><?php echo esc_html((string) $row['source_file']); ?></td>
                        <td><?php echo esc_html((string) $row['status']); ?></td>
                        <td><?php echo esc_html((string) $row['rows_total']); ?></td>
                        <td><?php echo esc_html((string) $row['rows_imported']); ?></td>
                        <td><?php echo esc_html((string) $row['rows_skipped']); ?></td>
                        <td><?php echo esc_html((string) $row['rows_failed']); ?></td>
                        <td><?php echo esc_html((string) $row['started_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function renderSettings(): void
    {
        $this->guard();
        $settings = Settings::all();
        ?>
        <div class="wrap spt-vouchers-admin">
            <h1><?php esc_html_e('Reglages SPT Vouchers', 'spt-vouchers'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('spt_vouchers'); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><?php esc_html_e('Attribution automatique', 'spt-vouchers'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION); ?>[enable_reservations]" value="1" <?php checked((bool) $settings['enable_reservations']); ?>> <?php esc_html_e('Activer la reservation des vouchers lors de la creation des commandes', 'spt-vouchers'); ?></label><p class="description"><strong><?php esc_html_e('Laisser desactive tant que le paiement et le processus de recette ne sont pas valides.', 'spt-vouchers'); ?></strong></p></td></tr>
                    <tr><th scope="row"><label for="spt-max-size"><?php esc_html_e('Taille maximale par fichier', 'spt-vouchers'); ?></label></th><td><input id="spt-max-size" type="number" min="1" max="100" name="<?php echo esc_attr(Settings::OPTION); ?>[max_file_size_mb]" value="<?php echo esc_attr((string) $settings['max_file_size_mb']); ?>"> Mo</td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /** @param array<int, array<string, int|string>> $stocks */
    private function renderStockTable(array $stocks): void
    {
        $indexed = [];

        foreach ($stocks as $stock) {
            $indexed[(int) $stock['product_id']] = $stock;
        }

        echo '<table class="widefat striped spt-vouchers-admin__table"><thead><tr><th>' . esc_html__('Produit', 'spt-vouchers') . '</th><th>' . esc_html__('Disponibles', 'spt-vouchers') . '</th><th>' . esc_html__('Reserves', 'spt-vouchers') . '</th><th>' . esc_html__('Vendus', 'spt-vouchers') . '</th><th>' . esc_html__('Expires', 'spt-vouchers') . '</th></tr></thead><tbody>';

        foreach (ProductCatalog::all() as $sku => $config) {
            $productId = ProductCatalog::productId($sku);
            $stock = !is_wp_error($productId) && isset($indexed[$productId]) ? $indexed[$productId] : [];
            echo '<tr><td>' . esc_html($config['label']) . '</td><td>' . esc_html((string) ($stock['available'] ?? 0)) . '</td><td>' . esc_html((string) ($stock['reserved'] ?? 0)) . '</td><td>' . esc_html((string) ($stock['sold'] ?? 0)) . '</td><td>' . esc_html((string) ($stock['expired'] ?? 0)) . '</td></tr>';
        }

        echo '</tbody></table>';
    }

    private function stat(string $label, int $value): void
    {
        echo '<div class="spt-vouchers-admin__stat"><strong>' . esc_html((string) $value) . '</strong><span>' . esc_html($label) . '</span></div>';
    }

    private function redirectWithNotice(string $type, string $message): never
    {
        wp_safe_redirect(add_query_arg([
            'page' => 'spt-vouchers-imports',
            'spt_notice_type' => $type,
            'spt_notice' => $message,
        ], admin_url('admin.php')));
        exit;
    }

    private function renderQueryNotice(): void
    {
        if (!isset($_GET['spt_notice'])) {
            return;
        }

        $type = isset($_GET['spt_notice_type']) ? sanitize_key(wp_unslash($_GET['spt_notice_type'])) : 'info';
        $type = in_array($type, ['success', 'warning', 'error', 'info'], true) ? $type : 'info';
        $message = sanitize_text_field(wp_unslash($_GET['spt_notice']));
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    private function guard(): void
    {
        if (!current_user_can('manage_spt_vouchers')) {
            wp_die(esc_html__('Vous n avez pas acces a cette page.', 'spt-vouchers'));
        }
    }
}
