# Presentation du boilerplate WordPress Tealforge

## 1. Objectif

Le boilerplate WordPress Tealforge est un socle reutilisable pour demarrer des
projets WordPress professionnels, administrables et maintenables.

Il fournit une base technique commune pour les sites vitrines, les sites avec
espace client et, lorsque cela est valide au cas par cas, les projets WooCommerce.
Chaque nouveau projet part d'une structure connue, documentee et versionnee.

Le boilerplate ne contient pas les contenus d'un client. Il contient le code du
theme, l'architecture front-end, les conventions de developpement, les fichiers
ACF necessaires au socle et les scripts de workflow.

## 2. Benefices pour l'equipe

- demarrage plus rapide d'un projet WordPress ;
- architecture identique d'un projet a l'autre ;
- separation claire entre code, contenu et configuration ;
- developpement local reproductible avec DDEV et Docker ;
- theme moderne base sur Timber, Twig et Vite ;
- contenu administrable avec ACF Flexible Content ;
- meilleure reprise par un autre developpeur ;
- deploiement du theme possible sans Node.js sur le serveur ;
- documentation commune pour les developpeurs humains et les agents IA ;
- reduction des erreurs liees aux champs ACF, aux assets et aux permissions.

## 3. Architecture generale

```text
NOM_PROJET/
├── .ddev/                         # environnement local
├── bin/                           # scripts de workflow
├── docs/                          # documentation technique
├── AGENTS.md                      # regles communes pour les agents IA
├── CLAUDE.md                      # point d'entree Claude Code
├── PROJECT.md                     # contexte propre au projet
├── README.md                      # installation et commandes
├── deploy.example.env             # modele de configuration deploiement
└── web/
    ├── wp-admin/                  # WordPress Core, non modifie
    ├── wp-includes/               # WordPress Core, non modifie
    └── wp-content/
        └── themes/
            └── tealforge/        # theme custom versionne
```

Le depot Git contient le code. La base de donnees, les medias, les plugins tiers,
les sauvegardes et les secrets restent lies a chaque environnement.

## 4. Stack technique

### Socle WordPress

- WordPress comme CMS ;
- theme custom `tealforge` ;
- Timber 2 et Twig pour separer les donnees du rendu HTML ;
- ACF Pro pour les champs administrables et le Flexible Content ;
- WPForms pour les formulaires ;
- WooCommerce uniquement lorsqu'il est necessaire au projet.

### Developpement front-end

- Vite pour compiler les assets ;
- CSS natif moderne ;
- JavaScript natif et modulaire ;
- convention BEM avec prefixe `tf-` ;
- tokens visuels centralises dans `assets/styles/tokens.css` ;
- dossier `dist` versionne pour le deploiement.

### Environnement local

- Docker Desktop fournit les conteneurs ;
- DDEV orchestre l'environnement WordPress local ;
- WP-CLI, PHP, Composer et Node sont utilises via DDEV ;
- WPvivid sert aux sauvegardes et aux migrations de base et de medias.

## 5. Fonctionnement du theme

WordPress determine le fichier PHP d'entree selon sa hierarchie de templates.
Ce fichier prepare le contexte, puis Timber transmet les donnees a Twig.

```text
WordPress
    -> fichier PHP d'entree
    -> contexte PHP
    -> template Twig
    -> HTML final
    -> CSS et JavaScript compiles par Vite
```

Organisation principale du theme :

```text
web/wp-content/themes/tealforge/
├── inc/
│   ├── setup.php       # supports, menus et initialisation
│   ├── assets.php      # chargement du manifest Vite
│   ├── timber.php      # contexte et rendu Timber
│   └── helpers.php     # fonctions utilitaires du theme
├── views/
│   ├── layouts/        # structure HTML globale
│   ├── pages/          # vues de pages et templates
│   ├── sections/       # sections ACF Flexible Content
│   ├── components/     # composants reutilisables
│   └── partials/       # header, footer et fragments fixes
├── assets/
│   ├── styles/         # sources CSS
│   └── scripts/        # sources JavaScript
├── acf-json/           # definitions ACF versionnees
├── dist/               # assets compiles et manifest
├── functions.php       # point d'entree leger
└── screenshot.png      # apercu du theme dans WordPress
```

