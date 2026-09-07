<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

function tealforge_register_site_options_page(): void
{
    if (! function_exists('acf_add_options_page')) {
        return;
    }

    acf_add_options_page([
        'page_title' => __('Options du site', 'tealforge'),
        'menu_title' => __('Options du site', 'tealforge'),
        'menu_slug' => 'tealforge-site-settings',
        'parent_slug' => 'themes.php',
        'capability' => 'edit_theme_options',
        'redirect' => false,
        'autoload' => true,
    ]);
}

add_action('acf/init', 'tealforge_register_site_options_page');
