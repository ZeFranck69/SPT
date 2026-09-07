<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

function tealforge_get_theme_file(string $relative_path): string
{
    return get_theme_file_path(ltrim($relative_path, '/'));
}

function tealforge_get_theme_file_uri(string $relative_path): string
{
    return get_theme_file_uri(ltrim($relative_path, '/'));
}

function tealforge_prepare_link_field(mixed $link): ?array
{
    if (! is_array($link) || empty($link['url'])) {
        return null;
    }

    return [
        'url' => esc_url((string) $link['url']),
        'title' => isset($link['title']) ? (string) $link['title'] : '',
        'target' => ! empty($link['target']) ? (string) $link['target'] : '_self',
    ];
}

function tealforge_prepare_image_field(mixed $image): ?array
{
    if (! is_array($image)) {
        return null;
    }

    $image_id = (int) ($image['ID'] ?? $image['id'] ?? 0);

    if ($image_id <= 0) {
        return null;
    }

    $source = wp_get_attachment_image_src($image_id, 'full');
    $url = is_array($source)
        ? (string) $source[0]
        : (string) ($image['url'] ?? wp_get_attachment_url($image_id));

    if ($url === '') {
        return null;
    }

    return [
        'id' => $image_id,
        'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
        'url' => $url,
        'width' => is_array($source) ? (int) $source[1] : (int) ($image['width'] ?? 0),
        'height' => is_array($source) ? (int) $source[2] : (int) ($image['height'] ?? 0),
        'srcset' => wp_get_attachment_image_srcset($image_id, 'full') ?: '',
    ];
}
