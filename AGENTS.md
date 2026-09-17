# AGENTS.md — Socle WordPress Tealforge

Ce fichier définit les règles générales à suivre par Codex sur les projets WordPress
Tealforge issus du boilerplate.

Il doit rester générique et réutilisable d'un projet à l'autre.

Les informations propres à un client ou à un projet doivent être placées dans
`PROJECT.md`.

---

## 0. Lecture du contexte

Avant toute analyse ou modification, lire `PROJECT.md` s'il existe à la racine du
dépôt.

Rôle des fichiers :

- `AGENTS.md` : règles générales Tealforge, applicables à tous les projets.
- `PROJECT.md` : présentation, contraintes et décisions propres au projet courant.
- `README.md` : documentation humaine d'installation, commandes et workflow.

En cas de contradiction :

1. respecter d'abord les règles de sécurité et de validation de `AGENTS.md` ;
2. appliquer ensuite les contraintes spécifiques de `PROJECT.md` ;
3. demander une validation si une contradiction reste ambiguë.

`PROJECT.md` doit rester court et contenir principalement :

- le nom et l'objectif du projet ;
- les environnements et URLs ;
- la stack effectivement retenue ;
- les plugins activés ou exclus ;
- les conventions propres au client ;
- les décisions d'architecture spécifiques ;
- les accès ou processus à demander au client, sans secrets ;
- les points de vigilance connus.

Ne jamais stocker de secret, mot de passe, clé API, clé SSH privée ou identifiant
sensible dans `PROJECT.md`, `README.md` ou le dépôt.

---

## 1. Exécution économique

- Inspecter uniquement les fichiers nécessaires à la tâche.
- Utiliser `rg` ou `rg --files` pour les recherches lorsque c'est possible.
- Ne pas utiliser de sous-agents sauf demande explicite.
- Ne pas répéter les audits, builds ou tests déjà réussis sans raison.
- Faire un seul build et une seule validation globale en fin de lot.
- Ne pas utiliser de navigateur automatisé sauf demande explicite.
- Ne pas installer d'outil de test sans validation.
- Garder les réponses et comptes rendus concis.
- Le contrôle visuel final est généralement réalisé manuellement par l'utilisateur.

---

## 2. Objet du socle

Ce dépôt contient un projet WordPress développé par Tealforge.

Architecture par défaut du boilerplate :

- environnement local : DDEV ;
- CMS : WordPress ;
- thème custom : `web/wp-content/themes/tealforge` ;
- templating : Timber 2 et Twig ;
- champs administrables : ACF Pro ;
- formulaires : WPForms ;
- build front-end : Vite, CSS natif moderne et JavaScript natif ;
- convention CSS : BEM avec préfixe `tf-` ;
- WooCommerce : optionnel selon le projet ;
- plugins custom projet : développés à part, zippés puis installés via le
  back-office WordPress.

Le développement se fait principalement en local.

Le dépôt Git est la source de vérité du code. La base de données WordPress et les
médias restent des données d'environnement, non versionnées.

---

## 3. Méthode de travail

Travailler uniquement sur la tâche explicitement demandée.

Le développement doit avancer section par section à partir d'une maquette, d'une
capture ou d'une demande validée.

Avant de créer ou modifier des champs ACF, CPT ou taxonomies :

1. lire `PROJECT.md` et vérifier le dossier courant ainsi que le nom DDEV ;
2. rechercher les groupes ACF JSON, CPT, taxonomies et champs existants ;
3. établir une fiche de modèle de données avec le nom technique, le type,
   l'emplacement, l'usage et le template prévu pour chaque élément ;
4. signaler les incohérences ou informations manquantes avant de coder.

Cette vérification s'applique à tout agent IA. Ne pas créer un champ ou un type
de contenu uniquement parce que la demande n'en précise pas le modèle.

Toujours créer une page WordPress pour porter le contenu, même lorsqu'il s'agit
d'une onepage. Le rendu doit être construit avec des sections administrables,
principalement via ACF Flexible Content, et non comme une page statique codée en
dur dans un template.

Pour chaque tâche :

