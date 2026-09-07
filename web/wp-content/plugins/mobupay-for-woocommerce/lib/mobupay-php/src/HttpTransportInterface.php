<?php

declare(strict_types=1);

namespace Mobupay;

/**
 * Transport HTTP injectable (PLAN-291, lot 1.A.2).
 *
 * Le SDK n'impose pas cURL : chaque environnement peut fournir son propre
 * transport (ex. l'API HTTP de WordPress via `wp_remote_request`, exigee par la
 * revue du repertoire wordpress.org). Sans transport explicite, MobupayClient
 * utilise CurlTransport.
 */
interface HttpTransportInterface
{
    /**
     * Execute une requete HTTP et retourne le statut + corps bruts.
     * Ne DOIT PAS lever d'exception sur un statut HTTP >= 400 (le client s'en
     * charge) ; ne lever MobupayException que sur une erreur reseau/transport.
     *
     * @param string               $method  Verbe HTTP (GET, POST, ...).
     * @param string               $url     URL absolue.
     * @param array<string,string> $headers Headers (cle => valeur).
     * @param string|null          $body    Corps deja serialise (JSON), ou null.
     * @param int                  $timeout Timeout en secondes.
     * @return array{status:int, body:string}
     * @throws MobupayException En cas d'echec reseau (DNS, timeout, TLS...).
     */
    public function request(string $method, string $url, array $headers, ?string $body, int $timeout): array;
}
