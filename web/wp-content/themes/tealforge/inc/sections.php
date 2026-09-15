<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

function tealforge_get_shop_url(): string
{
    if (function_exists('wc_get_page_permalink')) {
        return (string) wc_get_page_permalink('shop');
    }

    return home_url('/boutique/');
}

function tealforge_get_product_url_by_sku(string $sku): string
{
    if (function_exists('wc_get_product_id_by_sku')) {
        $product_id = wc_get_product_id_by_sku($sku);

        if ($product_id > 0) {
            return (string) get_permalink($product_id);
        }
    }

    return tealforge_get_shop_url();
}

function tealforge_get_product_id_by_sku(string $sku): int
{
    if (! function_exists('wc_get_product_id_by_sku')) {
        return 0;
    }

    return (int) wc_get_product_id_by_sku($sku);
}

function tealforge_get_default_page_sections(): array
{
    return [
        [
            'acf_fc_layout' => 'recharge_hero',
            'eyebrow' => 'Recharge instantanée',
            'title' => 'Restez connecté en toutes circonstances',
            'text' => 'Rechargez Papito Voix et Neti Data en ligne. Un parcours rapide, simple et sécurisé à Wallis et Futuna.',
            'primary_label' => 'Recharger maintenant',
            'primary_url' => '#recharges',
            'secondary_label' => 'En savoir plus',
            'secondary_url' => '#fonctionnement',
            'stat_label' => 'Paiement',
            'stat_value' => '100%',
            'stat_suffix' => 'sécurisé',
            'stat_badge' => '',
            'mini_cards' => [
                [
                    'label' => 'Service',
                    'amount' => '24/7',
                    'text' => 'Toujours disponible',
                ],
                [
                    'label' => 'Livraison',
                    'amount' => 'Email + SMS',
                    'text' => 'Après validation',
                ],
            ],
        ],
        [
            'acf_fc_layout' => 'recharge_products',
            'eyebrow' => 'Choisissez l’offre qui vous convient le mieux',
            'title' => 'Offres disponibles',
            'products' => tealforge_get_default_recharge_product_ids(),
        ],
        [
            'acf_fc_layout' => 'recharge_families',
            'families' => [
                [
                    'eyebrow' => 'Papito Voix',
                    'title' => 'Pour appeler simplement',
                    'text' => 'Des recharges voix disponibles en 1 000 F, 3 000 F et 5 000 F.',
                    'tone' => 'papito',
                ],
                [
                    'eyebrow' => 'Neti Data',
                    'title' => 'Pour rester connecté',
                    'text' => 'Des recharges data disponibles en 1 000 F, 3 000 F et 5 000 F.',
                    'tone' => 'neti',
                ],
            ],
        ],
        [
            'acf_fc_layout' => 'recharge_steps',
            'title' => 'Comment ça marche ?',
            'lead' => 'Quatre étapes, moins d’une minute.',
            'steps' => [
                [
                    'number' => '1',
                    'icon' => 'smartphone',
                    'title' => 'Choisissez votre recharge',
                    'text' => 'PAPITO pour les appels et SMS, NETI pour l’internet mobile.',
                ],
                [
                    'number' => '2',
                    'icon' => 'hash',
                    'title' => 'Indiquez le numéro à recharger',
                    'text' => 'Le vôtre ou celui d’un proche, sur le réseau Manuia.',
                ],
                [
                    'number' => '3',
                    'icon' => 'credit-card',
                    'title' => 'Payez par carte bancaire',
                    'text' => 'Paiement sécurisé, aucun compte obligatoire.',
                ],
                [
                    'number' => '4',
                    'icon' => 'mail',
                    'title' => 'Recevez votre code',
                    'text' => 'Par email et SMS, immédiatement après le paiement.',
                ],
            ],
        ],
        [
            'acf_fc_layout' => 'reassurance',
            'items' => [
                [
                    'title' => 'Paiement sécurisé',
                    'text' => 'Transaction protégée',
                ],
                [
                    'title' => 'Livraison contrôlée',
                    'text' => 'Uniquement après paiement',
                ],
                [
                    'title' => 'Prix transparents',
                    'text' => 'Aucun frais caché',
                ],
                [
                    'title' => 'Support client',
                    'text' => 'Une aide en cas de besoin',
                ],
            ],
        ],
        [
            'acf_fc_layout' => 'recharge_faq',
            'eyebrow' => '',
            'title' => 'Questions fréquentes',
            'intro_text' => 'Vous ne trouvez pas votre réponse ? Notre équipe répond par email sous 24 h ouvrées.',
            'button_label' => 'Nous contacter',
            'button_url' => '#contact-cta',
            'items' => [
                [
                    'question' => 'Quelle est la différence entre PAPITO et NETI ?',
                    'answer' => 'PAPITO est une recharge voix : du crédit d’appel accompagné de SMS offerts. NETI est une recharge internet : de la data pour naviguer, échanger et partager votre connexion.',
                ],
                [
                    'question' => 'Quand est-ce que je reçois mon code ?',
                    'answer' => 'Le code est envoyé par email et par SMS dès la validation du paiement, en général en moins d’une minute.',
                ],
                [
                    'question' => 'Puis-je recharger le téléphone de quelqu’un d’autre ?',
                    'answer' => 'Oui. Il suffit d’indiquer le numéro Manuia du bénéficiaire au moment de la commande. Le code peut aussi être envoyé directement sur son téléphone.',
                ],
                [
                    'question' => 'Quels moyens de paiement acceptez-vous ?',
                    'answer' => 'Le paiement se fait par carte bancaire, via une plateforme sécurisée. Aucune donnée de carte n’est conservée sur le site.',
                ],
                [
                    'question' => 'Je n’ai pas reçu mon code, que faire ?',
                    'answer' => 'Vérifiez d’abord le dossier indésirables de votre messagerie. Si le code ne s’y trouve pas, écrivez-nous à contact@rechargetonmanuia.wf en précisant votre numéro de commande.',
                ],
                [
                    'question' => 'Comment utiliser mon code de recharge ?',
                    'answer' => 'Depuis le téléphone à recharger, composez la séquence indiquée dans l’email, puis validez. Le crédit ou la data est activé immédiatement.',
                ],
            ],
        ],
        [
            'acf_fc_layout' => 'recharge_cta',
            'eyebrow' => '',
            'title' => 'Une question sur votre recharge ?',
            'text' => 'Écrivez-nous en précisant votre numéro de commande, nous revenons vers vous rapidement.',
            'button_label' => 'contact@rechargetonmanuia.wf',
            'button_url' => 'mailto:contact@rechargetonmanuia.wf',
            'secondary_button_label' => 'Voir l’aide',
            'secondary_button_url' => '#faq',
        ],
    ];
}

