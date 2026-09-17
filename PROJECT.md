# PROJECT.md - SPT

Ce fichier décrit le projet courant.

Il sert de contexte à l'équipe et à Codex. Il doit rester court, concret et mis à
jour quand une décision importante change.

Ne jamais mettre ici :

- mot de passe ;
- clé API ;
- clé SSH privée ;
- token ;
- accès SMTP ;
- accès WordPress réel ;
- information client confidentielle non nécessaire au développement.

## 1. Présentation

- Client : Pacific IP / SPT Wallis-et-Futuna
- Objectif du site : créer une plateforme e-commerce permettant de vendre en
  ligne des recharges prépayées SPT sous forme de vouchers uniques.
- Type de site : WordPress / WooCommerce
- Date de démarrage : 2026-08-25

Le site propose un parcours d'achat simple : choix d'une ou plusieurs recharges,
paiement par carte bancaire, puis délivrance des codes après validation effective
du paiement. L'email est intégré ; l'envoi par SMS reste à développer.

## 2. Environnements

- Local :
  - URL : https://spt.ddev.site
  - Nom DDEV : spt
  - Docroot : web
- Développement :
  - URL : à définir
  - Hébergeur : à définir
  - Chemin WordPress : à définir
- Production :
  - URL : à définir
  - Hébergeur : à définir
  - Chemin WordPress : à définir

## 3. Stack retenue

- WordPress : installé localement ; version à vérifier selon l'environnement
- PHP : 8.3
- MySQL/MariaDB : MySQL 8.0 via DDEV
- DDEV : oui
- Thème custom : tealforge
- Templating : Timber 2 / Twig
- Champs : ACF Pro
- Formulaires : WPForms
- E-commerce : WooCommerce
- Build front-end : Vite

## 4. Plugins WordPress

Plugins utilisés pour le site : ACF Pro, WPForms, WooCommerce, WPvivid,
WP-Optimize et AIOS. Vérifier leur activation et leurs versions sur chaque
environnement après restauration.

Plugins spécifiques :

- `mobupay-for-woocommerce` : paiement CB par redirection vers la page hébergée
  MobuPay, retour sur le site et confirmation par webhook signé. Version 1.2.0
  présente localement ; mode test utilisé, configuration production à valider.
- `spt-vouchers` : imports, stocks, réservation, attribution après paiement,
  emails et affichage/impression des codes dans Mon compte. Version 0.4.2
  présente localement ; version de production à vérifier.

Les plugins custom sont distribués séparément du dépôt du site. Restaurer la
BDD ne restaure pas leurs fichiers ni leurs versions. La source du plugin
`spt-vouchers` et ses archives sont dans `/Users/francoissarin/plugins-tealforge/`.

Réglages sensibles à documenter :

- Cache : WP-Optimize actif uniquement après validation visuelle.
- Sécurité : AIOS activé progressivement, REST API et admin-ajax à vérifier.
- SMTP : fournisseur et configuration d'envoi réel à confirmer, secrets hors dépôt.
- Sauvegardes : WPvivid avant toute restauration ou déploiement.

## 5. Conventions projet

- Slug du thème : tealforge
- Préfixe CSS : tf-
- Champs ACF principaux : page_sections
- Menus WordPress : `primary`, `footer_recharges`, `footer_help` ; `footer`
  reste enregistré comme ancien emplacement.
- CPT prévus : aucun dans le thème à ce stade
- Taxonomies prévues : aucune dans le thème à ce stade
- Templates spécifiques : intégration WooCommerce et sections ACF du thème.
- Plugin métier : `spt-vouchers`, afin de garder la logique vouchers hors du
  thème.

## 6. Catalogue WooCommerce

Le catalogue contient 6 produits simples :

- Papito Voix 1 000 F
- Papito Voix 3 000 F
- Papito Voix 5 000 F
- Neti Data 1 000 F
- Neti Data 3 000 F
- Neti Data 5 000 F

Devise du parcours : F CFP / XPF ; vérifier le réglage WooCommerce sur chaque
environnement.

Chaque produit doit être associé à un type de recharge utilisé par le système de
vouchers. Le stock affiché doit refléter les vouchers disponibles.

## 7. Vouchers et données métier

Les vouchers sont des codes uniques préexistants, fournis régulièrement par SPT
sous forme de fichiers CSV.

Fonctionnalités intégrées au plugin :

- import CSV depuis le back-office ;
- association de chaque voucher à une gamme, un montant et un produit
  WooCommerce ;
- contrôle des doublons à l'import ;
- statuts : disponible, réservé, vendu, expiré ;
- suivi du stock disponible par recharge ;
- attribution automatique du nombre exact de vouchers après paiement validé ;
- rattachement des vouchers attribués à la commande WooCommerce ;
- conservation de la date d'attribution ou de vente ;
- garantie qu'un voucher ne puisse être attribué qu'une seule fois.

Format CSV confirmé : sans en-tête, un voucher par ligne, avec séparateur `|` :
`numero_voucher|numero_serie|date_expiration` (date `AAAA-MM-JJ`). L'interface
propose un champ d'import pour chacun des six produits ; l'association au type
de recharge vient du champ choisi, pas d'une colonne CSV. Les réimports cumulent
le stock avec contrôle des doublons. Volume estimé : 2 à 4 imports par mois.

## 8. Parcours client

Le client doit pouvoir :

