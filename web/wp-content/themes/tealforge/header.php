<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

$context = tealforge_get_context();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php wp_head(); ?>
    </head>

    <body <?php body_class('tf-site'); ?>>
        <?php wp_body_open(); ?>

        <?php tealforge_render('partials/header.twig', $context); ?>

        <main id="tf-main-content" class="tf-site__main" tabindex="-1">
