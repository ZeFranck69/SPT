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

function tealforge_get_cart_count(): int
{
    if (! function_exists('WC') || ! WC()->cart) {
        return 0;
    }

    return max(0, (int) WC()->cart->get_cart_contents_count());
}

function tealforge_render_header_cart_count(): string
{
    $count = tealforge_get_cart_count();

    if ($count === 0) {
        return '<span class="tf-site-header__cart-count" hidden></span>';
    }

    return sprintf(
        '<span class="tf-site-header__cart-count" aria-label="%1$s">%2$d</span>',
        esc_attr(sprintf(
            _n('%d article dans le panier', '%d articles dans le panier', $count, 'tealforge'),
            $count
        )),
        $count
    );
}

function tealforge_woocommerce_cart_count_fragment(array $fragments): array
{
    $fragments['.tf-site-header__cart-count'] = tealforge_render_header_cart_count();

    return $fragments;
}

add_filter('woocommerce_add_to_cart_fragments', 'tealforge_woocommerce_cart_count_fragment');

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

function tealforge_woocommerce_empty_cart_block(string $block_content, array $block): string
{
    if (($block['blockName'] ?? '') !== 'woocommerce/empty-cart-block') {
        return $block_content;
    }

    $empty_cart_panel = sprintf(
        '<div class="tf-empty-cart__panel">'
        . '<span class="tf-empty-cart__icon" aria-hidden="true">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">'
        . '<circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle>'
        . '<path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h7.78a2 2 0 0 0 2-1.61L20.12 6H5.12"></path>'
        . '</svg>'
        . '</span>'
        . '<h2 class="wp-block-heading has-text-align-center wc-block-cart__empty-cart__title">%1$s</h2>'
        . '<p class="tf-empty-cart__text">%2$s</p>'
        . '<a class="tf-empty-cart__button" href="%3$s">%4$s</a>'
        . '</div>',
        esc_html__('Votre panier est vide', 'tealforge'),
        esc_html__('Choisissez une ou plusieurs recharges Papito Voix ou Neti Data pour commencer votre commande.', 'tealforge'),
        esc_url(home_url('/#recharges')),
        esc_html__('Découvrir les recharges', 'tealforge')
    );

    $block_content = (string) preg_replace(
        '/<h2[^>]*class="[^"]*wc-block-cart__empty-cart__title[^"]*"[^>]*>.*?<\/h2>/s',
        $empty_cart_panel,
        $block_content,
        1
    );

    $block_content = str_replace(
        '>New in store</h2>',
        '>' . esc_html__('Quelques recharges disponibles', 'tealforge') . '</h2>',
        $block_content
    );

    return $block_content;
}

add_filter('render_block_woocommerce/empty-cart-block', 'tealforge_woocommerce_empty_cart_block', 10, 2);

function tealforge_woocommerce_email_logo(mixed $currentLogo): mixed
{
    if (! function_exists('get_field')) {
        return $currentLogo;
    }

    $logo = get_field('header_logo', 'option');

    if (is_array($logo) && ! empty($logo['url'])) {
        return esc_url_raw((string) $logo['url']);
    }

    if (is_numeric($logo)) {
        $url = wp_get_attachment_image_url((int) $logo, 'full');

        return $url ?: $currentLogo;
    }

    return is_string($logo) && $logo !== '' ? esc_url_raw($logo) : $currentLogo;
}

add_filter('option_woocommerce_email_header_image', 'tealforge_woocommerce_email_logo');

function tealforge_woocommerce_email_brand_color(mixed $color): string
{
    return '#e6141f';
}

function tealforge_woocommerce_email_text_color(mixed $color): string
{
    return '#1d1b1c';
}

function tealforge_woocommerce_email_background_color(mixed $color): string
{
    return '#f3f3f3';
}

function tealforge_woocommerce_email_body_color(mixed $color): string
{
    return '#ffffff';
}

function tealforge_woocommerce_email_logo_width(mixed $width): string
{
    return '220';
}

add_filter('option_woocommerce_email_base_color', 'tealforge_woocommerce_email_brand_color');
add_filter('option_woocommerce_email_text_color', 'tealforge_woocommerce_email_text_color');
add_filter('option_woocommerce_email_background_color', 'tealforge_woocommerce_email_background_color');
add_filter('option_woocommerce_email_body_background_color', 'tealforge_woocommerce_email_body_color');
add_filter('option_woocommerce_email_header_image_width', 'tealforge_woocommerce_email_logo_width');

function tealforge_woocommerce_completed_email_subject(string $subject, WC_Order $order): string
{
    return sprintf(
        __('Vos codes de recharge - commande #%s', 'tealforge'),
        $order->get_order_number()
    );
}

add_filter('woocommerce_email_subject_customer_completed_order', 'tealforge_woocommerce_completed_email_subject', 10, 2);

function tealforge_woocommerce_completed_email_heading(string $heading, WC_Order $order): string
{
    return __('Vos recharges sont disponibles', 'tealforge');
}

add_filter('woocommerce_email_heading_customer_completed_order', 'tealforge_woocommerce_completed_email_heading', 10, 2);