1. choisir une recharge parmi les six produits ;
2. ajouter une ou plusieurs recharges au panier ;
3. renseigner ses informations, notamment email et numéro de téléphone ;
4. payer sa commande via MobuPay (page de paiement externe) ;
5. recevoir les codes par email après validation effective du paiement ;
6. retrouver et imprimer ses codes dans Mon compte > Commandes > Voir, si la
   commande payée lui appartient et que les vouchers sont vendus.

L'achat de plusieurs produits ou de plusieurs quantités dans une même commande
doit attribuer un code distinct pour chaque recharge achetée.

Achat invité : règle à décider. Un invité ne peut pas consulter ses codes dans
Mon compte ; l'email doit donc rester un moyen de récupération fiable.

## 9. Notifications

Après attribution des vouchers, l'email transactionnel WooCommerce affiche les
codes avec le récapitulatif. Les tests locaux passent par Mailpit ; l'envoi réel
via SMTP reste à configurer et à valider.

L'envoi des mêmes codes par SMS n'est pas encore intégré. SMSLink est le
prestataire envisagé : confirmer la couverture Wallis-et-Futuna, le contrat et
l'API avant développement. Prévoir une trace du résultat de chaque envoi pour
le support. Faire valider les modèles d'email et de SMS.

## 10. Back-office

Le back-office doit permettre à Pacific IP de gérer les opérations courantes sans
intervention technique :

- consultation des commandes ;
- suivi des paiements ;
- suivi des recharges vendues ;
- consultation des codes importés et attribués ;
- produit ou type de recharge associé à chaque voucher ;
- statut de chaque voucher ;
- commande associée à chaque voucher vendu ;
- date d'attribution ou de vente ;
- suivi des stocks disponibles ;
- import de nouveaux fichiers CSV ;
- consultation de l'historique des imports ;
- consultation des informations clients liées aux commandes.

## 11. Contenus et données

- Source de vérité du contenu : à définir selon la phase projet.
- Migration BDD :
  - Sens recommandé : dev/prod -> local après création d'un environnement de
    référence.
  - Outil : WPvivid
  - Dernier import local : reprise depuis la production signalée par François ;
    date et périmètre non vérifiés dans le dépôt.
- Médias : données d'environnement, non versionnées.
- Données externes/API :
  - fichiers CSV de vouchers fournis par SPT ;
  - API SMSLink et desserte Wallis-et-Futuna à confirmer ;
  - MobuPay en mode test, passage en production à valider.
- Données de test nécessaires :
  - les 6 produits WooCommerce ;
  - un lot de vouchers de test par produit ;
  - une commande payée avec un produit ;
  - une commande payée avec plusieurs produits et quantités.
- Données à ne jamais écraser : commandes, clients, vouchers et stocks des
  environnements dev/prod.

## 12. Déploiement

- Hébergeur : à définir
- Accès SSH : à définir
- Chemin WordPress distant : à définir
- Variables locales de déploiement : `deploy.local.env`
- Déploiement du thème : procédure projet à valider selon l'hébergement ; les
  plugins custom doivent être mis à jour séparément.
- Points de vérification après déploiement : manifest.json, CSS/JS 200,
  WooCommerce, ACF JSON, caches, imports vouchers, attribution après paiement,
  emails, SMS.
- Procédure de backup avant déploiement : WPvivid complet.

## 13. Workflow projet

- Qui installe WordPress : François, sauf décision contraire.
- Qui installe les plugins tiers : François, sauf décision contraire.
- Qui importe la BDD : François, après backup.
- Qui déploie le thème : François, après validation explicite.
- Qui valide visuellement : client + Tealforge.
- Environnement source de vérité pour le contenu : à définir selon la phase
  projet.

## 14. Points de vigilance

- Aucun voucher ne doit être envoyé avant validation effective du paiement.
- Le retour du navigateur depuis MobuPay ne vaut pas confirmation du paiement ;
  vérifier le webhook signé et les statuts WooCommerce.
- Aucun voucher ne doit pouvoir être attribué deux fois.
- Une commande ne doit pas être considérée comme correctement traitée si le stock
  de vouchers est insuffisant.
- Un produit sans stock de vouchers doit être bloqué ou affiché comme
  temporairement indisponible.
- Les erreurs d'import CSV doivent être explicites et non destructives.
- Les doublons de vouchers doivent être refusés ou ignorés avec rapport clair.
- Les erreurs d'envoi SMS doivent être journalisées pour le support.
- Les hooks WooCommerce d'attribution doivent être idempotents.
- Les secrets paiement, SMS et SMTP doivent rester hors dépôt.
- La logique vouchers doit rester dans un plugin custom projet, pas dans le
  thème.
- WooCommerce doit fonctionner même si le thème ne porte que l'intégration
  visuelle.
- AIOS, cache et règles de sécurité doivent être activés progressivement pour ne
  pas bloquer REST API, admin-ajax, WooCommerce ou les assets du thème.

## 15. Historique des décisions

- 2026-08-25 : le projet SPT est cadré comme une plateforme WordPress /
  WooCommerce de vente en ligne de recharges prépayées pour SPT
  Wallis-et-Futuna.
- 2026-08-25 : les vouchers seront gérés par un plugin custom projet dédié, afin
  de sécuriser les imports, les stocks et l'attribution après paiement.
- 2026-08-25 : les points 13 et 14 de la note projet PDF ne sont pas repris comme
  source de contenu dans ce fichier.
- 2026-09-17 : MobuPay est le moyen de paiement testé ; sa page de paiement
  hébergée implique une redirection. Le plugin vouchers 0.4.2 est présent en
  local avec email et affichage/impression dans Mon compte. SMSLink et l'envoi
  SMTP réel restent à valider ; la version du plugin en production est inconnue.
