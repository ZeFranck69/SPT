<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

$context = tealforge_get_context();
$context['post'] = Timber\Timber::get_post();
$context['heading'] = [
    'icon' => '',
    'eyebrow' => 'SPT Wallis & Futuna',
    'title' => $context['post'] ? $context['post']->title : '',
    'text' => '',
];

if (function_exists('is_cart') && is_cart()) {
    $context['heading'] = [
        'icon' => 'shopping-cart',
        'eyebrow' => 'Votre sélection',
        'title' => 'Mon panier',
        'text' => 'Vérifiez vos recharges avant de poursuivre votre commande.',
    ];
} elseif (function_exists('is_checkout') && is_checkout()) {
    $context['heading'] = [
        'icon' => 'credit-card',
        'eyebrow' => 'Paiement sécurisé',
        'title' => function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')
            ? 'Commande confirmée'
            : 'Finaliser ma commande',
        'text' => 'Renseignez vos coordonnées et validez votre paiement.',
    ];
}

tealforge_render('pages/page.twig', $context);
