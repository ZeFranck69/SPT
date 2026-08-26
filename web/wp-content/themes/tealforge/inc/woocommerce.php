<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

function tealforge_woocommerce_body_class(array $classes): array
{
    if (function_exists('is_account_page') && is_account_page()) {
        $classes[] = 'tf-woocommerce-account';

        if (is_user_logged_in()) {
            $classes[] = 'tf-account-authenticated';
        }

        if (is_user_logged_in() && function_exists('is_wc_endpoint_url') && ! is_wc_endpoint_url()) {
            $classes[] = 'tf-account-dashboard';
        }
    }

    return $classes;
}

add_filter('body_class', 'tealforge_woocommerce_body_class');

function tealforge_woocommerce_account_menu_items(array $items): array
{
    if (isset($items['dashboard'])) {
        $items['dashboard'] = __('Tableau de bord', 'tealforge');
    }

    if (isset($items['orders'])) {
        $items['orders'] = __('Mes commandes', 'tealforge');
    }

    if (isset($items['edit-account'])) {
        $items['edit-account'] = __('Mes informations', 'tealforge');
    }

    if (isset($items['downloads'])) {
        unset($items['downloads']);
    }

    if (isset($items['customer-logout'])) {
        $items['customer-logout'] = __('Déconnexion', 'tealforge');
    }

    return $items;
}

add_filter('woocommerce_account_menu_items', 'tealforge_woocommerce_account_menu_items');

function tealforge_woocommerce_add_to_cart_text(): string
{
    return __('Ajouter au panier', 'tealforge');
}

add_filter('woocommerce_product_add_to_cart_text', 'tealforge_woocommerce_add_to_cart_text');
add_filter('woocommerce_product_single_add_to_cart_text', 'tealforge_woocommerce_add_to_cart_text');

function tealforge_woocommerce_before_account_navigation(): void
{
    echo '<div class="tf-myaccount-nav-heading">';
    echo '<p class="tf-myaccount-nav-heading__eyebrow">' . esc_html__('Menu', 'tealforge') . '</p>';
    echo '<h2 class="tf-myaccount-nav-heading__title">' . esc_html__('Mon espace', 'tealforge') . '</h2>';
    echo '</div>';
}

add_action('woocommerce_before_account_navigation', 'tealforge_woocommerce_before_account_navigation');

function tealforge_woocommerce_before_customer_login_form(): void
{
    echo '<div class="tf-account-login-note">';
    echo '<p class="tf-account-login-note__eyebrow">' . esc_html__('Accès client', 'tealforge') . '</p>';
    echo '<p class="tf-account-login-note__text">' . esc_html__('Utilisez votre email de commande pour accéder à votre espace personnel.', 'tealforge') . '</p>';
    echo '</div>';
}

add_action('woocommerce_before_customer_login_form', 'tealforge_woocommerce_before_customer_login_form');

function tealforge_woocommerce_account_dashboard_intro(): void
{
    echo '<div class="tf-myaccount-dashboard-intro">';
    echo '<p class="tf-myaccount-dashboard-intro__eyebrow">' . esc_html__('Votre espace', 'tealforge') . '</p>';
    echo '<h2 class="tf-myaccount-dashboard-intro__title">' . esc_html__('Vos recharges en un coup d’œil', 'tealforge') . '</h2>';
    echo '<p class="tf-myaccount-dashboard-intro__text">' . esc_html__('Consultez vos commandes et gardez vos coordonnées à jour.', 'tealforge') . '</p>';
    echo '<div class="tf-myaccount-dashboard-intro__actions">';
    echo '<a class="button" href="' . esc_url(wc_get_account_endpoint_url('orders')) . '">' . esc_html__('Voir mes commandes', 'tealforge') . '</a>';
    echo '<a class="button button--secondary" href="' . esc_url(wc_get_account_endpoint_url('edit-account')) . '">' . esc_html__('Modifier mes informations', 'tealforge') . '</a>';
    echo '</div>';
    echo '</div>';
}

add_action('woocommerce_account_dashboard', 'tealforge_woocommerce_account_dashboard_intro', 1);

function tealforge_woocommerce_gettext(string $translation, string $text, string $domain): string
{
    if ($domain !== 'woocommerce') {
        return $translation;
    }

    return match ($text) {
        'Log in' => __('Connexion', 'tealforge'),
        'Login' => __('Connexion', 'tealforge'),
        'Remember me' => __('Se souvenir de moi', 'tealforge'),
        'Username or email address' => __('Email ou identifiant', 'tealforge'),
        'Password' => __('Mot de passe', 'tealforge'),
        'Lost your password?' => __('Mot de passe oublié ?', 'tealforge'),
        'Add to cart' => __('Ajouter au panier', 'tealforge'),
        'View cart' => __('Voir le panier', 'tealforge'),
        'Checkout' => __('Commande', 'tealforge'),
        'Proceed to checkout' => __('Passer commande', 'tealforge'),
        'Apply coupon' => __('Appliquer le code', 'tealforge'),
        'Update cart' => __('Mettre à jour le panier', 'tealforge'),
        'Your cart is currently empty!' => __('Votre panier est vide.', 'tealforge'),
        'New in store' => __('Nouveautés', 'tealforge'),
        'You may be interested in…' => __('Ces recharges peuvent vous intéresser', 'tealforge'),
        default => $translation,
    };
}

add_filter('gettext', 'tealforge_woocommerce_gettext', 10, 3);