function tealforge_get_default_recharge_products(): array
{
    return [
        [
            'family' => 'Papito',
            'type' => 'Voix',
            'amount' => '1 000 F',
            'description' => 'Crédit voix national',
            'bonus' => '',
            'featured' => false,
            'sku' => 'papito-voix-1000',
            'tone' => 'papito',
        ],
        [
            'family' => 'Papito',
            'type' => 'Voix',
            'amount' => '3 000 F',
            'description' => 'Crédit voix + bonus SMS',
            'bonus' => '+500 F offerts',
            'featured' => true,
            'sku' => 'papito-voix-3000',
            'tone' => 'papito',
        ],
        [
            'family' => 'Papito',
            'type' => 'Voix',
            'amount' => '5 000 F',
            'description' => 'Crédit voix longue durée',
            'bonus' => '',
            'featured' => false,
            'sku' => 'papito-voix-5000',
            'tone' => 'papito',
        ],
        [
            'family' => 'Neti',
            'type' => 'Data',
            'amount' => '1 000 F',
            'description' => 'Forfait data mobile',
            'bonus' => '',
            'featured' => false,
            'sku' => 'neti-data-1000',
            'tone' => 'neti',
        ],
        [
            'family' => 'Neti',
            'type' => 'Data',
            'amount' => '3 000 F',
            'description' => 'Forfait data + réseau 4G',
            'bonus' => '+1 Go offert',
            'featured' => true,
            'sku' => 'neti-data-3000',
            'tone' => 'neti',
        ],
        [
            'family' => 'Neti',
            'type' => 'Data',
            'amount' => '5 000 F',
            'description' => 'Forfait data illimité 30j',
            'bonus' => '',
            'featured' => false,
            'sku' => 'neti-data-5000',
            'tone' => 'neti',
        ],
    ];
}