1. analyser uniquement les fichiers utiles ;
2. expliquer brièvement l'approche prévue ;
3. modifier uniquement les fichiers nécessaires ;
4. ne pas anticiper les sections suivantes ;
5. vérifier le résultat techniquement ;
6. présenter les fichiers modifiés et les tests effectués ;
7. signaler les points restant à valider.

Ne pas effectuer de refactorisation générale sans demande explicite.

Ne pas remplacer du code fonctionnel uniquement pour appliquer une préférence
stylistique.

Ne pas créer de documentation supplémentaire sauf demande explicite.

---

## 4. Validation humaine obligatoire

Ne jamais effectuer sans autorisation explicite :

- un commit Git ;
- un push Git ;
- un déploiement ;
- une connexion à un serveur distant ;
- une modification de production ;
- une installation ou mise à jour de plugin ;
- une installation ou mise à jour de dépendance Composer ou npm ;
- une migration ou modification destructive de base de données ;
- une suppression massive de fichiers ;
- un changement d'architecture global.

Avant une opération potentiellement destructive, présenter la commande et attendre
la validation.

---

## 5. Répartition des responsabilités

Par défaut, Codex intervient sur le développement du site, du thème et des plugins
custom spécifiques au projet.

Codex ne doit pas exécuter les étapes d'initialisation ou d'exploitation du projet
sauf demande explicite de l'utilisateur.

Actions réalisées par l'utilisateur, sauf demande contraire :

- créer le dépôt GitHub projet ;
- cloner le boilerplate ;
- changer le remote Git ;
- modifier `PROJECT.md` ;
- modifier `.ddev/config.yaml` ;
- lancer `ddev start` ;
- installer WordPress ;
- créer `wp-config.php` ;
- lancer `wp core install` ;
- installer, activer ou configurer les plugins tiers ;
- importer ou restaurer une base de données ;
- gérer les médias ;
- gérer les accès hébergement, SSH, cPanel ou DNS ;
- exécuter les commandes de déploiement distant.

Actions prises en charge par Codex :

- développer le thème custom ;
- créer ou modifier les templates PHP/Twig ;
- créer ou modifier les sections ACF JSON ;
- intégrer les maquettes ;
- écrire CSS et JavaScript ;
- préparer ou documenter des plugins custom projet après validation ;
- préparer ou améliorer les scripts de workflow ;
- documenter les commandes à exécuter ;
- vérifier le code, les diffs et les builds liés au développement.

Codex peut fournir les commandes d'installation ou d'exploitation, mais ne les
exécute pas sans demande explicite.

---

## 6. Périmètre autorisé

Le thème custom par défaut se trouve dans :

```text
web/wp-content/themes/tealforge
```

Si un projet utilise un autre slug de thème, il doit être indiqué dans
`PROJECT.md`.

Ne jamais modifier :

```text
web/wp-admin
web/wp-includes
```

Ne jamais modifier directement :

- WordPress Core ;
- ACF Pro ;
- WPForms ;
- WooCommerce ;
- WP Mail SMTP ;
- WPvivid ;
- un autre plugin tiers ;
- un thème tiers.

Une adaptation d'un plugin doit utiliser en priorité :

- les hooks ;
- les filtres ;
- les API publiques ;
- les templates prévus pour être surchargés.

---

## 7. Plugins WordPress

Les plugins ne sont pas interdits, mais ils doivent être justifiés.

Plugins de socle attendus par défaut sur les projets Tealforge :

- ACF Pro ;
- WPForms ;
- WPvivid ;
- WP-Optimize ;
- plugin de maintenance ;
- All-In-One Security / AIOS.

Ces plugins sont installés et configurés par l'utilisateur, sauf demande explicite
contraire. Codex peut documenter les étapes, diagnostiquer un conflit ou intégrer
visuellement leur rendu, mais ne doit pas les installer par défaut.

Avant de proposer un nouveau plugin :

1. vérifier que le besoin n'est pas déjà couvert ;
2. expliquer son utilité ;
3. indiquer son impact potentiel sur les performances ;
4. vérifier sa maintenance et sa compatibilité ;
5. attendre une validation avant installation.

Utiliser WPForms pour les formulaires plutôt que développer un moteur de
formulaire custom.

