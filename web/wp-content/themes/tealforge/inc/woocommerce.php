<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

function tealforge_woocommerce_body_class(array $classes): array
{
    if (function_exists('is_account_page') && is_account_page()) {
        $classes[] = 'tf-woocommerce-account';

        if (is_user_logged_in()) {
            $classes[] = 'tf-account-authenticated';
        }

        if (is_user_logged_in() && function_exists('is_wc_endpoint_url') && ! is_wc_endpoint_url()) {
            $classes[] = 'tf-account-dashboard';
        }
    }

    return $classes;
}

add_filter('body_class', 'tealforge_woocommerce_body_class');

function tealforge_woocommerce_account_menu_items(array $items): array
{
    if (isset($items['dashboard'])) {
        $items['dashboard'] = __('Vue d’ensemble', 'tealforge');
    }

    if (isset($items['orders'])) {
        $items['orders'] = __('Mes commandes', 'tealforge');
    }

    if (isset($items['edit-account'])) {
        $items['edit-account'] = __('Mes informations', 'tealforge');
    }

    if (isset($items['downloads'])) {
        unset($items['downloads']);
    }

    unset($items['edit-address']);

    if (isset($items['customer-logout'])) {
        $items['customer-logout'] = __('Déconnexion', 'tealforge');
    }

    return $items;
}

add_filter('woocommerce_account_menu_items', 'tealforge_woocommerce_account_menu_items');

function tealforge_woocommerce_add_to_cart_text(): string
{
    return __('Ajouter au panier', 'tealforge');
}

add_filter('woocommerce_product_add_to_cart_text', 'tealforge_woocommerce_add_to_cart_text');
add_filter('woocommerce_product_single_add_to_cart_text', 'tealforge_woocommerce_add_to_cart_text');

function tealforge_woocommerce_recharge_add_to_cart_redirect($url)
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && isset($_POST['tf_recharge_purchase'])
        && $_POST['tf_recharge_purchase'] === '1') {
        return home_url('/#recharges');
    }

    return $url;
}

add_filter('woocommerce_add_to_cart_redirect', 'tealforge_woocommerce_recharge_add_to_cart_redirect');

function tealforge_get_cart_count(): int
{
    if (! function_exists('WC') || ! WC()->cart) {
        return 0;
    }

    return max(0, (int) WC()->cart->get_cart_contents_count());
}

function tealforge_render_header_cart_count(): string
{
    $count = tealforge_get_cart_count();

    if ($count === 0) {
        return '<span class="tf-site-header__cart-count" data-tf-cart-count hidden></span>';
    }

    return sprintf(
        '<span class="tf-site-header__cart-count" data-tf-cart-count aria-label="%1$s">%2$d</span>',
        esc_attr(sprintf(
            _n('%d article dans le panier', '%d articles dans le panier', $count, 'tealforge'),
            $count
        )),
        $count
    );
}

function tealforge_woocommerce_cart_count_fragment(array $fragments): array
{
    $fragments['.tf-site-header__cart-count'] = tealforge_render_header_cart_count();

    return $fragments;
}

add_filter('woocommerce_add_to_cart_fragments', 'tealforge_woocommerce_cart_count_fragment');

function tealforge_woocommerce_before_customer_login_form(): void
{
    echo '<div class="tf-account-login-note">';
    echo '<p class="tf-account-login-note__eyebrow">' . esc_html__('Accès client', 'tealforge') . '</p>';
    echo '<p class="tf-account-login-note__text">' . esc_html__('Connectez-vous avec les identifiants de votre compte. Un achat en tant qu’invité ne crée pas automatiquement de compte : retrouvez les informations de cet achat dans votre email de commande.', 'tealforge') . '</p>';
    echo '</div>';
}

add_action('woocommerce_before_customer_login_form', 'tealforge_woocommerce_before_customer_login_form');

