# Boilerplate WordPress Tealforge

Socle WordPress reutilisable pour les projets Tealforge.

Stack incluse :

- WordPress ;
- theme custom `tealforge` ;
- Timber 2 et Twig ;
- ACF Pro ;
- WPForms ;
- Vite, CSS natif et JavaScript natif ;
- DDEV pour l'environnement local.

Le code du theme, les fichiers ACF JSON et les assets construits sont versionnes.
La base de donnees, les medias, les plugins tiers et les secrets ne le sont pas.

Les regles de developpement sont dans [AGENTS.md](AGENTS.md).
Les informations propres a un projet sont dans [PROJECT.md](PROJECT.md).

## Sommaire

- [Prerequis et installation des outils](#1-prerequis)
- [Installation d'un nouveau projet](#installation-dun-nouveau-projet)
- [Reprise d'un projet existant](#reprise-dun-projet-existant)
- [Developper et builder](#developper-et-builder)
- [Versionner les modifications](#versionner-les-modifications)
- [Deployer en dev/prod](#deployer-en-devprod)
- [Documentation](#documentation)

## 1. Prerequis

Installer ou obtenir :

- Git ;
- Docker Desktop ;
- DDEV ;
- un depot Git vide pour le projet ;
- les acces WordPress necessaires ;
- un acces SSH si un deploiement distant est prevu.

PHP, Composer, Node.js et WP-CLI sont utilises via DDEV.

### Installer Docker et DDEV

Installer Docker Desktop :

<https://docs.docker.com/desktop/setup/install/mac-install/>

Sur macOS :

```bash
brew install ddev/ddev/ddev
mkcert -install
```

Verifier :

```bash
docker --version
ddev version
```

> **Important : demarrer Docker Desktop avant toute commande DDEV.**
> Attendre que Docker indique qu'il est pret, puis seulement lancer `ddev start`.

## Installation d'un nouveau projet

### 1. Creer le projet

Creer d'abord un depot Git vide, sans README ni commit initial.

Avant de copier les commandes ci-dessous, remplacer :

- `ORGANISATION` par le nom de l'organisation ou du compte GitHub ;
- `NOM_PROJET` par le nom du projet, utilise aussi pour le dossier local et le depot distant.

> **Ne pas executer les commandes avec les valeurs `ORGANISATION` et `NOM_PROJET` telles quelles.**
> Adapter les deux valeurs dans les lignes `git clone` et `git remote add` avant de les coller.

Exemple : pour un projet `maison-du-bonheur`, le depot du boilerplate est clone dans
un dossier `maison-du-bonheur`, puis le remote est remplace par le depot du projet.

Depuis le dossier des projets :

```bash
cd ~/Sites
git clone git@github.com:ORGANISATION/boilerplate-wordpress-tealforge.git NOM_PROJET
cd NOM_PROJET
git remote remove origin
git remote add origin git@github.com:ORGANISATION/NOM_PROJET.git
cp PROJECT.md.example PROJECT.md
```

Apres le clonage, adapter ensuite :

- `PROJECT.md` ;
- `.ddev/config.yaml`, avec `name: NOM_PROJET` (le meme nom de projet, sans espace).

### 2. Installer WordPress en local

Depuis la racine du projet :

> **Avant `ddev start`, verifier que Docker Desktop est bien demarre.**

Remplacer `NOM_PROJET` dans l'URL et le titre ci-dessous par le nom choisi pour le
projet. Cette valeur doit correspondre au nom defini dans `.ddev/config.yaml`.

```bash
ddev start
ddev wp core download --path=/var/www/html/web --locale=fr_FR --skip-content
ddev wp config create \
  --path=/var/www/html/web \
  --dbname=db --dbuser=db --dbpass=db --dbhost=db
ddev wp core install \
  --path=/var/www/html/web \
  --url=https://NOM_PROJET.ddev.site \
  --title="NOM_PROJET" \
  --admin_user=tf-admin \
  --admin_password='CHANGE_ME_LOCAL_ONLY' \
  --admin_email=dev@tealforge.local \
  --skip-email
ddev wp rewrite structure '/%postname%/' --path=/var/www/html/web
ddev wp rewrite flush --path=/var/www/html/web
```

Le mot de passe ci-dessus est reserve au local et doit etre remplace.

### 3. Installer le theme et les plugins

Installer les dependances du theme :

```bash
ddev composer --working-dir=/var/www/html/web/wp-content/themes/tealforge install
ddev npm --prefix /var/www/html/web/wp-content/themes/tealforge install
bin/build
ddev wp theme activate tealforge --path=/var/www/html/web
```

Installer ensuite depuis l'administration WordPress les plugins retenus :

- ACF Pro ;
- WPForms ;
- WPvivid ;
- WP-Optimize ;
- plugin de maintenance ;
- All-In-One Security / AIOS.

Les plugins tiers ne sont pas versionnes dans le depot.

Si un environnement dev/prod existe deja :

1. installer et configurer WPvivid sur le local et l'environnement distant ;
2. faire un backup ;
3. importer la base distante vers le local ;
4. importer les medias si necessaire ;
5. verifier les utilisateurs, pages, menus, ACF et formulaires.

Sens recommande :

```text
dev/prod -> local
```

## Reprise d'un projet existant

### 1. Cloner le projet

Pour travailler sur un projet deja initialise, cloner le depot du projet. Les
commandes `git remote remove origin` et `git remote add origin` du parcours
« nouveau projet » ne sont pas necessaires.

> **Avant de copier la commande, remplacer `ORGANISATION` et `NOM_PROJET` par
> l'organisation/le compte et le nom exacts du depot existant.**

```bash
cd ~/Sites
git clone git@github.com:ORGANISATION/NOM_PROJET.git
cd NOM_PROJET
```

### 2. Installer WordPress localement

Le Core WordPress n'est pas versionne dans Git. L'installer dans le projet local :

> **Demarrer Docker Desktop et attendre qu'il soit pret avant `ddev start`.**

```bash
ddev start
ddev wp core download --path=/var/www/html/web --locale=fr_FR --skip-content
ddev wp config create \
  --path=/var/www/html/web \
  --dbname=db --dbuser=db --dbpass=db --dbhost=db
```

### 3. Installer les dependances et les plugins

Installer les dependances du theme :

```bash
ddev composer --working-dir=/var/www/html/web/wp-content/themes/tealforge install
ddev npm --prefix /var/www/html/web/wp-content/themes/tealforge install
bin/build
```

Les plugins ne sont pas recuperes par Git. Installer les plugins tiers depuis le
back-office et restaurer separement les plugins custom et leur version depuis
leurs archives validees. Pour SPT, verifier notamment `spt-vouchers` : restaurer
la base de donnees ne met pas ses fichiers a jour. Voir `PROJECT.md` pour les
versions attendues et les points restant a confirmer.

### 4. Importer la base et les medias

La base et les medias ne sont pas recuperes par Git. Les importer depuis
l'environnement de reference avec WPvivid, apres avoir fait un backup.

### 5. Verifier le projet

Verifier les utilisateurs, pages, menus, ACF, formulaires, medias et theme actif.

Chaque developpeur doit utiliser ses propres acces Git et sa propre cle SSH.
La procedure cPanel est decrite dans la documentation du projet.

## Developper et builder

Le theme se trouve dans :

```text
web/wp-content/themes/tealforge
```

Les pages sont composees avec des sections ACF Flexible Content. Pour creer une
section, consulter [docs/creer-section.md](docs/creer-section.md).

Commandes principales :

```bash
ddev launch
bin/status
bin/check
bin/build
bin/ci-check
```

`bin/build` compile les assets et met a jour `dist/`.
Le dossier `dist/` doit rester versionne pour le deploiement.

`bin/ci-check` verifie Git, PHP, les dependances npm, le build Vite et le
manifest. Il peut utiliser DDEV si les outils ne sont pas installes sur le poste.

### Recuperer les modifications d'un collegue

Depuis le depot du projet courant, verifier les modifications locales avant de
recuperer la branche distante :

```bash
git status
git pull --ff-only origin main
```

Ne pas remplacer le remote du projet par celui du boilerplate. Si le pull
contient des modifications CSS ou JavaScript, reconstruire les assets avec
`bin/build`. Traiter les modifications locales avant le pull ; ne pas forcer un
push en cas de conflit.

## Versionner les modifications

Workflow Git classique :

```bash
git status
git add .
git commit -m "Message clair"
git push
```

Workflow simplifie :

```bash
bin/commit "Message clair"
bin/push
```

Avant un commit, verifier que les sauvegardes, exports SQL, secrets, uploads,
`node_modules`, `vendor` et `deploy.local.env` ne sont pas inclus.
`bin/commit` utilise `git add -A` : il inclut donc tous les fichiers modifies
encore suivis par Git, meme ceux que `.gitignore` exclut pour les nouveaux clones.
Verifier `git status --short` avant de le lancer.

Pour faire evoluer un projet avec une nouvelle version du boilerplate, consulter
[docs/evolution-boilerplate.md](docs/evolution-boilerplate.md). Ne jamais fusionner
automatiquement tout le theme du boilerplate dans un projet deja personnalise.

## Deployer en dev/prod

Le deploiement est manuel et necessite un backup avant toute intervention.

Preparer le build et la configuration locale :

```bash
bin/build
cp deploy.example.env deploy.local.env
```

Renseigner dans `deploy.local.env` :

```text
DEPLOY_HOST="HOST"
DEPLOY_PORT="PORT"
DEPLOY_USER="USER"
DEPLOY_WP_PATH="/chemin/vers/wordpress"
DEPLOY_SSH_KEY="/chemin/vers/cle-privee"
```

Ce fichier ne doit jamais etre committe.

Afficher les commandes de deploiement :

```bash
bin/deploy-theme
```

Le script prepare l'archive et affiche les commandes `scp`, `ssh` et serveur.
Il ne se connecte pas automatiquement et ne modifie pas la production.

Suivre les commandes affichees dans le terminal :

1. envoyer l'archive ;
2. se connecter en SSH ;
3. sauvegarder puis remplacer le theme `tealforge` ;
4. appliquer les permissions indiquees ;
5. vider les caches ;
6. verifier le manifest et les fichiers CSS/JS.

Apres deploiement, verifier :

- le theme actif conserve le slug `tealforge` ;
- `dist/manifest.json` repond en HTTP 200 ;
- les CSS et JS sont charges depuis `dist/` ;
- les groupes ACF sont synchronises avant toute modification de page.

La procedure SSH/cPanel et les cas de depannage sont documentes dans [docs/](docs/).

## Documentation

- [Architecture du theme](docs/architecture-theme.md)
- [Creer une section](docs/creer-section.md)
- [Checklist nouveau projet](docs/checklists/nouveau-projet.md)
- [Checklist recette production](docs/checklists/recette-production.md)
- [Evolution du boilerplate](docs/evolution-boilerplate.md)
- [Depannage](docs/depannage.md)
- [Presentation technique](docs/presentation-boilerplate.md)
- [AGENTS.md](AGENTS.md)
- [CLAUDE.md](CLAUDE.md)