Le thème peut gérer :

- l'intégration visuelle des formulaires ;
- leur emplacement ;
- leur responsive ;
- leurs styles ;
- les messages affichés ;
- les petits ajustements JavaScript nécessaires à l'intégration.

Le thème ne doit pas devenir le moteur métier des formulaires.

Configuration des emails recommandée :

- local : Mailpit via DDEV ;
- développement distant : WP Mail SMTP ;
- production : WP Mail SMTP.

Le fournisseur SMTP retenu doit être indiqué dans `PROJECT.md`.

Ne jamais ajouter de clé SMTP ou de secret dans le dépôt.

### Sécurité

Les plugins de sécurité, pare-feu ou durcissement WordPress peuvent bloquer le
workflow.

À surveiller particulièrement :

- REST API ;
- admin-ajax ;
- Gutenberg ;
- ACF ;
- WPForms ;
- fichiers JSON ;
- accès à `dist/manifest.json` ;
- permissions de fichiers ;
- règles `.htaccess` ;
- blocages IP, 2FA, CAPTCHA ou changement d'URL de connexion.

Activer ces options progressivement et tester après chaque changement.

---

## 8. Timber et Twig

Timber 2 est installé avec Composer dans le thème.

Autoloader attendu par défaut :

```text
web/wp-content/themes/tealforge/vendor/autoload.php
```

Règles :

- préparer les données en PHP ;
- utiliser Twig uniquement pour le rendu ;
- ne jamais lancer de `WP_Query` dans un template Twig ;
- ne pas placer de logique métier complexe dans Twig ;
- privilégier les composants et partials réutilisables ;
- éviter les appels répétés à une même donnée ;
- échapper les sorties selon leur contexte ;
- ne jamais utiliser `raw` sur une donnée non nettoyée ;
- limiter le HTML autorisé avec les fonctions WordPress adaptées.

Organisation cible :

```text
inc/
├── setup.php
├── assets.php
├── timber.php
└── helpers.php

views/
├── components/
├── layouts/
├── pages/
├── partials/
└── sections/
```

`sections/` contient les blocs ACF administrables ; `components/` les briques
réutilisables ; `partials/` les éléments structurels ; `pages/` les vues de
pages ; `layouts/` les structures globales.

---

## 9. ACF Pro

Chaque page de contenu doit exister comme page WordPress et être composée avec
le champ ACF Flexible Content principal, y compris sur un site one-page. Ne pas
coder le contenu directement dans `front-page.php`, `page.php` ou Twig sans
champs administrables, sauf validation explicite.

Champ principal recommandé :

```text
page_sections
```

Si un projet utilise un autre nom, l'indiquer dans `PROJECT.md`.

### Fiche préalable des champs et contenus

Avant de modifier le modèle de contenu, documenter au minimum :

```text
Nom technique | Objet | Type | Emplacement | Obligatoire | Usage | Template
```

Vérifier qu'un champ de même rôle n'existe pas déjà. Ne pas renommer ou
réutiliser un champ existant pour un autre usage sans migration validée.

### Création et intégration d'une page ACF

Pour chaque page de contenu demandée :

1. vérifier le dossier projet courant et le nom DDEV avant toute modification ;
2. vérifier les champs existants, puis créer la page si elle n'existe pas ;
3. la définir comme accueil statique si elle porte l'accueil ;
4. créer ou synchroniser le groupe ACF JSON nécessaire ;
5. insérer les layouts et contenus dans la page, pas seulement les champs ;
6. vérifier que `get_field('page_sections', ID_PAGE)` retourne les layouts attendus ;
7. vérifier l'URL publique et la sauvegarde en administration sans erreur REST.

Une page prévue avec sa propre URL ne doit pas être remplacée par une ancre ou
une section d'une autre page.

Règles ACF :

- utiliser des noms techniques stables en `snake_case` ;
- ne pas renommer un champ existant après saisie de contenu sans migration ;
- ne pas imbriquer plusieurs Flexible Content ;
- éviter les répéteurs volumineux ou imbriqués ;
- utiliser un CPT pour un contenu avec URL propre, filtrage, pagination,
  réutilisation sur plusieurs pages, métadonnées SEO ou volume évolutif ;
