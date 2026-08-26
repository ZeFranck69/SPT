<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

$context = tealforge_get_context();
$context['post'] = Timber\Timber::get_post();

$context['contact'] = [
    'email' => 'support@spt.wf',
    'location' => 'Wallis et Futuna',
    'hours' => '24h/24 - 7j/7',
    'items' => [
        [
            'icon' => 'mail',
            'label' => 'Email',
            'value' => 'support@spt.wf',
            'url' => 'mailto:support@spt.wf',
        ],
        [
            'icon' => 'clock',
            'label' => 'Horaires',
            'value' => '24h/24 - 7j/7',
            'url' => '',
        ],
        [
            'icon' => 'map-pin',
            'label' => 'Adresse',
            'value' => 'Wallis et Futuna',
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
