<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

function tealforge_get_asset_manifest(): array
{
    static $manifest = null;

    if (null !== $manifest) {
        return $manifest;
    }

    $manifest = [];
    $manifest_path = get_theme_file_path('dist/manifest.json');

    if (! is_readable($manifest_path)) {
        return $manifest;
    }

    $manifest_json = file_get_contents($manifest_path);

    if (false === $manifest_json) {
        return $manifest;
    }

    $decoded_manifest = json_decode($manifest_json, true);

    if (is_array($decoded_manifest)) {
        $manifest = $decoded_manifest;
    }

    return $manifest;
}

function tealforge_enqueue_assets(): void
{
    $entry = tealforge_get_asset_manifest()['assets/scripts/main.js'] ?? null;
    $theme_version = wp_get_theme()->get('Version');

    wp_enqueue_style(
        'tealforge-fonts',
        'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
        [],
        null
    );

    if (is_array($entry) && ! empty($entry['file'])) {
        if (! empty($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $index => $css_file) {
                wp_enqueue_style(
                    'tealforge-main-' . $index,
                    get_theme_file_uri('dist/' . ltrim((string) $css_file, '/')),
                    [],
                    null
                );
            }
        }

        wp_enqueue_script(
            'tealforge-main',
            get_theme_file_uri('dist/' . ltrim((string) $entry['file'], '/')),
            [],
            null,
            true
        );

        tealforge_enqueue_page_styles();

        return;
    }

    wp_enqueue_style(
        'tealforge-main',
        get_theme_file_uri('assets/styles/main.css'),
        [],
        $theme_version
    );

    wp_enqueue_script(
        'tealforge-main',
        get_theme_file_uri('assets/scripts/app.js'),
        [],
        $theme_version,
        true
    );

    tealforge_enqueue_page_styles();
}

add_action('wp_enqueue_scripts', 'tealforge_enqueue_assets');

function tealforge_module_script_tag(string $tag, string $handle): string
{
    if (! in_array($handle, ['tealforge-main', 'tealforge-cart'], true)) {
        return $tag;
    }

    $processor = new WP_HTML_Tag_Processor($tag);

    if ($processor->next_tag('SCRIPT')) {
        $processor->set_attribute('type', 'module');
    }

    return $processor->get_updated_html();
}

add_filter('script_loader_tag', 'tealforge_module_script_tag', 10, 2);

function tealforge_enqueue_cart_script(): void
{
    if (! function_exists('is_cart')
        || (! is_cart() && ! (is_checkout() && ! is_wc_endpoint_url()))) {
        return;
    }

    $source = 'assets/scripts/cart.js';
    $entry = tealforge_get_asset_manifest()[$source] ?? null;
    $compiled = is_array($entry) && ! empty($entry['file']);

    wp_enqueue_script(
        'tealforge-cart',
        get_theme_file_uri($compiled ? 'dist/' . $entry['file'] : $source),
        ['wp-data', 'wp-i18n', 'wc-blocks-data-store', 'wc-blocks-checkout'],
        $compiled ? null : wp_get_theme()->get('Version'),
        true
    );
}

add_action('wp_enqueue_scripts', 'tealforge_enqueue_cart_script', 20);

function tealforge_enqueue_page_styles(): void
{
    $post = get_queried_object();
    $content = $post instanceof WP_Post ? $post->post_content : '';
    $cart = function_exists('is_cart') && (is_cart() || has_shortcode($content, 'woocommerce_cart') || has_block('woocommerce/cart', $content));
    $checkout = function_exists('is_checkout') && (is_checkout() || has_shortcode($content, 'woocommerce_checkout') || has_block('woocommerce/checkout', $content));
    $account = is_page(['mon-compte', 'creer-un-compte']) || (function_exists('is_account_page') && is_account_page()) || has_shortcode($content, 'woocommerce_my_account');
    $woocommerce = function_exists('is_woocommerce') && (is_woocommerce() || $cart || $checkout || $account || str_contains($content, '<!-- wp:woocommerce/'));

    foreach (['products', 'product', 'product_page', 'product_category', 'product_categories', 'add_to_cart', 'woocommerce_order_tracking'] as $shortcode) {
        $woocommerce = $woocommerce || has_shortcode($content, $shortcode);
    }

    // Synced patterns can contain forms or shop blocks outside the page content.
    $synced_pattern = has_block('core/block', $content);
    $section_layouts = $post instanceof WP_Post ? get_post_meta($post->ID, 'page_sections', true) : [];
    $recharge_layouts = ['recharge_hero', 'recharge_products', 'recharge_families', 'recharge_steps', 'recharge_faq', 'recharge_cta', 'reassurance'];
    $has_recharge_sections = is_array($section_layouts) && array_intersect($recharge_layouts, $section_layouts) !== [];
    $styles = [
        'recharge' => is_front_page() || $has_recharge_sections,
        'woocommerce' => $woocommerce || $synced_pattern,
        'product-detail' => (function_exists('is_product') && is_product()) || has_shortcode($content, 'product_page'),
        'cart' => $cart || $synced_pattern,
        'checkout' => $checkout || $synced_pattern,
        'account' => $account || $synced_pattern,
        'forms' => is_page('contact') || has_shortcode($content, 'wpforms') || str_contains($content, '<!-- wp:wpforms/') || $synced_pattern,
        'contact' => is_page('contact'),
        'error-page' => is_404(),
        'animations' => true,
    ];
    $styles = apply_filters('tealforge_page_styles', $styles);
    $manifest = tealforge_get_asset_manifest();
    $dependencies = wp_style_is('tealforge-main-0', 'enqueued') ? ['tealforge-main-0'] : ['tealforge-main'];

    foreach ($styles as $name => $enabled) {
        if (! $enabled) {
            continue;
        }

        $source = 'assets/styles/sections/' . $name . '.css';
        $entry = $manifest[$source] ?? null;
        $compiled = is_array($entry) && ! empty($entry['file']);
        $handle = 'tealforge-' . $name;
        wp_enqueue_style(
            $handle,
            get_theme_file_uri($compiled ? 'dist/' . $entry['file'] : $source),
            $dependencies,
            $compiled ? null : wp_get_theme()->get('Version')
        );
        $dependencies[] = $handle;
    }
}
