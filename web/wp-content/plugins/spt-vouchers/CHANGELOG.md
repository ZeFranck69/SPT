# Changelog

## 0.2.0 - 2026-09-04

- Remplacement du depot FTP par six imports manuels associes aux produits.
- Prise en charge du CSV sans en-tete separe par une barre verticale.
- Ajout du numero de serie et de la date d expiration.
- Controle des doublons sur le voucher et le numero de serie.
- Exclusion automatique des vouchers expires.
- Synchronisation obligatoire du stock des six produits WooCommerce.
- Blocage des achats tant que l attribution automatique n est pas activee.
- Protection contre la double reduction du stock WooCommerce.

## 0.1.0 - 2026-08-31

- Ajout des tables vouchers, imports et notifications.
- Ajout du chiffrement des codes et du controle global des doublons.
- Ajout de l'import CSV par dossier de depot et de son historique.
- Ajout du back-office de suivi des stocks, vouchers et imports.
- Ajout de la synchronisation de stock WooCommerce.
- Ajout de la reservation transactionnelle, desactivee par defaut.
- Ajout du passage au statut vendu apres paiement valide.
- Preparation de la future journalisation email et SMS, sans envoi actif.