- réserver les répéteurs aux petits contenus propres à une seule page ;
- demander confirmation si le choix entre CPT et répéteur reste ambigu ;
- mutualiser les réglages communs lorsque cela est pertinent ;
- ne pas permettre la saisie libre de classes CSS ;
- préférer des choix contrôlés pour les variantes visuelles ;
- préférer une liste d'icônes contrôlée aux SVG uploadés lorsque le rendu doit être homogène ;
- enregistrer les définitions dans `acf-json` ;
- versionner les fichiers JSON ACF.

Dossier attendu :

```text
web/wp-content/themes/tealforge/acf-json
```

Les définitions ACF circulent avec les fichiers JSON.

Les contenus saisis restent dans la base de données WordPress.

Après un déploiement qui modifie `acf-json` :

1. vérifier la présence des fichiers JSON sur le serveur ;
2. vérifier leurs permissions (`644` pour les fichiers, `755` pour les dossiers) ;
3. supprimer les fichiers parasites `._*` ;
4. vérifier l'état de synchronisation avec `wp acf json status` ;
5. prévisualiser la synchronisation avec `wp acf json sync --dry-run` ;
6. synchroniser ou importer les groupes ACF avant d'éditer les pages ;
7. si des CPT ou taxonomies ACF ont été ajoutés, synchroniser aussi les types
   concernés puis relancer les règles de réécriture ;
8. ne jamais sauvegarder une page si des champs ACF attendus sont manquants.

Commandes serveur recommandées après déploiement de JSON ACF :

```bash
wp acf json status
wp acf json sync --dry-run
wp acf json sync
wp rewrite flush --hard
wp cache flush
```

Lorsque seul un type précis est concerné, limiter la synchronisation :

```bash
wp acf json sync --type=field-group
wp acf json sync --type=post-type
wp acf json sync --type=taxonomy
```

### Création d'un CPT

Avant d'enregistrer un CPT, vérifier qu'il n'existe pas déjà et documenter son
nom, slug, supports, taxonomies, exposition REST et groupe ACF. Prévoir ses vues
publiques si nécessaire, puis vérifier les permaliens, l'administration et REST.
Utiliser une taxonomie pour filtrer ou regrouper des contenus.

---

## 10. Structure d'une section

Chaque section doit avoir une responsabilité claire.

Garder son template et ses styles dans des fichiers dédiés. Ne créer un fichier
JavaScript dédié que si la section possède un comportement interactif.

Exemple :

```text
views/sections/text-media.twig
assets/styles/sections/text-media.css
assets/scripts/sections/text-media.js
```

Ne créer un fichier JavaScript que lorsque la section en a réellement besoin.

Chaque section doit prendre en compte :

- desktop ;
- tablette ;
- mobile ;
- contenu court ;
- contenu long ;
- absence d'un champ optionnel ;
- accessibilité ;
- performances ;
- comportement dans l'administration ACF.

Ne développer que la section demandée.
Ne pas déplacer tout le CSS ou le JavaScript existant lors d'une modification
ciblée, sauf demande explicite de refactorisation.

---

## 11. CSS et BEM

Utiliser BEM avec le préfixe `tf-`.

`assets/styles/main.css` est l'entrée globale Vite ; les styles propres aux
sections vivent dans `assets/styles/sections/` et réutilisent les variables de
`assets/styles/tokens.css`.

Exemple :

```html
<section class="tf-text-media tf-text-media--image-left">
    <div class="tf-text-media__container">
        <div class="tf-text-media__content"></div>
        <div class="tf-text-media__media"></div>
    </div>
</section>
```

Règles :

- tous les blocs custom commencent par `tf-` ;
- utiliser `block__element--modifier` ;
- ne pas utiliser d'identifiant HTML pour appliquer un style ;
- réserver les identifiants aux ancres et à l'accessibilité ;
- ne pas produire de sélecteurs excessivement spécifiques ;
- éviter `!important` ;
- éviter les styles inline ;
- ne pas utiliser le nom d'une page comme dépendance de style ;
- ne pas introduire Bootstrap, Tailwind ou un framework CSS sans validation ;
- respecter `prefers-reduced-motion` pour les animations ;
- garder les styles de formulaires WPForms dans les sections concernées.

