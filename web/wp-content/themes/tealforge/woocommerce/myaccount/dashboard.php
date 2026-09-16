<?php
/**
 * Account dashboard: the theme supplies the summary through the native hook.
 *
 * @version 4.4.0
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

do_action('woocommerce_account_dashboard');
do_action('woocommerce_before_my_account');
do_action('woocommerce_after_my_account');
