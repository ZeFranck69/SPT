<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

function tealforge_register_site_options_page(): void
{
    if (! function_exists('acf_add_options_page')) {
        return;
    }

    acf_add_options_page([
        'page_title' => __('Réglages du site', 'tealforge'),
        'menu_title' => __('Réglages du site', 'tealforge'),
        'menu_slug' => 'tealforge-site-settings',
        'icon_url' => 'dashicons-admin-generic',
        'position' => 59,
        'capability' => 'edit_theme_options',
        'redirect' => false,
        'autoload' => true,
    ]);
}

add_action('acf/init', 'tealforge_register_site_options_page');