La logique metier complexe, les integrations Laravel, les synchronisations CRM
et les commandes WP-CLI specifiques sont developpees dans un plugin separe du
theme, puis distribue sous forme de ZIP.

## 6. Principe d'administration des pages

Chaque page importante existe comme page WordPress, y compris une one-page.
Son contenu est compose avec des sections ACF Flexible Content.

Exemple :

```text
Page WordPress : Accueil
└── page_sections
    ├── hero
    ├── texte_image
    ├── cartes
    └── appel_action
```

Cela permet a l'equipe de modifier l'ordre et le contenu des sections depuis
WordPress sans modifier le code. Les definitions de champs sont versionnees dans
`acf-json`, tandis que les valeurs saisies restent dans la base de donnees.

Avant de creer un champ ACF, un CPT ou une taxonomie, l'element doit etre decrit
avec son nom technique, son type, son emplacement et son usage dans `PROJECT.md`
ou dans la demande de developpement.

Un CPT est privilegie lorsqu'un contenu possede sa propre URL, doit etre filtre,
trie ou pagine, est utilise depuis plusieurs pages, possede des metadonnees SEO
propres ou peut depasser une dizaine d'elements.

Un repetiteur ACF est reserve a une petite liste propre a une seule page, sans URL
individuelle ni besoin de filtrage. Une taxonomie est preferee a un champ ACF de
classification lorsqu'elle sert a filtrer, trier ou regrouper des contenus.

## 7. Prerequis techniques

Pour faire fonctionner le site en local, un developpeur doit disposer de :

- Git ;
- Docker Desktop ;
- DDEV ;
- un acces au depot du projet ;
- un acces WordPress admin si necessaire ;
- Composer et Node.js n'ont pas besoin d'etre installes directement sur le poste,
  car ils sont utilises via DDEV.

Pour un environnement distant, il faut en plus :

- un acces SSH autorise par l'hebergeur ;
- une cle SSH personnelle ;
- un acces au fournisseur Git ;
- les informations d'environnement dans un fichier local non versionne ;
- un processus de backup valide avant toute intervention.

## 8. Outils de travail assistes par IA

Pour la methode Tealforge, l'environnement recommande est :

- Visual Studio Code comme editeur ;
- extension Claude Code pour les developpements avec Claude ;
- extension Codex pour les developpements avec Codex ;
- connexion MCP a Banani lorsque la maquette est produite ou consultee dans
  Banani.

Ces outils ne sont pas necessaires au fonctionnement technique du site. Ils sont
necessaires a la methode de production retenue pour exploiter le contexte du
projet, les maquettes et les regles du boilerplate.

### Role de Banani MCP

La connexion MCP permet a l'agent de consulter les donnees structurees de la
maquette :

- couleurs exactes ;
- typographies ;
- espacements ;
- dimensions ;
- composants et variantes ;
- contenus et hierarchie visuelle.

Elle evite de deduire des valeurs precises uniquement depuis des captures d'ecran.
Les valeurs confirmees doivent ensuite etre reportees dans
`assets/styles/tokens.css` et dans les sections concernees.

Si Banani MCP n'est pas disponible, une capture ou un export peut toujours servir,
mais l'integration risque de demander davantage de corrections manuelles.

### Role de Codex et Claude Code

Les agents peuvent aider a :

- analyser le projet et la maquette ;
- proposer le modele ACF/CPT ;
- developper le theme ;
- ecrire le CSS et le JavaScript ;
- preparer les scripts et la documentation ;
- verifier les diffs, la syntaxe et le build.

