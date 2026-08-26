<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

get_header();

$heading_context = tealforge_get_context();
$heading_context['heading'] = [
    'icon' => 'shopping-cart',
    'eyebrow' => 'Boutique SPT',
    'title' => woocommerce_page_title(false),
    'text' => 'Choisissez vos recharges Papito Voix et Neti Data.',
];
?>

<section class="tf-shop-page">
    <div class="tf-shop-page__inner">
        <?php if (! is_product()) : ?>
            <?php tealforge_render('components/inner-page-heading.twig', $heading_context); ?>
        <?php endif; ?>

        <?php woocommerce_content(); ?>
    </div>
</section>

<?php
get_footer();
