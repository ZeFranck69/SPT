<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

function tealforge_setup(): void
{
    load_theme_textdomain('tealforge', get_template_directory() . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('menus');
    add_theme_support('woocommerce');

    register_nav_menus([
        'primary' => __('Menu principal', 'tealforge'),
        'footer' => __('Menu pied de page', 'tealforge'),
    ]);
}

add_action('after_setup_theme', 'tealforge_setup');

function tealforge_use_classic_editor_for_pages(bool $use_block_editor, string $post_type): bool
{
    if ($post_type === 'page') {
        return false;
    }

    return $use_block_editor;
}

add_filter('use_block_editor_for_post_type', 'tealforge_use_classic_editor_for_pages', 10, 2);
