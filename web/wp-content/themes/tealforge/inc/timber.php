<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

if (class_exists(Timber\Timber::class)) {
    Timber\Timber::init();
}

function tealforge_get_context(): array
{
    if (! class_exists(Timber\Timber::class)) {
        return [];
    }

    $context = Timber\Timber::context();

    $context['menus'] = [
        'primary' => has_nav_menu('primary') ? Timber\Timber::get_menu('primary') : null,
        'footer' => has_nav_menu('footer') ? Timber\Timber::get_menu('footer') : null,
    ];

    $context['spt_links'] = [
        'shop' => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/boutique/'),
        'cart' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/panier/'),
        'account' => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/mon-compte/'),
        'contact' => home_url('/contact/'),
    ];

    $is_logged_in = is_user_logged_in();
    $current_user = wp_get_current_user();
    $display_name = $current_user->first_name ?: $current_user->display_name;

    $context['spt_account'] = [
        'is_logged_in' => $is_logged_in,
        'display_name' => $is_logged_in ? $display_name : '',
        'header_label' => $is_logged_in ? __('Mon compte', 'tealforge') : __('Se connecter', 'tealforge'),
        'mobile_label' => $is_logged_in ? __('Compte', 'tealforge') : __('Connexion', 'tealforge'),
    ];

    return $context;
}

function tealforge_render(string $template, array $context = []): void
{
    if (! class_exists(Timber\Timber::class)) {
        wp_die(
            esc_html__(
                'Les dependances Composer du theme Tealforge sont absentes. Lancez composer install dans le theme.',
                'tealforge'
            )
        );
    }

    Timber\Timber::render($template, $context);
}
