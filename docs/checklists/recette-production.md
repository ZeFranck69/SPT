# Checklist de recette avant production

Cette checklist complete la CI. La CI verifie le code et le build ; cette recette
verifie le fonctionnement du site avec WordPress, les plugins, la base de donnees
et les contenus du projet.

La recette doit etre realisee sur un environnement de developpement ou de staging
representatif avant toute mise en production.

## 1. Preparation

- [ ] Le projet et l'environnement cible sont identifies dans `PROJECT.md`.
- [ ] Le commit deploye est connu et present dans le depot distant.
- [ ] La CI est verte.
- [ ] Le diff a ete relu par un developpeur.
- [ ] Les changements de base de donnees ou de structure sont documentes.
- [ ] Un responsable valide la recette et le deploiement.

## 2. WordPress et plugins

- [ ] WordPress est a jour selon la version validee du projet.
- [ ] Le theme `tealforge` est actif.
- [ ] Les plugins attendus sont actifs.
- [ ] Les versions des plugins sont compatibles avec le projet.
- [ ] AIOS ne bloque pas la connexion, REST, AJAX, Gutenberg, ACF ou WPForms.
- [ ] Le cache est purge apres les changements.
- [ ] Le SMTP est configure sur l'environnement concerne.

## 3. Donnees et administration

- [ ] Les pages principales existent.
- [ ] La page d'accueil est correctement definie.
- [ ] Les menus et liens internes fonctionnent.
- [ ] Les groupes ACF sont synchronises.
- [ ] Les champs ACF attendus sont visibles dans l'administration.
- [ ] Les valeurs ACF sont conservees apres sauvegarde d'une page.
- [ ] Les CPT et taxonomies attendus existent.
- [ ] Les slugs, archives et pages individuelles des CPT fonctionnent.
- [ ] Les utilisateurs et roles necessaires sont presents.
- [ ] Aucun import ou deploiement n'a ecrase une donnee a conserver.

## 4. Fonctionnel public

- [ ] L'accueil s'affiche sans erreur PHP ou JavaScript.
- [ ] Les pages principales sont accessibles directement par leur URL.
- [ ] Les liens du header, footer et menus fonctionnent.
- [ ] Les liens externes ouvrent la bonne destination.
- [ ] Les formulaires affichent leurs messages de validation.
- [ ] Les formulaires refusent les donnees invalides.
- [ ] Les formulaires envoient un email de test.
- [ ] La confirmation ou redirection apres envoi fonctionne.
- [ ] La page 404 repond correctement pour une URL inexistante.
- [ ] Les contenus optionnels vides ne cassent pas la mise en page.

## 5. Comptes et donnees privees

- [ ] La connexion fonctionne avec un compte de test.
- [ ] La deconnexion fonctionne.
- [ ] Les pages privees refusent un visiteur non authentifie.
- [ ] Un utilisateur ne peut pas consulter les donnees d'un autre utilisateur.
- [ ] Les roles et capacites correspondent au besoin metier.
- [ ] Les endpoints prives refusent les appels non authentifies.
- [ ] Les endpoints publics ne retournent aucune donnee sensible.
- [ ] Les actions d'ecriture refusent une requete sans nonce ou autorisation.
- [ ] Les mots de passe et tokens ne figurent ni dans les URLs, ni dans les logs.

## 6. Responsive et accessibilite

- [ ] Le site est verifie sur desktop.
- [ ] Le site est verifie sur tablette.
- [ ] Le site est verifie sur mobile.
- [ ] Le menu mobile s'ouvre, se ferme et reste utilisable au clavier.
- [ ] Les boutons et liens ont un etat de focus visible.
- [ ] Les champs ont des labels associes.
- [ ] Les images importantes ont un texte alternatif pertinent.
- [ ] Les titres suivent une hierarchie coherente.
- [ ] Les animations respectent `prefers-reduced-motion`.
- [ ] Aucun texte, bouton ou composant ne deborde ou ne se chevauche.

## 7. SEO et contenu

- [ ] Les titres et descriptions SEO sont presents si le projet utilise un plugin SEO.
- [ ] Les URLs canoniques et permaliens sont corrects.
- [ ] Les liens internes ne pointent pas vers l'ancien domaine.
- [ ] Les textes alternatifs sont renseignes.
- [ ] Les pages legales sont accessibles.
- [ ] Le consentement cookies est configure si necessaire.
- [ ] Les formulaires indiquent le traitement des donnees si necessaire.
- [ ] Les contenus de test et placeholders ont ete retires.

## 8. Performance et assets

- [ ] `dist/manifest.json` est accessible.
- [ ] Les CSS et JavaScript compiles repondent en HTTP 200.
- [ ] Les polices et images chargees existent.
- [ ] Les anciennes versions d'assets ne sont plus appelees.
- [ ] Les images sont dimensionnees pour leur usage.
- [ ] Le cache est purge puis reteste.
- [ ] La page principale ne contient pas d'erreur console bloquante.
- [ ] Les appels API externes repondent ou affichent un fallback coherent.

## 9. Sauvegarde et deploiement

- [ ] Un backup complet de la cible a ete realise.
- [ ] Le fichier `deploy.local.env` n'est pas versionne.
- [ ] L'acces SSH et le chemin WordPress ont ete verifies.
- [ ] `bin/build` a ete execute.
- [ ] `bin/package-theme` a ete execute.
- [ ] `bin/deploy-theme` a ete relu avant execution des commandes distantes.
- [ ] Le slug du theme reste `tealforge`.
- [ ] Les permissions sont `755` pour les dossiers et `644` pour les fichiers.
- [ ] Les JSON ACF sont presents et synchronises si necessaire.
- [ ] Les permaliens sont actualises si un CPT ou une taxonomie a change.

## 10. Verification apres deploiement

- [ ] La page d'accueil repond en HTTPS.
- [ ] Les pages principales repondent sans erreur serveur.
- [ ] `dist/manifest.json` ne retourne pas une erreur 403.
- [ ] Les CSS, JavaScript, images et polices repondent correctement.
- [ ] Les formulaires ont ete testes sur la cible.
- [ ] Les emails de test sont recus.
- [ ] La connexion et les espaces prives ont ete testes.
- [ ] Les logs ne contiennent pas d'erreur nouvelle bloquante.
- [ ] Les caches CDN, serveur, WordPress et plugin sont purges si necessaire.
- [ ] Le responsable valide la mise en ligne.

## 11. Retour arriere

En cas de probleme bloquant :

1. arreter les modifications de contenu ;
2. identifier si le probleme vient du code, de la base, du cache ou d'un plugin ;
3. restaurer le theme precedent ou le backup valide ;
4. ne pas restaurer la base sans validation ;
5. documenter la cause et la correction avant une nouvelle tentative.

Une recette validee ne remplace pas la surveillance post-production. Les erreurs
et retours utilisateurs doivent etre suivis apres la mise en ligne.