Ils ne doivent pas installer WordPress, restaurer une base, modifier la production,
installer un plugin ou deployer sans demande explicite. Chaque agent doit lire
`AGENTS.md` puis `PROJECT.md` avant de modifier le code.

## 9. Workflow de creation d'un projet

### Initialisation

1. creer le depot du projet ;
2. cloner le boilerplate dans `~/Sites/NOM_PROJET` ;
3. remplacer le remote par celui du projet ;
4. copier `PROJECT.md.example` vers `PROJECT.md` ;
5. renseigner le nom DDEV et les decisions du projet ;
6. lancer DDEV ;
7. installer WordPress dans `web/` ;
8. installer les dependances du theme ;
9. installer les plugins WordPress retenus ;
10. importer la base et les medias de reference si un environnement distant
    existe ;
11. synchroniser les groupes ACF ;
12. faire le premier commit et le premier push.

### Developpement

Le developpement avance fonctionnalite par fonctionnalite :

1. lire les regles et le contexte du projet ;
2. verifier les champs ACF, CPT et taxonomies existants ;
3. definir le modele de donnees avant le code ;
4. interroger Banani MCP pour les specifications de maquette ;
5. preparer les donnees en PHP ;
6. rendre le HTML avec Twig ;
7. ajouter les styles et interactions de la section ;
8. tester les contenus courts, longs et les champs vides ;
9. compiler les assets ;
10. verifier le diff avant commit.

### Reprise par un collegue

Un collegue clone le depot du projet, et non le boilerplate. Il ne reinstalle pas
WordPress. Il lance DDEV, installe les dependances non versionnees et recupere la
base de donnees et les medias via WPvivid.

```bash
git clone git@github.com:ORGANISATION/NOM_PROJET.git
cd NOM_PROJET
ddev start
```

Chaque developpeur utilise ses propres acces Git et sa propre cle SSH. Les secrets
ne sont jamais transmis dans Git ou dans `PROJECT.md`.

## 10. Plugins et responsabilites

Plugins de socle courants :

- ACF Pro ;
- WPForms ;
- WPvivid ;
- WP-Optimize ;
- plugin de maintenance ;
- AIOS.

Ils sont installes et configures par l'utilisateur ou l'equipe projet. AIOS doit
etre active progressivement : REST API, admin-ajax, Gutenberg, ACF, WPForms,
manifest Vite et les permissions de fichiers doivent etre testes apres chaque
changement important.

Le theme gere la presentation. Un plugin custom gere les fonctionnalites metier
qui depassent le rendu : bridge API, import de donnees, favoris, paiement,
authentification applicative ou integration CRM.

## 11. Git, sauvegardes et deploiement

Git versionne le code, les definitions ACF, les dependances declaratives et les
assets compiles. Il ne versionne pas :

- la base de donnees ;
- les medias et uploads ;
- les sauvegardes ;
- les secrets ;
- `node_modules` et `vendor` ;
- les fichiers temporaires WPvivid.

Avant un deploiement :

1. faire un backup de l'environnement cible ;
2. verifier le commit et le push ;
3. lancer `bin/build` ;
4. verifier `dist/manifest.json` ;
5. preparer l'archive du theme ;
6. transferer l'archive par SCP/SSH ;
7. remplacer le theme en conservant le slug `tealforge` ;
8. verifier les permissions ;
9. vider les caches ;
10. tester les assets, les pages, les formulaires et les champs ACF.

Le deploiement reste manuel et valide par un humain. Aucun push Git ne doit
deployer automatiquement en production.

## 12. CI et controles automatises

Le boilerplate fournit une CI minimale pour detecter rapidement les erreurs de
code et de build. Elle est disponible pour GitHub Actions et GitLab CI.

```text
.github/workflows/quality.yml
.gitlab-ci.yml
```

La CI execute :

1. `git diff --check` ;
2. la syntaxe PHP du theme ;
3. une installation propre des dependances npm ;
4. le build Vite ;
5. la verification de `dist/manifest.json`.

