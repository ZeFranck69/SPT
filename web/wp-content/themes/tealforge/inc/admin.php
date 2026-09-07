<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

function tealforge_get_admin_logo_url(): string
{
    $uploads = wp_get_upload_dir();

    if (! empty($uploads['error']) || empty($uploads['baseurl'])) {
        return '';
    }

    return trailingslashit((string) $uploads['baseurl']) . '2026/09/Logo-recharge-ton-manuia-removebg.png';
}

function tealforge_enqueue_admin_assets(): void
{
    $stylesheet_path = get_theme_file_path('assets/styles/admin.css');

    wp_enqueue_style(
        'tealforge-admin',
        get_theme_file_uri('assets/styles/admin.css'),
        [],
        is_readable($stylesheet_path) ? (string) filemtime($stylesheet_path) : wp_get_theme()->get('Version')
    );
}

add_action('admin_enqueue_scripts', 'tealforge_enqueue_admin_assets');

function tealforge_enqueue_login_assets(): void
{
    $stylesheet_path = get_theme_file_path('assets/styles/login.css');

    wp_enqueue_style(
        'tealforge-login',
        get_theme_file_uri('assets/styles/login.css'),
        [],
        is_readable($stylesheet_path) ? (string) filemtime($stylesheet_path) : wp_get_theme()->get('Version')
    );

    $logo_url = tealforge_get_admin_logo_url();

    if ($logo_url !== '') {
        wp_add_inline_style(
            'tealforge-login',
            '#login h1 a { background-image: url("' . esc_url($logo_url) . '"); }'
        );
    }
}

add_action('login_enqueue_scripts', 'tealforge_enqueue_login_assets');

function tealforge_login_header_url(): string
{
    return home_url('/');
}

add_filter('login_headerurl', 'tealforge_login_header_url');

function tealforge_login_header_text(): string
{
    return get_bloginfo('name');
}

add_filter('login_headertext', 'tealforge_login_header_text');