---

## 12. JavaScript

Utiliser JavaScript natif et modulaire par défaut.

`assets/scripts/main.js` est l'entrée Vite ; `app.js` contient le comportement
commun et `assets/scripts/sections/` les comportements propres aux sections.

Règles :

- ne pas ajouter jQuery au thème ;
- accepter jQuery uniquement lorsqu'un plugin existant l'impose ;
- ne pas ajouter de framework JavaScript sans validation ;
- utiliser des attributs `data-tf-*` comme hooks JavaScript ;
- ne pas utiliser les classes CSS comme unique contrat JavaScript ;
- charger les scripts uniquement lorsqu'ils sont nécessaires ;
- gérer l'absence éventuelle d'un élément DOM ;
- éviter les écouteurs et initialisations en doublon ;
- respecter le clavier et les technologies d'assistance.

Exemple :

```html
<div
    class="tf-accordion"
    data-tf-component="accordion"
>
```

---

## 13. PHP et WordPress

Règles générales :

- utiliser `declare(strict_types=1);` dans les nouveaux fichiers PHP lorsque compatible ;
- empêcher l'accès direct aux fichiers avec `defined('ABSPATH') || exit;` ;
- utiliser les API WordPress ;
- nettoyer et valider toutes les entrées ;
- échapper toutes les sorties ;
- vérifier les capacités utilisateur ;
- utiliser des nonces pour les actions et formulaires custom ;
- ne jamais concaténer directement une donnée utilisateur dans une requête SQL ;
- ne jamais stocker de secret dans le code ;
- ne pas ajouter de fonction globale générique susceptible d'entrer en conflit ;
- utiliser le préfixe `tealforge_` pour les fonctions globales inévitables ;
- privilégier des classes et namespaces pour les fonctionnalités structurées.

`functions.php` doit rester un point d'entrée léger. Les responsabilités du thème
doivent être déplacées dans `inc/` dès qu'elles dépassent quelques lignes :

```text
inc/setup.php
inc/assets.php
inc/timber.php
inc/helpers.php
inc/acf.php
inc/post-types.php
inc/admin.php
inc/ajax.php
```

Créer uniquement les modules nécessaires au projet. Ne pas ajouter une structure
complexe par anticipation.

Les intégrations métier externes doivent être placées dans un plugin custom projet
lorsqu'elles dépassent le simple rendu du thème. Ces plugins sont développés dans
un espace dédié, zippés après validation, puis installés depuis le back-office
WordPress.

Exemples :

- bridge API Laravel ;
- synchronisation CRM ;
- authentification applicative ;
- import ou cache de données métier ;
- commandes WP-CLI projet.

---

## 14. Performances

Éviter les requêtes et chargements inutiles.

Règles :

- aucune requête dans Twig ;
- aucune requête répétée dans une boucle ;
- éviter les problèmes N+1 ;
- récupérer les données liées de manière groupée ;
- limiter les champs et objets récupérés au besoin réel ;
- paginer les listes volumineuses ;
- utiliser `no_found_rows` lorsqu'un comptage total est inutile ;
- mettre en cache les calculs ou appels externes coûteux ;
- ne pas charger globalement un script utilisé sur une seule page ;
- ne pas ajouter de bibliothèque lourde pour un comportement simple ;
- compiler et minifier les assets destinés à la production ;
- conserver des dépendances front-end limitées.

Pour les images :

- utiliser les tailles WordPress adaptées ;
- utiliser les fonctions WordPress d'image responsive ;
- fournir les dimensions lorsque possible ;
- conserver le lazy loading hors élément principal ;
- ne pas appliquer de lazy loading à l'image principale LCP ;
- éviter d'afficher directement les fichiers originaux surdimensionnés.

---

## 15. SEO

Le thème doit rester compatible avec un plugin SEO dédié.

Règles :