Le script commun est :

```bash
bin/ci-check
```

Cette CI ne necessite pas de base de donnees WordPress, de licence ACF Pro, de
plugin tiers, d'acces SSH ou de secret. En local, elle peut utiliser DDEV lorsque
PHP ou npm ne sont pas disponibles sur le poste. Elle ne deploye jamais en production.
Les tests de recette, les tests avec donnees client et les controles visuels
restent manuels ou necessitent un environnement de test dedie.

La recette est documentee dans :

```text
docs/checklists/recette-production.md
```

Elle constitue la definition de termine avant production et complete la CI avec
les controles WordPress, contenus, comptes, formulaires, securite, responsive,
emails, caches et assets.

## 13. Securisation d'un projet

La securite ne repose pas uniquement sur AIOS. Elle doit etre verifiee a trois
niveaux : le code, la configuration WordPress et l'infrastructure d'hebergement.
AIOS complete cette approche, mais ne remplace pas une revue du code custom.

### Checklist avant recette et production

Apres le developpement d'une fonctionnalite, verifier :

- les entrees utilisateur sont validees et nettoyees ;
- les sorties sont echappees selon leur contexte ;
- les actions d'ecriture utilisent un nonce ;
- les capacites de l'utilisateur sont verifiees ;
- les formulaires et endpoints refusent les donnees inattendues ;
- les erreurs affichees au public ne contiennent ni chemin serveur ni secret ;
- le mode debug et les logs detailles sont desactives en production ;
- les plugins, WordPress et PHP sont a jour ;
- les comptes inutiles sont supprimes et les roles sont limites ;
- les sauvegardes sont recentes et leur restauration a ete testee ;
- HTTPS est actif sur tous les environnements distants ;
- les fichiers sensibles et temporaires ne sont pas accessibles publiquement ;
- les permissions sont `755` pour les dossiers et `644` pour les fichiers ;
- AIOS, le cache et le SMTP sont testes sans bloquer REST, AJAX, ACF ou WPForms.

### Endpoints REST et AJAX

Tout endpoint custom doit etre implemente dans un plugin specifique au projet,
pas dans Twig. Avant de le creer, documenter :

```text
Namespace/version : nom-projet/v1
Route : /ressource
Methode : GET / POST / PUT / DELETE
Donnees exposees : publiques ou authentifiees
Role requis : visiteur / client / editeur / administrateur
Donnees acceptees : types et contraintes
Donnees retournees : structure et champs autorises
```

Pour une route WordPress :

- l'enregistrer sur `rest_api_init` ;
- utiliser un namespace versionne et propre au projet ;
- toujours fournir un `permission_callback` ;
- utiliser `__return_true` uniquement pour une route volontairement publique ;
- verifier les capacites pour toute lecture privee ou ecriture ;
- valider et nettoyer chaque parametre ;
- limiter les champs retournes et ne jamais exposer de donnees sensibles ;
- prevoir des erreurs controlees et des codes HTTP coherents ;
- tester les requetes authentifiees et non authentifiees ;
- ajouter une protection contre l'abus lorsque la route est couteuse ou sensible.

Pour une action AJAX, appliquer les memes controles et utiliser un nonce. Pour une
requete interne au navigateur WordPress, le nonce REST `wp_rest` peut etre transmis
dans l'en-tete `X-WP-Nonce`. Un nonce ne remplace pas une verification de capacite.

Pour une application externe, utiliser une authentification dediee et revocable,
par exemple des Application Passwords WordPress ou le mecanisme prevu par le
service distant. Ne jamais utiliser le mot de passe principal d'un utilisateur
dans une application ou un script.

### Emplacement des cles et secrets

Les cles API, mots de passe, tokens, identifiants SMTP et credentials d'API doivent
rester hors du depot et hors du JavaScript public.

Ordre de preference :

