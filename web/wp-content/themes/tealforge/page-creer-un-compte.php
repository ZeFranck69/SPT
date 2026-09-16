<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

$context = tealforge_get_context();
$context['post'] = Timber\Timber::get_post();
$context['is_registration'] = true;

tealforge_render('pages/account.twig', $context);
