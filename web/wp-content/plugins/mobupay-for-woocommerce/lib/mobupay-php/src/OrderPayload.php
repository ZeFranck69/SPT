<?php

declare(strict_types=1);

namespace Mobupay;

/**
 * Noyau arithmetique de construction de l'objet `order`.
 *
 * PLAN-598 lot C3. Ce code a d'abord vecu dans le plugin WooCommerce (PLAN-581),
 * puis a ete reecrit a l'identique pour PrestaShop. L'avoir vu DEUX fois est ce qui
 * justifie de le sortir ici : trois copies auraient fini par diverger, et une
 * divergence sur cette arithmetique ne se voit pas en revue, elle se voit en
 * production sous la forme d'un paiement refuse.
 *
 * Ce qui vit ici est exactement ce qui ne depend d'AUCUNE plateforme : composer une
 * ligne a partir d'un brut et d'un net, faire tomber la somme sur le montant, poser
 * un champ non vide, tronquer un libelle, convertir en unite mineure. Ce qui reste
 * dans chaque connecteur est la LECTURE de la commande, qui elle est propre a
 * chaque boutique.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * LES REGLES QUE CE NOYAU FAIT RESPECTER, chacune tiree d'un piege reel
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * 1. LES PRIX SONT TAXE COMPRISE. `isInclTaxAmount` vaut `true` par defaut cote
 *    API : les `unitPrice` recus sont compris comme des montants TTC, et c'est le
 *    serveur qui extrait le HT pour la facture. Le connecteur doit donc TOUJOURS
 *    passer ici des montants taxe comprise.
 *
 * 2. LA SOMME DOIT TOMBER EXACTEMENT SUR LE MONTANT. `validateOrderAmounts()` exige
 *    Somme(unitPrice x quantity - remise de ligne) - remise de commande = amount, a
 *    l'unite pres et SANS MARGE. Chaque boutique arrondit la taxe a sa facon : un
 *    ecart d'une unite est courant, et il refuse le paiement. `reconcile()` le
 *    resorbe explicitement.
 *
 * 3. UNE REMISE DE QUELQUES CENTIMES PEUT APPARAITRE SANS AUCUNE REMISE REELLE.
 *    `unitPrice` est un entier : 9,99 HT a 20 % font 11,988 TTC, non representable.
 *    On arrondit au superieur et l'ecart part en `discount` de ligne. Le net, la base
 *    imposable et le total restent EXACTS ; seul l'affichage porte une remise de
 *    un a quatre centimes. Toute autre solution exigerait un prix unitaire
 *    fractionnaire, que l'API n'accepte pas, ou de renoncer a la quantite, que la
 *    facture exige. C'est le prix de l'encodage en entiers.
 *
 * 4. EN CAS DE DOUTE, ON RENONCE AU DETAIL, JAMAIS AU PAIEMENT. Chaque fonction rend
 *    `null` plutot qu'une valeur dont elle n'est pas sure, et l'appelant retombe sur
 *    la charge minimale. Un detail manquant est un desagrement ; un paiement refuse
 *    est une vente perdue.
 */
final class OrderPayload
{
    /** Longueur max d'un libelle de ligne (confort de lecture de la facture). */
    public const MAX_LABEL = 200;

    /**
     * Convertit un montant decimal en unite mineure.
     *
     * Le XPF n'a PAS de decimale : 5 000 XPF valent 5000, pas 500000. Se tromper ici
     * multiplie ou divise par cent tous les montants d'une boutique caledonienne.
     */
    public static function toMinorUnits($amount, string $currencyIso): int
    {
        $factor = strtoupper($currencyIso) === 'XPF' ? 1 : 100;

        return (int) round((float) $amount * $factor);
    }

