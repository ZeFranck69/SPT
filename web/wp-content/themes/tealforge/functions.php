<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

$tealforge_autoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($tealforge_autoload)) {
    require_once $tealforge_autoload;
}

require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/setup.php';
require_once __DIR__ . '/inc/assets.php';
require_once __DIR__ . '/inc/admin.php';
require_once __DIR__ . '/inc/acf.php';
require_once __DIR__ . '/inc/timber.php';
require_once __DIR__ . '/inc/sections.php';

if (class_exists('WooCommerce')) {
    require_once __DIR__ . '/inc/woocommerce.php';
}