1. variables d'environnement fournies par l'hebergeur ;
2. constantes ajoutees dans `wp-config.php` sur chaque environnement ;
3. fichier local ignore par Git pour le developpement uniquement.

Exemple dans `wp-config.php` ou dans la configuration serveur :

```php
define('TEALFORGE_LARAVEL_API_URL', 'https://api.example.test');
define('TEALFORGE_LARAVEL_API_KEY', 'valeur-hors-depot');
```

Le plugin lit ces constantes sans fournir de valeur par defaut sensible. Une cle
necessaire au navigateur n'est pas un secret : elle doit alors etre restreinte par
le fournisseur (domaines, permissions, quotas) et ne jamais donner un acces
d'administration.

Ne jamais placer un secret dans :

- un fichier du theme versionne ;
- un plugin ZIP ;
- `package.json` ou un fichier JavaScript compile ;
- `PROJECT.md`, `README.md` ou une capture d'ecran ;
- les logs, messages d'erreur ou reponses REST.

### Role d'AIOS

AIOS peut aider a durcir WordPress et a surveiller certains comportements, mais
ses reglages doivent etre actives progressivement. Apres chaque modification,
tester au minimum : connexion, administration, REST, admin-ajax, Gutenberg, ACF,
WPForms, chargement des assets et sauvegarde des pages.

Les reglages AIOS propres au client sont documentes dans `PROJECT.md`, sans secret.
Les blocages observes doivent etre notes avec la regle responsable et la correction
appliquee afin d'eviter de desactiver la securite a l'aveugle.

### Verification des endpoints

Pour chaque endpoint, conserver une trace de tests locaux et distants :

```text
GET public sans authentification : attendu 200 ou 404 selon le besoin
GET prive sans authentification : attendu 401/403
POST sans nonce ou authentification : attendu 401/403
POST avec role insuffisant : attendu 403
Parametre invalide : attendu 400
Reponse valide : aucun secret ni champ inutile
```

Une revue de securite doit preceder la mise en production lorsqu'une fonctionnalite
concerne des comptes, paiements, favoris, donnees personnelles, importations ou
une API externe.

Ressources de reference :

- https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/
- https://developer.wordpress.org/reference/functions/register_rest_route/
- https://developer.wordpress.org/apis/wp-config-php/

## 14. Securite et limites

- ne jamais placer un mot de passe, token, cle privee ou cle API dans le depot ;
- chaque developpeur possede sa propre cle SSH ;
- toujours sauvegarder avant une restauration ou une modification distante ;
- ne pas modifier WordPress Core ni les plugins tiers ;
- activer les plugins de securite et de cache progressivement ;
- verifier les permissions `755` pour les dossiers et `644` pour les fichiers ;
- tester REST, AJAX, ACF et WPForms apres un durcissement de securite ;
- ne pas confondre le code versionne avec les donnees de la base ;
- ne pas considerer un environnement distant comme source principale du code.

## 15. Limites du boilerplate

Le boilerplate ne remplace pas l'analyse fonctionnelle d'un projet. Il ne decide
pas automatiquement :

- des CPT et taxonomies d'un client ;
- du modele d'abonnement ou de paiement ;
- de la structure d'une API externe ;
- des regles metier WooCommerce ;
- des contenus et medias ;
- des reglages de securite, cache et SMTP ;
- des acces fournis par l'hebergeur.

Ces decisions sont documentees dans `PROJECT.md` et validees avant implementation.

## 16. Conclusion

Le boilerplate Tealforge fournit un cadre commun, reproductible et evolutif pour
les projets WordPress de l'agence. Il reduit le temps de mise en place et les
erreurs recurrentes, tout en conservant la flexibilite necessaire aux projets
metier.

La base technique est commune, mais chaque projet reste autonome dans son depot.
Les contenus, integrations et plugins specifiques ne polluent pas le boilerplate
et peuvent evoluer independamment.
