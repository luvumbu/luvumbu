=== Déploiement du serveur DualCam (https://luvumbu.com/DualCam/) ===

Backend INDÉPENDANT de DualCam : sa PROPRE base de données, son PROPRE dossier
uploads/, son PROPRE code. Rien n'est partagé avec PhotoSync.

------------------------------------------------------------------
1) ENVOYER ce dossier sur le serveur
------------------------------------------------------------------
Copie tout le contenu de ce dossier dans :  luvumbu.com/DualCam/
Résultat attendu :
   luvumbu.com/DualCam/index.php
   luvumbu.com/DualCam/install.php
   luvumbu.com/DualCam/api/...
   luvumbu.com/DualCam/lib/...
   luvumbu.com/DualCam/web/dualcam.php

------------------------------------------------------------------
2) INSTALLER la base DualCam (indépendante)
------------------------------------------------------------------
Ouvre https://luvumbu.com/DualCam/install.php
- Crée (ou choisis) une base de données DÉDIÉE à DualCam.
- Renseigne les identifiants de CETTE base (différente de PhotoSync).
- Le préfixe des tables est « dualcam_ » par défaut (dualcam_users, dualcam_photos).
L'assistant écrit lib/db.config.php et crée les tables + le dossier uploads/.

------------------------------------------------------------------
3) DOSSIER uploads/  (propre à DualCam)
------------------------------------------------------------------
Créé automatiquement par install.php dans /DualCam/uploads/ (doit être inscriptible).
Les vidéos DualCam y sont stockées, séparées de PhotoSync.

------------------------------------------------------------------
4) GOOGLE
------------------------------------------------------------------
Même identifiant client Web que PhotoSync (déjà dans lib/config.php) : la connexion
Google fonctionne, mais comme la base est indépendante, DualCam gère SES PROPRES
comptes (un compte DualCam est créé à la première connexion Google).
Le client OAuth Android « com.frontback.dualcam » reste valable.

------------------------------------------------------------------
5) TEST
------------------------------------------------------------------
- https://luvumbu.com/DualCam/  → connexion DualCam (ou installateur si pas encore installé).
- Connecte-toi avec Google.
- Depuis l'app DualCam, filme : l'envoi automatique remplit /DualCam/uploads/.
- Les vidéos apparaissent sur https://luvumbu.com/DualCam/web/dualcam.php

NB : l'app pointe déjà sur https://luvumbu.com/DualCam (SettingsStore.kt).