- utiliser un HTML sémantique ;
- conserver une hiérarchie de titres cohérente ;
- avoir un contenu principal identifiable ;
- prévoir une page `404.php` ;
- éviter les liens ou contenus critiques générés uniquement en JavaScript ;
- ne pas créer de deuxième système de métadonnées concurrent du plugin SEO ;
- ne pas générer de données structurées dupliquées ;
- utiliser des URLs internes WordPress ;
- prévoir les textes alternatifs des images ;
- préserver les permaliens et les contenus indexables.

---

## 16. Accessibilité

Chaque composant doit être utilisable :

- au clavier ;
- avec un focus visible ;
- avec des intitulés compréhensibles ;
- sans dépendre uniquement d'une couleur ;
- sans dépendre uniquement d'une animation ;
- avec une structure HTML sémantique.

Règles :

- utiliser un bouton pour une action ;
- utiliser un lien pour une navigation ;
- associer les labels aux champs ;
- ajouter ARIA uniquement lorsque le HTML natif ne suffit pas ;
- respecter `prefers-reduced-motion` pour les animations ;
- ne pas supprimer le focus sans solution de remplacement.

---

## 17. WooCommerce

WooCommerce est optionnel.

Le thème doit fonctionner lorsque WooCommerce est absent.

Règles :

- vérifier que WooCommerce est actif avant de charger une intégration ;
- utiliser les hooks et filtres en priorité ;
- limiter les surcharges de templates ;
- ne pas placer de logique de commande, paiement, stock ou taxe dans les templates ;
- charger les assets WooCommerce uniquement lorsqu'ils sont nécessaires ;
- ne pas modifier les fichiers du plugin WooCommerce.

---

## 18. Build front-end

Le build cible utilise :

```text
Vite
CSS natif moderne
JavaScript natif
```

Ne pas ajouter de dépendance sans validation.

Les fichiers suivants doivent rester versionnés :

```text
package.json
package-lock.json
composer.json
composer.lock
```

Ne pas versionner :

```text
node_modules
vendor
```

Le dossier `dist` du thème est destiné à être versionné afin de permettre un
déploiement sans build Node.js sur le serveur.

Après un build local, vérifier que les permissions de `dist` restent lisibles :

```bash
chmod -R u+rwX,go+rX web/wp-content/themes/tealforge/dist
```

---

## 19. Commandes locales

Exécuter les commandes WordPress, PHP, Composer et Node avec DDEV depuis la racine
du projet.

Exemples :

```bash
ddev start
ddev describe
ddev wp plugin list
ddev wp theme list
ddev wp cache flush
ddev php -v
ddev composer --version
ddev node --version
ddev npm --version
```

Pour Composer dans le thème :

```bash
ddev exec "composer --working-dir=/var/www/html/web/wp-content/themes/tealforge install"
```

Pour le build du thème :

```bash
ddev exec npm --prefix /var/www/html/web/wp-content/themes/tealforge run build
```

Ne pas supposer que PHP, Composer, WP-CLI ou Node.js sont installés directement
sur le poste hôte.

Le boilerplate peut proposer des scripts simplifiés dans `bin/` ou `package.json`,
mais les scripts doivent rester explicites et documentés dans `README.md`.

---

## 20. Contrôles avant validation

Avant de considérer une tâche terminée :

1. vérifier `git status --short` ;
2. vérifier le diff ;
3. vérifier la syntaxe des fichiers PHP modifiés ;
4. exécuter le build si les assets ont changé ;
5. vérifier que WordPress répond si la tâche le nécessite ;
6. contrôler les erreurs PHP ou JavaScript si disponibles ;
7. tester la section concernée en desktop et mobile lorsque pertinent ;
8. vérifier les cas où les champs optionnels sont vides.

Commande minimale :

```bash
git diff --check
```

Pour chaque fichier PHP modifié :

```bash
ddev exec php -l /var/www/html/chemin/du/fichier.php
```

Ne pas créer de commit sauf demande explicite.

---

## 21. Git

Le dépôt Git est la source de vérité du code.

Règles :

- ne pas committer sans demande explicite ;
- ne pas pousser sans demande explicite ;
- ne pas inclure de secrets ;
- ne pas inclure les sauvegardes, exports SQL, archives de déploiement ou uploads ;
- ne pas inclure `wp-content/wpvivid_staging` ;
- garder `dist` versionné ;
- garder `acf-json` versionné ;
- ignorer les fichiers macOS `._*` et `.DS_Store`.

