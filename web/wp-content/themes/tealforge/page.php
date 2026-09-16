<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

$context = tealforge_get_context();
$context['post'] = Timber\Timber::get_post();
$sections = function_exists('get_field') && $context['post']
    ? get_field('page_sections', $context['post']->ID)
    : [];
$context['sections'] = is_array($sections) ? tealforge_prepare_page_sections($sections) : [];
$context['heading'] = [
    'icon' => '',
    'eyebrow' => 'SPT Wallis & Futuna',
    'title' => $context['post'] ? $context['post']->title : '',
    'text' => '',
];

if (function_exists('is_cart') && is_cart()) {
    $context['is_purchase_page'] = true;
    $context['cart_return_url'] = home_url('/#recharges');
    $context['heading'] = [
        'icon' => '',
        'eyebrow' => 'Votre sélection',
        'title' => 'Mon panier',
        'text' => 'Vérifiez vos recharges avant de poursuivre votre commande.',
    ];
} elseif (function_exists('is_checkout') && is_checkout()) {
    $context['is_purchase_page'] = true;
    $is_confirmation = function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received');
    if (! is_wc_endpoint_url()) {
        $context['checkout_cart_url'] = wc_get_cart_url();
    }
    $context['heading'] = [
        'icon' => 'credit-card',
        'eyebrow' => 'Paiement sécurisé',
        'title' => $is_confirmation
            ? 'Commande reçue'
            : 'Finaliser ma commande',
        'text' => $is_confirmation
            ? 'Retrouvez ci-dessous le récapitulatif et les informations de votre commande.'
            : 'Renseignez vos coordonnées, puis choisissez votre moyen de paiement.',
    ];
}

tealforge_render('pages/page.twig', $context);