function tealforge_get_default_recharge_product_ids(): array
{
    $product_ids = [];

    foreach (tealforge_get_default_recharge_products() as $product) {
        $product_id = tealforge_get_product_id_by_sku((string) ($product['sku'] ?? ''));

        if ($product_id > 0) {
            $product_ids[] = $product_id;
        }
    }

    return $product_ids;
}

function tealforge_normalize_product_id(mixed $product): int
{
    if (is_numeric($product)) {
        return (int) $product;
    }

    if ($product instanceof WP_Post) {
        return (int) $product->ID;
    }

    if (is_object($product) && method_exists($product, 'get_id')) {
        return (int) $product->get_id();
    }

    return 0;
}

function tealforge_get_recharge_product_fallback(string $sku): array
{
    foreach (tealforge_get_default_recharge_products() as $product) {
        if (($product['sku'] ?? '') === $sku) {
            return $product;
        }
    }

    return [];
}

function tealforge_get_recharge_product_field(int $product_id, string $name): mixed
{
    if (! function_exists('get_field')) {
        return null;
    }

    return get_field($name, $product_id);
}

function tealforge_get_recharge_product_data(int $product_id): array
{
    if ($product_id <= 0) {
        return [];
    }

    $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
    $sku = $product && method_exists($product, 'get_sku') ? (string) $product->get_sku() : '';
    $fallback = tealforge_get_recharge_product_fallback($sku);
    $title = get_the_title($product_id);
    $price = $product && method_exists($product, 'get_price') ? (string) $product->get_price() : '';
    $featured = get_post_meta($product_id, 'recharge_featured', true);
    $voucher_managed = get_post_meta($product_id, '_spt_vouchers_managed', true) === 'yes';
    $filtered_voucher_stock = apply_filters('spt_vouchers_available_stock', null, $product_id);
    $voucher_stock = $voucher_managed
        ? max(0, is_numeric($filtered_voucher_stock) ? (int) $filtered_voucher_stock : (int) $product?->get_stock_quantity())
        : null;
    $can_quick_add = $product
        && method_exists($product, 'is_type')
        && $product->is_type('simple')
        && $product->is_purchasable()
        && $product->is_in_stock();
    $max_quantity = $can_quick_add ? (int) $product->get_max_purchase_quantity() : 0;

    if ($can_quick_add && ($max_quantity < 1 || $max_quantity > 10)) {
        $max_quantity = 10;
    }

    $stock_label = '';
    $unavailable_label = __('Indisponible', 'tealforge');

    if ($voucher_stock !== null && $voucher_stock > 0) {
        $stock_label = sprintf(
            _n('%d code disponible', '%d codes disponibles', $voucher_stock, 'tealforge'),
            $voucher_stock
        );

        if (!$can_quick_add) {
            $unavailable_label = __('Vente bientôt disponible', 'tealforge');
        }
    } elseif ($voucher_stock === 0) {
        $stock_label = __('Stock épuisé', 'tealforge');
        $unavailable_label = __('Stock épuisé', 'tealforge');
    }

    return [
        'id' => $product_id,
        'family' => (string) (tealforge_get_recharge_product_field($product_id, 'recharge_family') ?: ($fallback['family'] ?? $title)),
        'type' => (string) (tealforge_get_recharge_product_field($product_id, 'recharge_type') ?: ($fallback['type'] ?? '')),
        'amount' => (string) (tealforge_get_recharge_product_field($product_id, 'recharge_amount') ?: ($fallback['amount'] ?? ($price !== '' ? number_format((float) $price, 0, ',', ' ') . ' F' : ''))),
        'purchase_price' => $product && $price !== ''
            ? number_format_i18n(wc_get_price_to_display($product), wc_get_price_decimals())
            : '',
        'description' => (string) (tealforge_get_recharge_product_field($product_id, 'recharge_description') ?: ($fallback['description'] ?? get_the_excerpt($product_id))),
        'bonus' => (string) (tealforge_get_recharge_product_field($product_id, 'recharge_bonus') ?: ($fallback['bonus'] ?? '')),
        'featured' => $featured === '' ? (bool) ($fallback['featured'] ?? false) : (bool) $featured,
        'sku' => $sku,
        'tone' => (string) (tealforge_get_recharge_product_field($product_id, 'recharge_tone') ?: ($fallback['tone'] ?? 'papito')),
        'url' => (string) get_permalink($product_id),
        'can_quick_add' => $can_quick_add,
        'voucher_managed' => $voucher_managed,
        'stock_quantity' => $voucher_stock,
        'stock_label' => $stock_label,
        'unavailable_label' => $unavailable_label,
        'max_quantity' => $max_quantity,
        'add_to_cart_action' => home_url('/#recharges'),
    ];
}