Les commandes Git récurrentes doivent être simplifiées dans le boilerplate, par
exemple via scripts documentés :

```text
bin/status
bin/build
bin/check
bin/commit
bin/deploy-theme
```

Le détail de ces scripts appartient au `README.md`.

---

## 22. Déploiement

Le serveur distant est une cible de validation ou de production, pas la source
principale du code.

Ne jamais déployer sans autorisation explicite.

Ne jamais synchroniser automatiquement vers le serveur :

```text
wp-config.php
wp-content/uploads
les sauvegardes
les fichiers SQL
les secrets
```

Déploiement thème recommandé lorsque `rsync` n'est pas disponible :

1. construire le thème en local ;
2. créer une archive `tar.gz` en excluant `node_modules`, `.git`, `.DS_Store`, `._*` ;
3. transférer l'archive en SSH/SCP ;
4. extraire dans un dossier temporaire ;
5. appliquer les permissions `755` pour les dossiers et `644` pour les fichiers ;
6. remplacer le dossier actif en conservant le slug attendu ;
7. réactiver le thème ;
8. synchroniser les JSON ACF si des champs, CPT ou taxonomies ont changé ;
9. relancer les règles de réécriture si des CPT ou taxonomies ont changé ;
10. vider les caches ;
11. vérifier `dist/manifest.json`.

Ne pas laisser WordPress actif sur un dossier renommé du type :

```text
tealforge.backup-YYYYMMDD-HHMMSS
```

Le thème actif doit conserver son slug stable :

```text
tealforge
```

Après déploiement :

- vérifier que le HTML charge `/themes/tealforge/dist/...` ;
- vérifier que `dist/manifest.json` répond sans 403 ;
- vérifier que les fichiers compilés répondent en 200 ;
- exécuter `wp acf json status` et `wp acf json sync --dry-run` ;
- synchroniser les JSON ACF si des champs, CPT ou taxonomies sont en attente ;
- exécuter `wp rewrite flush --hard` si des CPT ou taxonomies ont changé ;
- vider le cache WordPress et les caches de plugin ;
- ne pas éditer une page tant que les champs ACF attendus ne sont pas visibles.

Toute correction effectuée exceptionnellement sur un serveur distant doit être
reportée dans le dépôt local.

---

## 23. Base de données, médias et sauvegardes

La base de données et les médias ne sont pas versionnés.

Utiliser un outil de sauvegarde/migration validé pour les transferts de contenu,
par exemple WPvivid si le projet le retient.

Règles :

- faire un backup avant toute restauration ;
- clarifier le sens de migration : local vers distant ou distant vers local ;
- ne jamais écraser une base distante sans validation explicite ;
- privilégier l'import de la base dev/prod vers local lorsque le local démarre
  avec une base neuve ;
- utiliser WPvivid sur les deux environnements lorsque c'est le processus retenu ;
- vérifier les utilisateurs, pages, menus, options, ACF et formulaires après une
  restauration locale ;
- ne pas committer les sauvegardes WPvivid ;
- ne pas committer `wp-content/wpvivid_staging`.

Cas connu WordPress/Gutenberg :

Si une page bloque avec l'erreur de méta `footnotes`, vérifier et supprimer
uniquement la méta concernée après validation :

```bash
wp post meta list ID_PAGE --keys=footnotes
wp post meta delete ID_PAGE footnotes
wp cache flush
```

Ne pas modifier la page tant que les champs ACF attendus ne sont pas revenus.

---

## 24. Compte rendu attendu

À la fin d'une tâche, répondre avec :

### Modifications

- fichiers créés ;
- fichiers modifiés ;
- résumé fonctionnel.

### Vérifications

- commandes exécutées ;
- tests réussis ;
- tests non exécutés.

### Points à valider

- différences éventuelles avec la maquette ;
- hypothèses réalisées ;
- prochaines décisions nécessaires.

Ne pas afficher de longs extraits de fichiers déjà visibles dans le diff, sauf
demande explicite.

Limiter le compte rendu final à ce qui aide réellement l'utilisateur à poursuivre.
