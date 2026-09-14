<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

function tealforge_get_asset_manifest(): array
{
    static $manifest = null;

    if (null !== $manifest) {
        return $manifest;
    }

    $manifest = [];
    $manifest_path = get_theme_file_path('dist/manifest.json');

    if (! is_readable($manifest_path)) {
        return $manifest;
    }

    $manifest_json = file_get_contents($manifest_path);

    if (false === $manifest_json) {
        return $manifest;
    }

    $decoded_manifest = json_decode($manifest_json, true);

    if (is_array($decoded_manifest)) {
        $manifest = $decoded_manifest;
    }

    return $manifest;
}

function tealforge_enqueue_assets(): void
{
    $entry = tealforge_get_asset_manifest()['assets/scripts/main.js'] ?? null;
    $theme_version = wp_get_theme()->get('Version');

    wp_enqueue_style(
        'tealforge-fonts',
        'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
        [],
        null
    );

    if (is_array($entry) && ! empty($entry['file'])) {
        if (! empty($entry['css']) && is_array($entry['css'])) {
            foreach ($entry['css'] as $index => $css_file) {
                wp_enqueue_style(
                    'tealforge-main-' . $index,
                    get_theme_file_uri('dist/' . ltrim((string) $css_file, '/')),
                    [],
                    null
                );
            }
        }

        wp_enqueue_script(
            'tealforge-main',
            get_theme_file_uri('dist/' . ltrim((string) $entry['file'], '/')),
            [],
            null,
            true
        );

        return;
    }

    wp_enqueue_style(
        'tealforge-main',
        get_theme_file_uri('assets/styles/main.css'),
        [],
        $theme_version
    );

    wp_enqueue_script(
        'tealforge-main',
        get_theme_file_uri('assets/scripts/app.js'),
        [],
        $theme_version,
        true
    );
}

add_action('wp_enqueue_scripts', 'tealforge_enqueue_assets');