function tealforge_prepare_page_sections(array $sections): array
{
    foreach ($sections as $section_index => $section) {
        if (($section['acf_fc_layout'] ?? '') === 'recharge_hero') {
            $image = $section['hero_image'] ?? null;
            $image_id = is_array($image) ? (int) ($image['ID'] ?? 0) : (is_numeric($image) ? (int) $image : 0);
            $sections[$section_index]['hero_image_html'] = $image_id > 0
                ? wp_get_attachment_image($image_id, 'full', false, [
                    'class' => 'tf-recharge-hero__image',
                    'alt' => '',
                    'sizes' => '(max-width: 1000px) 100vw, 50vw',
                    'loading' => 'eager',
                    'decoding' => 'async',
                    'fetchpriority' => 'high',
                ])
                : '';
            $sections[$section_index]['hero_image'] = tealforge_get_section_image_url(
                $section['hero_image'] ?? null,
                'assets/images/visuel-lagon.svg'
            );

            $sections[$section_index]['hero_text_html'] = str_replace(
                ['PAPITO', 'NETI'],
                ['<strong class="tf-recharge-hero__papito">PAPITO</strong>', '<strong class="tf-recharge-hero__neti">NETI</strong>'],
                esc_html((string) ($section['text'] ?? ''))
            );
            $sections[$section_index]['benefits'] = [];

            foreach (['benefit_simple' => 'phone', 'benefit_fast' => 'zap', 'benefit_secure' => 'shield-check'] as $field => $icon) {
                $label = trim((string) ($section[$field] ?? ''));

                if ($label !== '') {
                    $sections[$section_index]['benefits'][] = ['label' => $label, 'icon' => $icon];
                }
            }
        }

        if (($section['acf_fc_layout'] ?? '') === 'recharge_products') {
            $products = $section['products'] ?? [];

            if (! is_array($products) || $products === []) {
                $products = tealforge_get_default_recharge_product_ids();
            }

            $prepared_products = [];

            foreach ($products as $product) {
                if (is_array($product) && isset($product['sku'])) {
                    $product_id = tealforge_get_product_id_by_sku((string) $product['sku']);
                    $prepared_products[] = array_merge(tealforge_get_recharge_product_data($product_id), $product, [
                        'id' => $product_id,
                        'url' => tealforge_get_product_url_by_sku((string) $product['sku']),
                    ]);
                    continue;
                }

                $product_data = tealforge_get_recharge_product_data(tealforge_normalize_product_id($product));

                if ($product_data !== []) {
                    $prepared_products[] = $product_data;
                }
            }

            $sections[$section_index]['products'] = $prepared_products;
            $product_groups = [
                'papito' => [
                    'title' => (string) ($section['papito_title'] ?? 'PAPITO'),
                    'subtitle' => (string) ($section['papito_subtitle'] ?? ''),
                    'description' => (string) ($section['papito_description'] ?? ''),
                    'delivery' => (string) ($section['papito_delivery'] ?? ''),
                    'tone' => 'papito',
                    'icon' => 'phone',
                    'bonus_icon' => 'message-circle',
                    'products' => [],
                ],
                'neti' => [
                    'title' => (string) ($section['neti_title'] ?? 'NETI'),
                    'subtitle' => (string) ($section['neti_subtitle'] ?? ''),
                    'description' => (string) ($section['neti_description'] ?? ''),
                    'delivery' => (string) ($section['neti_delivery'] ?? ''),
                    'tone' => 'neti',
                    'icon' => 'globe',
                    'bonus_icon' => 'wifi',
                    'products' => [],
                ],
            ];

            foreach ($prepared_products as $prepared_product) {
                $tone = (string) ($prepared_product['tone'] ?? 'papito');

                if (! isset($product_groups[$tone])) {
                    $tone = 'papito';
                }

                $product_groups[$tone]['products'][] = $prepared_product;
            }

            $sections[$section_index]['product_groups'] = array_values(array_filter(
                $product_groups,
                static fn(array $group): bool => $group['products'] !== []
            ));
            $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
            $sections[$section_index]['currency_label'] = $currency === 'XPF'
                ? 'F CFP'
                : (function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol($currency) : '');
        }

        if (($section['acf_fc_layout'] ?? '') === 'recharge_steps') {
            $sections[$section_index]['lead'] = trim((string) ($section['lead'] ?? ''))
                ?: 'Quatre étapes, moins d’une minute.';
            $step_icons = ['smartphone', 'hash', 'credit-card', 'mail'];
            $step_tones = ['red', 'blue', 'turquoise', 'blue'];

            foreach ((array) ($section['steps'] ?? []) as $step_index => $step) {
                $sections[$section_index]['steps'][$step_index]['icon'] = tealforge_resolve_section_icon(
                    $step['icon'] ?? '',
                    $step_icons[$step_index] ?? 'circle-check'
                );
                $sections[$section_index]['steps'][$step_index]['tone'] = $step_tones[$step_index] ?? 'blue';
            }
        }

        if (($section['acf_fc_layout'] ?? '') === 'reassurance') {
            $reassurance_icons = ['shield-check', 'mail', 'smartphone', 'headphones'];

            foreach ((array) ($section['items'] ?? []) as $item_index => $item) {
                $sections[$section_index]['items'][$item_index]['icon'] = tealforge_resolve_section_icon(
                    $item['icon'] ?? '',
                    $reassurance_icons[$item_index] ?? 'circle-check'
                );
            }
        }
    }

    return $sections;
}

