<?php

declare(strict_types=1);

namespace Mobupay;

/**
 * Client HTTP Mobupay (PLAN-177, lot 0.F).
 *
 * Wrapper mince autour de l'API REST Mobupay, utilise par les connecteurs
 * e-commerce. Le client ne manipule jamais de donnees carte : il cree des
 * sessions / liens de paiement, le client final paie sur la page hebergee
 * Mobupay (widget Monext), puis le marchand est notifie par webhook signe.
 *
 * Montants : toujours en centimes EUR en interne (convention Mobupay).
 *
 * Exemple :
 *   $client = new MobupayClient('sk_test_xxx');                 // env test (sandbox)
 *   $client = new MobupayClient('sk_live_xxx');                 // env live
 *   // Transport HTTP personnalise (defaut : cURL) :
 *   $client = new MobupayClient('sk_test_xxx', 'https://api.mobupay.nc', 30, $monTransport);
 *   $session = $client->createCheckoutSession(
 *       ['reference' => 'CMD-1042', 'amount' => 2500, 'currency' => 'EUR'],
 *       'https://maboutique.nc/commande/merci',
 *       'https://maboutique.nc/?wc-api=mobupay',
 *       ['externalId' => '1042']                                // id de commande boutique
 *   );
 *   header('Location: ' . $session['checkoutUrl']);
 */
final class MobupayClient
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private HttpTransportInterface $transport;

    /**
     * @param string                      $apiKey    Cle API marchand (`sk_test_*` ou `sk_live_*`).
     * @param string                      $baseUrl   Base de l'API (defaut : production).
     * @param int                         $timeout   Timeout HTTP en secondes.
     * @param HttpTransportInterface|null $transport Transport HTTP. Defaut : CurlTransport.
     *                                               Permet aux integrations d'imposer leur pile
     *                                               HTTP (ex. `wp_remote_request` sous WordPress).
     */
    public function __construct(
        string $apiKey,
        string $baseUrl = 'https://api.mobupay.nc',
        int $timeout = 30,
        ?HttpTransportInterface $transport = null
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Cle API Mobupay manquante.');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->transport = $transport ?? new CurlTransport();
    }

    /**
     * Cree une session de paiement (page hebergee). Rediriger le client vers
     * `checkoutUrl`. Retour : ['paymentId', 'sessionId', 'checkoutUrl', 'token'].
     *
     * @param array       $order          Commande : ['reference'(req), 'amount'(req, centimes),
     *                                     'currency'(req, 'EUR'|'XPF'), ...].
     * @param string      $redirectUrl    URL de retour du client apres paiement.
     * @param string      $notificationUrl URL webhook (recevra les events signes du paiement).
     * @param array       $opts           Options : ['externalId', 'customerId', 'email',
     *                                     'captureMode'('AUTO'|'MANUAL'), 'languageCode', 'privateData'].
     * @param string|null $idempotencyKey  Cle d'idempotence (recommande : l'id de commande). Un
     *                                     rejeu renvoie la session existante au lieu d'en creer une 2e.
     */
    public function createCheckoutSession(
        array $order,
        string $redirectUrl,
        string $notificationUrl,
        array $opts = [],
        ?string $idempotencyKey = null
    ): array {
        $body = array_merge($opts, [
            'order' => $order,
            'redirectUrl' => $redirectUrl,
            'notificationUrl' => $notificationUrl,
        ]);
        return $this->request('POST', '/api/v1/payments/sessions', $body, $idempotencyKey);
    }

    /**
     * Cree un lien de paiement (pay-by-link). Retour : ['paymentId', 'linkId', 'linkUrl', 'expiresAt'].
     */
    public function createCheckoutLink(
        array $order,
        string $redirectUrl,
        string $notificationUrl,
        array $opts = [],
        ?string $idempotencyKey = null
    ): array {
        $body = array_merge($opts, [
            'order' => $order,
            'redirectUrl' => $redirectUrl,
            'notificationUrl' => $notificationUrl,
        ]);
        return $this->request('POST', '/api/v1/payments/links', $body, $idempotencyKey);
    }

    /**
     * Rembourse (ou annule) un paiement. $amount en centimes pour un remboursement
     * partiel ; null = total. Les remboursements partiels/multiples sont supportes
     * tant que la somme n'excede pas le montant initial.
     */
    /**
     * Rembourse tout ou partie d'un paiement.
     *
     * ATTENTION AU NOM DU CHAMP. L'API attend `amountCents`, DANS LA DEVISE
     * D'ORIGINE du paiement (des francs en XPF, des centimes en EUR). Ce SDK
     * envoyait `amount` : Zod ignore les cles inconnues, le montant etait donc
     * perdu en silence et l'API executait un remboursement TOTAL. Un marchand qui
     * demandait 555 XPF sur une commande de 11 655 en remboursait 11 655.
     * Constate en recette le 2026-08-24, sur un vrai paiement capture.
     *
     * @param int|null    $amount Montant dans la devise d'origine. `null` = total.
     * @param string|null $reason Motif, repris dans le journal d'audit cote Mobupay.
     */
    public function refund(string $paymentId, ?int $amount = null, ?string $reason = null): array
    {
        $body = [];
        if ($amount !== null) {
            $body['amountCents'] = $amount;
        }
        if ($reason !== null && $reason !== '') {
            $body['reason'] = $reason;
        }
        return $this->request('POST', '/api/v1/payments/' . rawurlencode($paymentId) . '/refund', $body);
    }

    /** Recupere le detail d'un paiement (source de verite cote serveur). */
    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', '/api/v1/payments/' . rawurlencode($paymentId));
    }

    /**
     * Recupere le secret de signature des webhooks au niveau du marchand
     * (`whsec_*`). A stocker pour verifier les webhooks (cf. Webhook::verify).
     */
    public function getSigningSecret(): string
    {
        $res = $this->request('GET', '/api/v1/webhooks/signing-secret');
        return $res['webhookSecret'] ?? '';
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     * @throws MobupayException
     */
    private function request(string $method, string $path, ?array $body = null, ?string $idempotencyKey = null): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'mobupay-php/1.0',
        ];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $payload = null;
        if ($body !== null && $method !== 'GET') {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers['Content-Type'] = 'application/json';
        }

        $res = $this->transport->request($method, $this->baseUrl . $path, $headers, $payload, $this->timeout);
        $status = $res['status'];

        $decoded = json_decode($res['body'], true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) && isset($decoded['message'])
                ? (string) $decoded['message']
                : 'Erreur API Mobupay (HTTP ' . $status . ')';
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- SDK multi-plateformes (fonctions WordPress indisponibles) ; le message est échappé à l'affichage par le consommateur.
            throw new MobupayException($message, $status, is_array($decoded) ? $decoded : null);
        }
        return is_array($decoded) ? $decoded : [];
    }
}