    /**
     * Compose une ligne a partir de son brut et de son net, tous deux TAXE COMPRISE.
     *
     * @param string $label                 libelle de l'article
     * @param float  $quantity              quantite, eventuellement fractionnaire
     * @param int    $grossTtc              total de ligne AVANT remise, en unite mineure
     * @param int    $netTtc                total de ligne APRES remise, en unite mineure
     * @param array  $taxDetail             cf. percentTax()
     * @param string $quantityLabelFormat   format sprintf du libelle de quantite
     *                                      fractionnaire, DEJA traduit par l'appelant :
     *                                      le SDK ne connait pas le systeme de
     *                                      traduction de la boutique
     * @param string $fallbackLabel         libelle de repli, deja traduit
     *
     * @return array|null null si la ligne est incoherente (l'appelant degrade)
     */
    public static function composeLine(
        string $label,
        float $quantity,
        int $grossTtc,
        int $netTtc,
        array $taxDetail = [],
        string $quantityLabelFormat = 'Quantité : %s',
        string $fallbackLabel = 'Article'
    ): ?array {
        if ($netTtc < 0) {
            return null;
        }

        $description = null;
        $qty = (int) round($quantity);
        if (abs($quantity - $qty) > 0.0001 || $qty < 1) {
            // Quantite fractionnaire (vente au poids, au metre) : l'API veut un
            // entier. On ramene a 1 et la quantite reelle passe en description, ou
            // elle reste lisible sur la facture du client.
            $description = sprintf($quantityLabelFormat, (string) $quantity);
            $qty = 1;
        }

        $unitPrice = (int) ceil(max($grossTtc, $netTtc) / $qty);
        $discount = $unitPrice * $qty - $netTtc;
        if ($discount < 0) {
            return null; // ne devrait pas arriver, mais on ne devine pas
        }

        $line = [
            'product' => self::trimLabel($label, $fallbackLabel),
            'unitPrice' => $unitPrice,
            'quantity' => $qty,
        ];
        if ($description !== null) {
            $line['description'] = $description;
        }
        if ($discount > 0) {
            $line['discount'] = $discount;
        }
        if (!empty($taxDetail)) {
            $line['taxDetail'] = $taxDetail;
        }

        return $line;
    }

    /**
     * Une taxe en pourcentage, exprimee en CENTIEMES DE POURCENT.
     *
     * Convention unique du depot : 1 % = 100. Une TGC a 11 % vaut donc 1100 et une
     * TVA a 5,5 % vaut 550. Le demi-point ne doit jamais se perdre.
     *
     * Le libelle vient des DONNEES de la boutique, jamais d'une table par pays :
     * « TGC » pour une boutique caledonienne, « TVA » pour une francaise, sans une
     * ligne de code par juridiction.
     *
     * @return array liste vide si le taux est nul (rien a declarer)
     */
    public static function percentTax(float $rate, string $label, string $fallbackLabel = 'Taxe'): array
    {
        $hundredths = (int) round($rate * 100);
        if ($hundredths <= 0) {
            return [];
        }

        return [[
            'id' => self::trimLabel($label, $fallbackLabel),
            'type' => 'PERCENTAGE',
            'value' => $hundredths,
        ]];
    }

    /**
     * Rend la somme des lignes exactement egale au montant (regle 2).
     *
     * Deux cas, dans cet ordre de preference :
     *  - somme trop GRANDE : l'ecart part en remise de niveau commande, ce qui
     *    n'altere aucun prix affiche ;
     *  - somme trop PETITE : on compense sur la DERNIERE ligne, en augmentant son
     *    prix unitaire du minimum necessaire et en absorbant le depassement dans sa
     *    remise, de sorte que sa contribution augmente de l'ecart exact.
     *
     * @return array{items: array, discount: int, adjusted: bool}|null
     */
    public static function reconcile(array $items, int $orderDiscount, int $amount): ?array
    {
        $expected = self::sumItems($items) - $orderDiscount;

        if ($expected === $amount) {
            return ['items' => $items, 'discount' => $orderDiscount, 'adjusted' => false];
        }

        if ($expected > $amount) {
            return [
                'items' => $items,
                'discount' => $orderDiscount + ($expected - $amount),
                'adjusted' => true,
            ];
        }

        $missing = $amount - $expected;
        $lastIndex = count($items) - 1;
        if ($lastIndex < 0) {
            return null;
        }
        $qty = (int) $items[$lastIndex]['quantity'];
        if ($qty < 1) {
            return null;
        }
        $delta = (int) ceil($missing / $qty);
        $items[$lastIndex]['unitPrice'] = (int) $items[$lastIndex]['unitPrice'] + $delta;
        $overshoot = $delta * $qty - $missing;
        if ($overshoot > 0) {
            $items[$lastIndex]['discount'] = (int) ($items[$lastIndex]['discount'] ?? 0) + $overshoot;
        }

        // Verification finale : on ne renvoie JAMAIS une charge dont on n'est pas sur.
        if (self::sumItems($items) - $orderDiscount !== $amount) {
            return null;
        }

        return ['items' => $items, 'discount' => $orderDiscount, 'adjusted' => true];
    }

