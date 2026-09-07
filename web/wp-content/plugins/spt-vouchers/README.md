# SPT Vouchers

Plugin metier WooCommerce pour importer, stocker et attribuer des vouchers SPT uniques.

## Fonctionnalites de la version 0.2.0

- six champs d import manuels, un pour chaque produit Papito ou Neti ;
- lecture du format CSV SPT sans en-tete et separe par `|` ;
- chiffrement des numeros de voucher ;
- conservation du numero de serie et de la date d expiration ;
- controle global des doublons de voucher et de numero de serie ;
- statuts `available`, `reserved`, `sold` et `expired` ;
- suivi des imports et des stocks dans le back-office ;
- synchronisation du stock WooCommerce avec les vouchers valides ;
- reservation transactionnelle facultative lors de la commande ;
- passage au statut vendu uniquement apres confirmation du paiement ;
- liberation des reservations pour les commandes echouees ou annulees.

Les emails et SMS contenant les codes ne sont pas encore actifs.

## Configuration obligatoire

Ajouter une cle aleatoire stable d au moins 32 caracteres dans `wp-config.php` :

```php
define('SPT_VOUCHERS_ENCRYPTION_KEY', 'remplacer-par-une-cle-aleatoire-longue-et-secrete');
```

Cette valeur ne doit jamais etre versionnee ni modifiee apres le premier import.

## Produits attendus

Le plugin associe les six zones d import aux SKU suivants :

```text
papito-voix-1000
papito-voix-3000
papito-voix-5000
neti-data-1000
neti-data-3000
neti-data-5000
```

Ces produits sont automatiquement places sous gestion de stock du plugin. Sans voucher valide, ils sont indisponibles a la vente.

## Format CSV

Le fichier ne contient pas d en-tete. Chaque ligne utilise exactement trois colonnes :

```text
numero_voucher|numero_serie|date_expiration
```

Exemple :

```text
60900001551813|102260900001|2028-09-04
```

Regles appliquees :

- voucher : exactement 14 chiffres ;
- numero de serie : exactement 12 chiffres ;
- date d expiration : format `AAAA-MM-JJ` ;
- voucher deja expire : refuse ;
- doublon de voucher ou de numero de serie : ignore et comptabilise ;
- fichier deja importe : refuse.

Le produit est determine par la zone d import choisie. Si le nom respecte la convention `PAPITO_1000_...csv` ou `NETI_3000_...csv`, le plugin verifie aussi sa coherence avec la zone choisie.

## Import

Dans WordPress, ouvrir **Vouchers SPT > Importer**, sélectionner un ou plusieurs fichiers dans les six zones puis lancer l import. Les fichiers sont lus depuis leur emplacement temporaire et ne sont pas ajoutes a la mediatheque.

## Securite des ventes

L option **Attribution automatique** reste desactivee par defaut. Tant qu elle est desactivee, les six produits ne sont pas achetables, meme si des vouchers ont ete importes.

Avant de l activer :

1. importer des vouchers de test ;
2. verifier les stocks et les doublons ;
3. tester les paiements MobuPay acceptes, refuses et annules ;
4. confirmer les regles de remboursement ;
5. effectuer une sauvegarde.

Le plugin reserve le nombre exact de vouchers lors de la creation de la commande. Les codes ne deviennent vendus qu apres paiement confirme. Aucun code n est encore transmis au client dans cette version.

## Suppression

La desactivation ou la suppression normale conserve les tables. La suppression definitive des donnees exige la constante suivante lors de la desinstallation :

```php
define('SPT_VOUCHERS_REMOVE_DATA', true);
```
