================================================================================
   GESTIONNAIRE DE FICHIERS DISTANT — _gestion/
   Gérer l'ensemble des dossiers de luvumbu.com depuis le navigateur.
================================================================================

CE QUE C'EST
------------
Une page web protégée par mot de passe qui permet, depuis n'importe où :
  • parcourir TOUS les dossiers du site (racine = dossier parent de _gestion/) ;
  • envoyer des fichiers (bouton « Envoyer » ou glisser-déposer) ;
  • éditer les fichiers texte/code directement dans le navigateur ;
  • créer des dossiers et des fichiers ;
  • renommer, télécharger, supprimer.

Deux façons de l'utiliser :
  • INTERFACE :  https://luvumbu.com/_gestion/
  • API JSON  :  https://luvumbu.com/_gestion/api.php?action=list&path=...
                 (mêmes actions, pour scripter — voir plus bas)


MISE EN LIGNE
-------------
1. Envoyer le dossier _gestion/ complet à la racine du site
   (là où se trouvent index.php, anniversaire/, cv_luvumbu/, dropbox/…).
   Résultat : luvumbu.com/_gestion/index.php

2. Ouvrir https://luvumbu.com/_gestion/ et se connecter.


CONNEXION (3 méthodes acceptées, dans l'ordre)
----------------------------------------------
1. Mot de passe HACHÉ local — RECOMMANDÉ (voir « Sécurité » ci-dessous).
2. Mot de passe de secours du portfolio : config/portfolio.php → admin.password
   (actuellement « admin2026 » — À CHANGER).
3. Identifiants MySQL BOKONZI (utilisateur + mot de passe réels), comme admin.php.

Le champ « Utilisateur » n'est utile que pour la méthode 3 (MySQL).


>>> SÉCURITÉ — À FAIRE AVANT / DÈS LA MISE EN LIGNE <<<
------------------------------------------------------
Cet outil donne un accès complet en écriture à TOUT le site. Traitez-le
comme une clé maîtresse.

1. METTEZ UN VRAI MOT DE PASSE (haché). Depuis XAMPP en local, générez-le :
      php -r "echo password_hash('VOTRE_MOT_DE_PASSE_FORT', PASSWORD_DEFAULT);"
   Copiez la chaîne obtenue dans un nouveau fichier :
      _gestion/password.local.php
   contenant exactement :
      <?php return '$2y$....LA_CHAINE_GENEREE....';
   Ce fichier n'est PAS versionné (voir .gitignore) et est bloqué par .htaccess.

2. Changez aussi le mot de passe « admin2026 » dans config/portfolio.php.

3. HTTPS obligatoire (les cookies sont marqués Secure automatiquement en https).

4. Ne laissez pas cet outil en ligne s'il ne sert pas : supprimez le dossier
   _gestion/ après usage, ou protégez-le en plus par un .htpasswd Apache.


PROTECTIONS DÉJÀ INTÉGRÉES
--------------------------
  • Authentification par session obligatoire pour toute opération.
  • Confinement strict à la racine du site (realpath) → aucun accès au-dessus,
    aucune traversée de chemin (../../ neutralisé).
  • Jeton CSRF exigé sur toutes les actions qui écrivent.
  • Anti-bruteforce : verrou de l'IP 15 min après 6 échecs.
  • Cookies de session durcis (HttpOnly, SameSite=Lax, Secure en https).
  • Noms de fichiers envoyés assainis ; taille d'envoi limitée (64 Mo).
  • Le dossier _gestion/ lui-même est protégé (pas de suppression/renommage).
  • .htaccess bloque l'accès direct à lib.php, au secret et au fichier de verrou.


API JSON — RÉFÉRENCE RAPIDE
---------------------------
Base : _gestion/api.php?action=ACTION
Auth : nécessite la session (connectez-vous d'abord via l'interface) ;
       les écritures exigent le champ POST « csrf ».

  GET   action=list      &path=DOSSIER            → contenu d'un dossier
  GET   action=read      &path=FICHIER            → contenu texte d'un fichier
  GET   action=download  &path=FICHIER            → téléchargement binaire
  POST  action=save      path, content, csrf      → écrit un fichier texte
  POST  action=upload    path, files[], csrf      → envoi (multipart)
  POST  action=mkdir     path, name, csrf         → nouveau dossier
  POST  action=newfile   path, name, csrf         → nouveau fichier vide
  POST  action=rename    path, name, csrf         → renommer / déplacer
  POST  action=delete    path, csrf               → supprimer (récursif)

Réponses : JSON { "ok": true/false, ... }. path = chemin RELATIF à la racine
(« » = racine ; « cv_luvumbu/includes » = sous-dossier).


FICHIERS DU MODULE
------------------
  index.php            Interface web (connexion + explorateur).
  api.php              API JSON (toutes les actions).
  lib.php              Auth, sécurité des chemins, CSRF, anti-bruteforce.
  .htaccess            Blocage des fichiers internes + no-index.
  password.local.php   (À CRÉER) mot de passe haché — non versionné.
  .lockout.json        (auto) compteur anti-bruteforce.
================================================================================