    /** Somme des lignes, exactement comme le serveur la calcule. */
    public static function sumItems(array $items): int
    {
        $sum = 0;
        foreach ($items as $line) {
            $sum += (int) $line['unitPrice'] * (int) $line['quantity'] - (int) ($line['discount'] ?? 0);
        }

        return $sum;
    }

    /**
     * N'ecrit que les valeurs non vides : l'API distingue « absent » de « vide », et
     * une chaine vide sur un champ d'adresse compte comme une mention renseignee,
     * donc comme une facture emissible alors qu'elle ne l'est pas.
     */
    public static function put(array &$target, string $key, $value): void
    {
        $value = trim((string) $value);
        if ($value !== '') {
            $target[$key] = $value;
        }
    }

    /** Libelle propre, sans balise, borne a MAX_LABEL. */
    public static function trimLabel(string $label, string $fallback = 'Article'): string
    {
        $label = trim(strip_tags($label));
        if ($label === '') {
            return $fallback;
        }
        if (function_exists('mb_substr')) {
            return mb_substr($label, 0, self::MAX_LABEL);
        }

        return substr($label, 0, self::MAX_LABEL);
    }

    /**
     * Adresse au format attendu par l'API, dans sa FORME CANONIQUE.
     *
     * PLAN-598 lot C3, et cette fonction existe a cause d'un defaut reel trouve en
     * la factorisant : WooCommerce envoyait `line1` / `line2`, PrestaShop `street` /
     * `street2`. Or `OrderAddressSchema` ne definit PAS `street2` : Zod supprime les
     * cles inconnues, donc le complement d'adresse partait a la poubelle en silence.
     * Une adresse incomplete, c'est une facture non emissible.
     *
     * La forme canonique est `street` (+ `streetNumber`) et `complement`. `line1` et
     * `line2` sont des ALIAS acceptes pour ne rien perdre d'un integrateur qui les
     * envoie par reflexe, pas une seconde convention : nos propres connecteurs
     * emettent la forme canonique, et une seule.
     *
     * Le pays part en code ISO 3166-1 alpha-2 : l'API refuse un nom en clair, et
     * c'est aussi ce qu'attend le XML Factur-X.
     *
     * Cles acceptees : street, streetNumber, complement, city, postalCode, country,
     * company, firstName, lastName, phone, email. Tout le reste est ignore, et les
     * valeurs vides ne sont jamais posees.
     */
    public static function composeAddress(array $parts): array
    {
        $out = [];
        self::put($out, 'streetNumber', $parts['streetNumber'] ?? '');
        self::put($out, 'street', $parts['street'] ?? '');
        self::put($out, 'complement', $parts['complement'] ?? '');
        self::put($out, 'city', $parts['city'] ?? '');
        self::put($out, 'postalCode', $parts['postalCode'] ?? '');
        self::put($out, 'country', strtoupper(trim((string) ($parts['country'] ?? ''))));
        // La raison sociale, quand le client l'a saisie, sert de libelle : c'est elle
        // qui doit figurer sur la facture d'un professionnel.
        self::put($out, 'label', $parts['company'] ?? '');
        self::put($out, 'firstName', $parts['firstName'] ?? '');
        self::put($out, 'lastName', $parts['lastName'] ?? '');
        self::put($out, 'phone', $parts['phone'] ?? '');
        self::put($out, 'email', $parts['email'] ?? '');

        return $out;
    }
}
