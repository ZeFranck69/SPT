<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

$context = tealforge_get_context();
$context['post'] = Timber\Timber::get_post();

$contact_email = function_exists('get_field') ? trim((string) get_field('contact_email', 'option')) : '';
$contact_address = function_exists('get_field') ? trim((string) get_field('contact_address', 'option')) : '';
$contact_hours = function_exists('get_field') ? trim((string) get_field('contact_hours', 'option')) : '';

$contact_email = $contact_email !== '' ? $contact_email : 'support@spt.wf';
$contact_address = $contact_address !== '' ? $contact_address : 'Wallis et Futuna';
$contact_hours = $contact_hours !== '' ? $contact_hours : '24h/24 - 7j/7';

$context['contact'] = [
    'email' => $contact_email,
    'location' => $contact_address,
    'hours' => $contact_hours,
    'items' => [
        [
            'icon' => 'mail',
            'label' => 'Email',
            'value' => $contact_email,
            'url' => 'mailto:' . sanitize_email($contact_email),
        ],
        [
            'icon' => 'clock',
            'label' => 'Horaires',
            'value' => $contact_hours,
            'url' => '',
        ],
        [
            'icon' => 'map-pin',
            'label' => 'Adresse',
            'value' => $contact_address,
            'url' => '',
        ],
    ],
    'faq' => [
        [
            'question' => 'Combien de temps prend la recharge ?',
            'answer' => 'Les codes sont envoyés en quelques secondes après validation du paiement.',
        ],
        [
            'question' => 'Puis-je acheter pour un proche ?',
            'answer' => 'Oui, il suffit de renseigner le numéro de téléphone du destinataire lors de la commande.',
        ],
        [
            'question' => 'Quels modes de paiement sont acceptés ?',
            'answer' => 'Le paiement par carte bancaire sera proposé avec la solution retenue pour le projet.',
        ],
        [
            'question' => 'Que faire si mon code n\'arrive pas ?',
            'answer' => 'Contactez le support avec votre référence de commande pour permettre une vérification rapide.',
        ],
    ],
];

tealforge_render('pages/contact.twig', $context);