function tealforge_woocommerce_account_dashboard_intro(): void
{
    if (! is_user_logged_in()) {
        return;
    }

    $customer_id = get_current_user_id();
    $orders = wc_get_orders([
        'customer_id' => $customer_id,
        'limit' => 3,
        'status' => array_keys(wc_get_order_statuses()),
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    $recent_orders = [];

    foreach ($orders as $order) {
        if ($order->get_customer_id() !== $customer_id || ! current_user_can('view_order', $order->get_id())) {
            continue;
        }

        $date = $order->get_date_created();
        $recent_orders[] = [
            'number' => $order->get_order_number(),
            'date' => $date ? wc_format_datetime($date) : '',
            'datetime' => $date ? $date->date(DATE_ATOM) : '',
            'status' => wc_get_order_status_name($order->get_status()),
            'total' => wp_kses_post($order->get_formatted_order_total()),
            'url' => $order->get_view_order_url(),
        ];
    }

    Timber\Timber::render('components/account-dashboard.twig', [
        'orders' => $recent_orders,
        'orders_url' => wc_get_account_endpoint_url('orders'),
        'recharges_url' => home_url('/#recharges'),
    ]);
}

add_action('woocommerce_account_dashboard', 'tealforge_woocommerce_account_dashboard_intro', 1);

function tealforge_woocommerce_verification_link_class(string $message): string
{
    if (! str_contains($message, 'wc_send_verification')) {
        return $message;
    }

    $processor = new WP_HTML_Tag_Processor($message);
    while ($processor->next_tag('A')) {
        $href = $processor->get_attribute('href');
        if (! is_string($href)) {
            continue;
        }

        $query = wp_parse_url($href, PHP_URL_QUERY);
        if (! is_string($query)) {
            continue;
        }

        parse_str($query, $params);
        if (($params['wc_send_verification'] ?? null) === '1') {
            $processor->add_class('tf-account-verify-link');
        }
    }

    return $processor->get_updated_html();
}

add_filter('woocommerce_add_notice', 'tealforge_woocommerce_verification_link_class');

function tealforge_woocommerce_gettext(string $translation, string $text, string $domain): string
{
    if ($domain !== 'woocommerce') {
        return $translation;
    }

    if ($text === 'Billing address' && function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received')) {
        return __('Information de facturation', 'tealforge');
    }

    return match ($text) {
        'Confirm email address' => __('Envoyer un mail', 'tealforge'),
        'Confirm your email address to check for past orders and link them to your account.' => __('Confirmez votre adresse e-mail pour retrouver vos anciens achats et les associer à votre compte.', 'tealforge'),
        'A confirmation link has been sent to your email address. Please check your inbox.' => __('Votre lien de confirmation a été envoyé par e-mail. Ouvrez le message pour confirmer votre adresse. Pensez aussi à vérifier vos courriers indésirables.', 'tealforge'),
        'Log in' => __('Connexion', 'tealforge'),
        'Login' => __('Connexion', 'tealforge'),
        'Remember me' => __('Se souvenir de moi', 'tealforge'),
        'Username or email address' => __('Email ou identifiant', 'tealforge'),
        'Password' => __('Mot de passe', 'tealforge'),
        'Lost your password?' => __('Mot de passe oublié ?', 'tealforge'),
        'Add to cart' => __('Ajouter au panier', 'tealforge'),
        'View cart' => __('Voir le panier', 'tealforge'),
        'Checkout' => __('Commande', 'tealforge'),
        'Proceed to checkout' => __('Passer au paiement', 'tealforge'),
        'Apply coupon' => __('Appliquer le code', 'tealforge'),
        'Update cart' => __('Mettre à jour le panier', 'tealforge'),
        'Your cart is currently empty!' => __('Votre panier est vide.', 'tealforge'),
        'New in store' => __('Nouveautés', 'tealforge'),
        'You may be interested in…' => __('Ces recharges peuvent vous intéresser', 'tealforge'),
        default => $translation,
    };
}

add_filter('gettext', 'tealforge_woocommerce_gettext', 10, 3);

function tealforge_woocommerce_empty_cart_block(string $block_content, array $block): string
{
    if (($block['blockName'] ?? '') !== 'woocommerce/empty-cart-block') {
        return $block_content;
    }

    $empty_cart_panel = sprintf(
        '<div class="tf-empty-cart__panel">'
        . '<span class="tf-empty-cart__icon" aria-hidden="true">'
        . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">'
        . '<circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle>'
        . '<path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h7.78a2 2 0 0 0 2-1.61L20.12 6H5.12"></path>'
        . '</svg>'
        . '</span>'
        . '<h2 class="wp-block-heading has-text-align-center wc-block-cart__empty-cart__title">%1$s</h2>'
        . '<p class="tf-empty-cart__text">%2$s</p>'
        . '<a class="tf-empty-cart__button" href="%3$s">%4$s</a>'
        . '</div>',
        esc_html__('Votre panier est vide', 'tealforge'),
        esc_html__('Choisissez une ou plusieurs recharges Papito Voix ou Neti Data pour commencer votre commande.', 'tealforge'),
        esc_url(home_url('/#recharges')),
        esc_html__('Découvrir les recharges', 'tealforge')
    );

    // Keep the block identity so WooCommerce can toggle it with the cart state.
    return '<div data-block-name="woocommerce/empty-cart-block" class="wp-block-woocommerce-empty-cart-block">' . $empty_cart_panel . '</div>';
}

add_filter('render_block_woocommerce/empty-cart-block', 'tealforge_woocommerce_empty_cart_block', 10, 2);

add_filter('render_block_woocommerce/cart-order-summary-coupon-form-block', '__return_empty_string');
add_filter('render_block_woocommerce/checkout-order-summary-coupon-form-block', '__return_empty_string');

add_filter('pre_option_woocommerce_checkout_phone_field', static fn () => 'required');

function tealforge_woocommerce_contact_address_fields(array $fields): array
{
    if (is_admin() || (function_exists('is_account_page') && is_account_page())) {
        return $fields;
    }

    foreach (['company', 'address_1', 'address_2', 'city', 'state', 'postcode'] as $key) {
        $fields[$key]['required'] = false;
        $fields[$key]['hidden'] = true;
    }

    return $fields;
}

add_filter('woocommerce_get_country_locale_default', 'tealforge_woocommerce_contact_address_fields', 20);

function tealforge_woocommerce_contact_country_locales(array $locales): array
{
    // Blocks do not inherit the default locale for countries without an entry.
    foreach (array_keys(WC()->countries->get_allowed_countries()) as $country) {
        $locales[$country] ??= [];
    }

    foreach ($locales as $country => $fields) {
        $locales[$country] = tealforge_woocommerce_contact_address_fields($fields);
    }

    return $locales;
}

add_filter('woocommerce_get_country_locale', 'tealforge_woocommerce_contact_country_locales', 20);

function tealforge_woocommerce_contact_block_title(array $block): array
{
    if (($block['blockName'] ?? '') === 'woocommerce/checkout-billing-address-block') {
        $block['attrs']['title'] = __('Vos coordonnées', 'tealforge');
        $block['attrs']['description'] = '';
    }

    return $block;
}

add_filter('render_block_data', 'tealforge_woocommerce_contact_block_title');

function tealforge_woocommerce_normalize_regional_phone(string $phone, string $country): string
{
    $prefix = ['NC' => '+687', 'WF' => '+681'][$country] ?? '';
    $phone = (string) preg_replace('/[\s().-]+/u', '', trim($phone));
    $phone = (string) preg_replace('/^00/', '+', $phone);

    if ($prefix !== '' && preg_match('/^[0-9]{6}$/D', $phone)) {
        $phone = $prefix . $phone;
    }

    return $phone;
}

function tealforge_woocommerce_validate_regional_phone($response, array $handler, WP_REST_Request $request)
{
    if ($response !== null || $request->get_method() !== 'POST'
        || ! preg_match('#^/wc/store/v[0-9]+/checkout(?:/[0-9]+)?$#D', $request->get_route())) {
        return $response;
    }

    $billing = $request->get_param('billing_address');
    if (! is_array($billing)) {
        return $response;
    }

    $country = is_string($billing['country'] ?? null) ? $billing['country'] : '';
    $phone = tealforge_woocommerce_normalize_regional_phone(
        is_string($billing['phone'] ?? null) ? $billing['phone'] : '',
        $country
    );

    $error = tealforge_woocommerce_regional_phone_error($phone, $country);
    if ($error !== null) {
        return $error;
    }

    $billing['phone'] = $phone;
    $request->set_param('billing_address', $billing);

    return $response;
}

add_filter('rest_request_before_callbacks', 'tealforge_woocommerce_validate_regional_phone', 10, 3);

function tealforge_woocommerce_regional_phone_error(string $phone, string $country): ?WP_Error
{
    $prefix = ['NC' => '+687', 'WF' => '+681'][$country] ?? '';
    $phone = tealforge_woocommerce_normalize_regional_phone($phone, $country);

    if ($prefix !== '' && preg_match('/^' . preg_quote($prefix, '/') . '[0-9]{6}$/D', $phone)) {
        return null;
    }

    return new WP_Error(
        'tealforge_invalid_phone',
        __('Saisissez un numéro de téléphone à 6 chiffres avec l’indicatif du pays sélectionné (+687 ou +681).', 'tealforge'),
        ['status' => 400]
    );
}

function tealforge_woocommerce_validate_order_phone(WC_Order $order, WP_Error $errors): void
{
    $error = tealforge_woocommerce_regional_phone_error($order->get_billing_phone(), $order->get_billing_country());

    if ($error !== null) {
        $errors->merge_from($error);
    }
}

add_action('woocommerce_checkout_validate_order_before_payment', 'tealforge_woocommerce_validate_order_phone', 10, 2);

function tealforge_woocommerce_email_logo(mixed $currentLogo): mixed
{
    if (! function_exists('get_field')) {
        return $currentLogo;
    }

    $logo = get_field('header_logo', 'option');

    if (is_array($logo) && ! empty($logo['url'])) {
        return esc_url_raw((string) $logo['url']);
    }

    if (is_numeric($logo)) {
        $url = wp_get_attachment_image_url((int) $logo, 'full');

        return $url ?: $currentLogo;
    }

    return is_string($logo) && $logo !== '' ? esc_url_raw($logo) : $currentLogo;
}

add_filter('option_woocommerce_email_header_image', 'tealforge_woocommerce_email_logo');

function tealforge_woocommerce_email_brand_color(mixed $color): string
{
    return '#e6141f';
}

function tealforge_woocommerce_email_text_color(mixed $color): string
{
    return '#1d1b1c';
}

function tealforge_woocommerce_email_background_color(mixed $color): string
{
    return '#f3f3f3';
}

function tealforge_woocommerce_email_body_color(mixed $color): string
{
    return '#ffffff';
}

function tealforge_woocommerce_email_logo_width(mixed $width): string
{
    return '220';
}

add_filter('option_woocommerce_email_base_color', 'tealforge_woocommerce_email_brand_color');
add_filter('option_woocommerce_email_text_color', 'tealforge_woocommerce_email_text_color');
add_filter('option_woocommerce_email_background_color', 'tealforge_woocommerce_email_background_color');
add_filter('option_woocommerce_email_body_background_color', 'tealforge_woocommerce_email_body_color');
add_filter('option_woocommerce_email_header_image_width', 'tealforge_woocommerce_email_logo_width');

function tealforge_woocommerce_completed_email_subject(string $subject, WC_Order $order): string
{
    return sprintf(
        __('Vos codes de recharge - commande #%s', 'tealforge'),
        $order->get_order_number()
    );
}

add_filter('woocommerce_email_subject_customer_completed_order', 'tealforge_woocommerce_completed_email_subject', 10, 2);

function tealforge_woocommerce_completed_email_heading(string $heading, WC_Order $order): string
{
    return __('Vos recharges sont disponibles', 'tealforge');
}

add_filter('woocommerce_email_heading_customer_completed_order', 'tealforge_woocommerce_completed_email_heading', 10, 2);