function tealforge_resolve_section_icon(mixed $icon, string $fallback = 'circle-check'): string
{
    if (! is_string($icon)) {
        return $fallback;
    }

    $allowed_icons = [
        'circle-check',
        'clock',
        'credit-card',
        'headphones',
        'hash',
        'lock',
        'mail',
        'map-pin',
        'message-circle',
        'shopping-cart',
        'shield-check',
        'smartphone',
        'undo',
        'users',
        'wifi',
        'zap',
    ];
    $icon = sanitize_key($icon);

    return in_array($icon, $allowed_icons, true) ? $icon : $fallback;
}

function tealforge_split_highlighted_title(string $title, string $highlight): array
{
    $position = function_exists('mb_stripos')
        ? mb_stripos($title, $highlight)
        : stripos($title, $highlight);

    if (false === $position) {
        return [
            'title_before' => $title,
            'title_highlight' => '',
            'title_after' => '',
        ];
    }

    $substring = static function (string $value, int $start, ?int $length = null): string {
        if (function_exists('mb_substr')) {
            return null === $length ? mb_substr($value, $start) : mb_substr($value, $start, $length);
        }

        return null === $length ? substr($value, $start) : substr($value, $start, $length);
    };

    $highlight_length = function_exists('mb_strlen') ? mb_strlen($highlight) : strlen($highlight);

    return [
        'title_before' => $substring($title, 0, (int) $position),
        'title_highlight' => $substring($title, (int) $position, $highlight_length),
        'title_after' => $substring($title, (int) $position + $highlight_length),
    ];
}

function tealforge_get_section_image_url(mixed $image, string $fallback_path): string
{
    if (is_array($image) && ! empty($image['url'])) {
        return (string) $image['url'];
    }

    if (is_numeric($image)) {
        $image_url = wp_get_attachment_image_url((int) $image, 'full');

        if ($image_url) {
            return $image_url;
        }
    }

    if (is_string($image) && $image !== '') {
        return $image;
    }

    return get_theme_file_uri($fallback_path);
}
