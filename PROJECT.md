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

Le site doit proposer un parcours d'achat simple : choix d'une ou plusieurs
recharges, paiement par carte bancaire, puis réception automatique des codes par
email et par SMS après validation effective du paiement.

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

- WordPress : dernière version stable installée via WP-CLI
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

Plugins de socle :

- [ ] ACF Pro
- [ ] WPForms
- [ ] WPvivid
- [ ] WP-Optimize
- [ ] Plugin de maintenance : à définir
- [ ] All-In-One Security / AIOS

Plugins spécifiques projet :

- [ ] WooCommerce
- [ ] Plugin custom projet `spt-vouchers` à prévoir pour la gestion métier des
      vouchers, des imports CSV, des stocks, de l'attribution après paiement et
      des notifications.

Licences ou comptes à demander au client, sans stocker de secret ici :

- ACF Pro : à confirmer
- WPForms : à confirmer
- WooCommerce : module de paiement à confirmer
- Paiement CB : ePayNC ou MobuPay à confirmer
- SMS : prestataire externe à confirmer
- SMTP : fournisseur à confirmer

Réglages sensibles à documenter :

- Cache : WP-Optimize actif uniquement après validation visuelle.
- Sécurité : AIOS activé progressivement, REST API et admin-ajax à vérifier.
- SMTP : fournisseur à confirmer avec le client, secrets hors dépôt.
- Sauvegardes : WPvivid avant toute restauration ou déploiement.

## 5. Conventions projet

- Slug du thème : tealforge
- Préfixe CSS : tf-
- Champs ACF principaux : page_sections
- Menus WordPress : primary, footer
- CPT prévus : aucun dans le thème à ce stade
- Taxonomies prévues : aucune dans le thème à ce stade
- Templates spécifiques : intégrations WooCommerce à prévoir selon la charte
  graphique client.
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

Devise prévue : F CFP / XPF, à confirmer dans la configuration WooCommerce.

Chaque produit doit être associé à un type de recharge utilisé par le système de
vouchers. Le stock affiché doit refléter les vouchers disponibles.

## 7. Vouchers et données métier

Les vouchers sont des codes uniques préexistants, fournis régulièrement par SPT
sous forme de fichiers CSV.

Fonctionnalités attendues :

- import CSV depuis le back-office ;
- association de chaque voucher à une gamme, un montant et un produit
  WooCommerce ;
- contrôle des doublons à l'import ;
- statuts minimum : disponible, réservé ou en cours d'attribution, vendu ;
- suivi du stock disponible par recharge ;
- attribution automatique du nombre exact de vouchers après paiement validé ;
- rattachement des vouchers attribués à la commande WooCommerce ;
- conservation de la date d'attribution ou de vente ;
- garantie qu'un voucher ne puisse être attribué qu'une seule fois.

Volume d'import estimé : environ 2 à 4 imports par mois.

Format CSV à confirmer. Champs attendus d'après les notes projet :

- type de recharge ;
- gamme concernée ;
- montant ;
- code voucher ;
- référence de lot éventuelle.

## 8. Parcours client

Le client doit pouvoir :

1. choisir une recharge parmi les six produits ;
2. ajouter une ou plusieurs recharges au panier ;
3. renseigner ses informations, notamment email et numéro de téléphone ;
4. payer sa commande par carte bancaire ;
5. recevoir les codes uniquement après validation effective du paiement ;
6. recevoir les mêmes codes par email et par SMS.

L'achat de plusieurs produits ou de plusieurs quantités dans une même commande
doit attribuer un code distinct pour chaque recharge achetée.

Achat invité : à confirmer.

## 9. Notifications

Après attribution des vouchers :

- l'email transactionnel WooCommerce doit contenir le récapitulatif de commande
  et les codes achetés ;
- un SMS doit envoyer les mêmes codes au numéro renseigné au checkout ;
- le résultat de l'envoi SMS doit être conservé si possible pour faciliter le
  support.

Les modèles d'email et de SMS devront être validés avant mise en ligne.

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
  - Dernier import local : aucun
- Médias : à intégrer selon la charte graphique et les contenus fournis.
- Données externes/API :
  - fichiers CSV de vouchers fournis par SPT ;
  - API SMS du prestataire externe à confirmer ;
  - module de paiement ePayNC ou MobuPay à confirmer.
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
- Commande ou méthode de déploiement : à définir selon l'hébergement retenu
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
