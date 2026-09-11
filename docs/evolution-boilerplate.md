# Faire evoluer un projet issu du boilerplate

## Principe

Le boilerplate sert de socle de depart. Apres la creation d'un projet, le depot
du projet devient sa source de verite et evolue de maniere autonome.

```text
boilerplate-wordpress-tealforge
            |
            | initialisation
            v
       depot projet
            |
            | adaptations client
            v
     projet autonome
```

Une nouvelle version du boilerplate ne doit donc jamais etre fusionnee
automatiquement dans tous les projets. Chaque projet doit choisir les evolutions
qui sont compatibles avec son architecture et ses donnees.

## Ajouter le boilerplate comme remote

Depuis la racine d'un projet existant :

```bash
git remote add boilerplate git@github.com:ZeFranck69/boilerplate-wordpress-tealforge.git
```

Le nom `boilerplate` est un alias local. Il distingue le socle du projet client :

```text
origin       depot du projet client
boilerplate  depot source Tealforge
```

Si le remote existe deja, ne pas le recreer. Verifier sa valeur :

```bash
git remote -v
```

Commandes utiles pour gerer les remotes :

```bash
git remote add boilerplate git@github.com:ZeFranck69/boilerplate-wordpress-tealforge.git
git remote get-url boilerplate
git remote remove boilerplate
```

`boilerplate` est uniquement un alias local. Il peut etre remplace par un autre
nom, mais le meme nom doit ensuite etre utilise dans les commandes `fetch`, `log`
et `diff`.

## Recuperer les evolutions

Avant toute comparaison :

1. faire un backup de la base et des fichiers importants si le projet contient
   des donnees non synchronisees ;
2. verifier que le projet est bien identifie ;
3. verifier l'etat du depot ;
4. ne pas commencer avec des modifications locales non comprises.

```bash
git status --short --branch
git fetch boilerplate
```

`git fetch` met a jour la reference locale `boilerplate/main`, mais ne modifie pas
les fichiers du projet et ne fusionne aucun commit.

## Comparer avant de choisir

Voir les commits du boilerplate :

```bash
git log --oneline --decorate boilerplate/main
```

Voir les commits presents dans le boilerplate mais pas dans le projet :

```bash
git log --oneline HEAD..boilerplate/main
```

Voir le detail d'un commit avant de l'integrer :

```bash
git show --stat --oneline IDENTIFIANT_DU_COMMIT
git show IDENTIFIANT_DU_COMMIT -- bin/ README.md
```

Comparer uniquement les fichiers techniques :

```bash
git diff HEAD..boilerplate/main -- AGENTS.md README.md bin/ docs/
git diff HEAD..boilerplate/main -- web/wp-content/themes/tealforge/inc
```

Comparer le theme complet demande davantage d'attention car le projet peut avoir
des sections, des CPT, des templates, des styles et des champs ACF propres au
client.

## Travailler dans une branche de test

Ne jamais tester une mise a jour directement sur `main` :

```bash
git switch -c test/mise-a-jour-boilerplate
```

Verifier que la branche est propre avant d'integrer un changement :

```bash
git status --short
```

Si des fichiers locaux apparaissent, ne pas les inclure automatiquement. Les
certificats DDEV, caches, sauvegardes, plugins installes et exports de base ne sont
pas des evolutions du boilerplate.

## Integrer une evolution

Lorsqu'un commit est compatible et independant du projet, le recuperer avec son
identifiant :

```bash
git cherry-pick IDENTIFIANT_DU_COMMIT
```

`git cherry-pick` applique dans la branche courante les changements contenus dans
un commit provenant d'une autre branche ou d'un autre remote. Il cree un nouveau
commit dans le projet courant : le commit d'origine n'est pas deplace et le reste
du boilerplate n'est pas fusionne.

Exemple :

```text
boilerplate/main : A -- B -- C
projet/main      : A -- D

cherry-pick B

projet/main      : A -- D -- B'
```

Le commit `B'` contient les changements de `B`, mais possede un nouvel identifiant
car il est applique dans l'historique du projet. Cette methode est adaptee aux
corrections isolees et aux evolutions autonomes. Elle ne convient pas a une
refonte globale du theme ou a une migration de donnees sans procedure dediee.

Apres chaque integration :

```bash
git status --short
git diff --check
bin/check
```

Pour interrompre un cherry-pick en cours apres un conflit :

```bash
git cherry-pick --abort
```

Pour consulter les changements prepares avant un commit :

```bash
git diff --cached
git status --short
```

Si l'integration cree un conflit :

1. ne pas supprimer les adaptations client pour resoudre rapidement le conflit ;
2. comparer les deux versions ;
3. conserver ou reporter manuellement les parties compatibles ;
4. tester le theme, les champs ACF et les templates concernes ;
5. annuler le cherry-pick si l'integration n'est pas maitrisable.

Ne pas utiliser `git merge boilerplate/main` ou `git rebase boilerplate/main` sans
analyse : ces commandes peuvent introduire des suppressions ou des changements
de structure incompatibles avec le projet.

## Types d'evolutions

### Evolution compatible

Exemples : documentation, correction isolee d'un script, regle `.gitignore` ou
amelioration sans impact sur les donnees.

Elle peut generalement etre integree apres comparaison et tests.

### Evolution necessitant une migration

Exemples : changement de champ ACF, nouveau CPT, nouvelle taxonomie, changement de
structure Twig ou modification d'une dependance.

Elle doit etre accompagnee d'une procedure de migration, d'un backup et d'une
validation sur une copie de la base avant toute production.

### Evolution majeure

Exemples : changement de version majeure de Timber, de PHP, de Vite, de WordPress
ou changement d'architecture du theme.

Elle doit faire l'objet d'une analyse dediee et ne doit pas etre appliquee par un
simple cherry-pick.

## Versionner le boilerplate

Le boilerplate doit utiliser des commits explicites et des tags pour identifier
les versions distribuees :

```bash
git tag -a v1.1.0 -m "Version 1.1.0"
git push origin v1.1.0
```

Les projets peuvent consulter les tags et choisir une version de reference. Un
changelog doit decrire les changements incompatibles, les migrations necessaires
et les fichiers concernes.

## Validation avant propagation

Avant de proposer une evolution a plusieurs projets :

- verifier le diff et les fichiers inclus ;
- executer le build du theme ;
- verifier la syntaxe PHP ;
- verifier les JSON ACF ;
- tester une installation neuve du boilerplate si la structure a change ;
- tester au moins un projet existant representatif ;
- documenter les migrations eventuelles ;
- faire relire le changement par un developpeur ;
- ne jamais deployer automatiquement en production.

## Retour a la branche principale

Apres validation de la branche de test :

```bash
git switch main
git merge --ff-only test/mise-a-jour-boilerplate
git push origin main
```

Si le test n'est pas concluant, supprimer uniquement la branche de test apres
avoir verifie qu'aucun travail utile n'y reste :

```bash
git branch -D test/mise-a-jour-boilerplate
```

La mise a jour d'un projet client doit ensuite etre commitee et poussee dans son
propre depot, avec un message indiquant la version ou le commit du boilerplate
integre.
