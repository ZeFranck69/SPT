<?php

declare(strict_types=1);

namespace Mobupay;

/**
 * Verification de la signature des webhooks Mobupay (PLAN-177, B3 + B4).
 *
 * Mobupay signe chaque livraison webhook. Le marchand DOIT verifier la signature
 * avant de traiter l'evenement, sinon un attaquant peut forger un faux
 * `payment.captured` et valider une commande non payee.
 *
 * Schemas de signature :
 *   - V2 (recommande, anti-rejeu) : header `X-Mobupay-Signature-V2` =
 *     HMAC-SHA256(`{X-Mobupay-Timestamp}.{corps_brut}`, secret). Le timestamp etant
 *     signe, une livraison interceptee ne peut pas etre rejouee plus tard (rejetee
 *     hors fenetre de tolerance).
 *   - V1 (historique) : header `X-Mobupay-Signature` = HMAC-SHA256(corps_brut, secret).
 *
 * Le secret est le `whsec_*` au niveau marchand (MobupayClient::getSigningSecret),
 * ou le secret de l'endpoint enregistre le cas echeant.
 */
final class Webhook
{
    /**
     * Verifie la signature et retourne l'evenement decode.
     *
     * @param string               $payload   Corps BRUT de la requete (php://input), non re-encode.
     * @param array<string,string> $headers   Headers de la requete (cles insensibles a la casse).
     * @param string               $secret    Secret de signature (`whsec_*`).
     * @param int                  $tolerance Fenetre de fraicheur du timestamp, en secondes (defaut 300).
     * @return array<string,mixed>            Evenement decode : ['id','type','createdAt','data'=>[...]].
     * @throws MobupayException                Si signature absente / invalide / horodatage hors fenetre.
     */
    public static function verify(string $payload, array $headers, string $secret, int $tolerance = 300): array
    {
        if ($secret === '') {
            throw new MobupayException('Secret de signature Mobupay manquant.');
        }

        $h = [];
        foreach ($headers as $k => $v) {
            $h[strtolower((string) $k)] = is_array($v) ? (string) reset($v) : (string) $v;
        }

        $sigV2 = $h['x-mobupay-signature-v2'] ?? '';
        $timestamp = $h['x-mobupay-timestamp'] ?? '';

        if ($sigV2 !== '' && $timestamp !== '') {
            // Schema V2 : anti-rejeu via timestamp signe.
            $age = abs(time() - (int) $timestamp);
            if ($age > $tolerance) {
                throw new MobupayException(
                    'Webhook Mobupay rejete : horodatage hors fenetre de tolerance (' . (int) $age . 's).'
                );
            }
            $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
            if (!hash_equals($expected, $sigV2)) {
                throw new MobupayException('Signature Mobupay (V2) invalide.');
            }
            return self::decode($payload);
        }

        // Repli V1 : HMAC du corps seul (pas d'anti-rejeu).
        $sigV1 = $h['x-mobupay-signature'] ?? '';
        if ($sigV1 === '') {
            throw new MobupayException('Signature Mobupay absente (ni V2 ni V1).');
        }
        $expected = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expected, $sigV1)) {
            throw new MobupayException('Signature Mobupay (V1) invalide.');
        }
        return self::decode($payload);
    }

    /** @return array<string,mixed> */
    private static function decode(string $payload): array
    {
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            throw new MobupayException('Corps de webhook Mobupay illisible (JSON invalide).');
        }
        return $event;
    }
}
