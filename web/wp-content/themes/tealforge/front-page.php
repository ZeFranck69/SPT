<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

$context = tealforge_get_context();
$context['post'] = Timber\Timber::get_post();

$sections = function_exists('get_field') && $context['post']
    ? get_field('page_sections', $context['post']->ID)
    : [];

if (! is_array($sections) || $sections === []) {
    $sections = tealforge_get_default_page_sections();
}

$context['sections'] = tealforge_prepare_page_sections($sections);
$context['woocommerce_notices'] = '';

if (function_exists('wc_print_notices')) {
    ob_start();
    wc_print_notices();
    $context['woocommerce_notices'] = (string) ob_get_clean();

    // Success confirmations must not trigger WooCommerce's delayed alert focus.
    $notices_html = new WP_HTML_Tag_Processor($context['woocommerce_notices']);
    while ($notices_html->next_tag(['class_name' => 'woocommerce-message'])) {
        $notices_html->set_attribute('role', 'status');
    }
    $context['woocommerce_notices'] = $notices_html->get_updated_html();

    foreach ($context['sections'] as &$section) {
        if (($section['acf_fc_layout'] ?? '') === 'recharge_products' && ! empty($section['product_groups'])) {
            $section['woocommerce_notices'] = $context['woocommerce_notices'];
            $context['woocommerce_notices'] = '';
            break;
        }
    }
    unset($section);
}

tealforge_render('pages/front-page.twig', $context);
