# Guide complet — Application « Élan » (objectifs/)

Organiseur d'objectifs **sport & pro** façon Kanban, avec avatar de progression, statistiques,
gamification, et **application Android (APK)**. En ligne : `https://luvumbu.com/objectifs/`.

> Fichier de doc — placé à la racine du projet. **Ne pas y écrire de secret** (le dossier est servi sur le web).

---

## 1. Vue d'ensemble

Élan est une **web-app PHP** mono-fichier (UI en HTML/CSS/JS inline) branchée sur le **SSO Luvumbu ID**.
Elle est aussi **installable** (PWA) et disponible en **APK Android** (TWA).

**Fichiers :**
| Fichier | Rôle |
|---|---|
| `objectifs/index.php` | Toute l'app (UI + logique JS). Board rendu en JS. |
| `objectifs/api.php` | `action=load` / `action=save` — données par utilisateur dans `objectifs/data/u_<sha1(email)>.json`. |
| `objectifs/.htaccess` | Bloque l'accès direct aux `.json` (protège les données). |
| `objectifs/manifest.webmanifest` · `sw.js` · `icon-*.png` | PWA (installable / base de l'APK). |
| `objectifs/Elan.apk` | L'application Android (voir §8). |

---

## 2. Le tableau (Kanban)

- Colonnes **personnalisables** (renommer, ajouter, supprimer). Supprimer une colonne est **annulable**
  (bouton « ↩️ Annuler » 9 s, ou restauration dans ⚙️ Paramètres). Ça **n'efface pas** les statistiques.
- Cartes en **glisser-déposer** entre colonnes. Catégorie 🏃 Sport / 💼 Pro, priorité, échéance.
- **Sauvegarde automatique** (serveur, par utilisateur).

---

## 3. Exercices & quantités

Catalogue de **~140 exercices** regroupés par **partie du corps** (12 groupes) :
Abdos · Pectoraux · Dos · Épaules · **Biceps · Triceps · Avant-bras** · **Fessiers · Cuisses · Mollets** · Cardio · Mobilité.

Dans une carte → « Modifier » → sections repliables par groupe. Chaque exercice a un champ quantité :
- **Muscu** : 3 champs **séries × répétitions × poids** (ex. `4 · 10 · 60 kg`).
- **Cardio / course** : 2 champs **distance + temps** (ex. `5 km` · `25 min`), + une **« Course (distance libre) »**.
- **Mobilité** : une **durée**.

**Où éditer le catalogue :** en tête du `<script>` de `index.php`, constantes JS :
`GROUPES` (les 12 zones), `ACTIONS` (les exercices : `{nom, grp, niv 1-3, m:{muscle:points}}`),
`SEANCES` (séances prêtes), `MUSCLES`, `RANGS`. ⚠️ N'utiliser que les **13 muscles existants**
(sinon l'avatar casse) et ne pas renommer une clé existante (compat. des données).

---

## 4. Points, allure & progression

Valider une séance = la glisser vers une colonne **✅ Terminé**.

- **Points** = somme des points-muscles des exercices.
- **Course** : multipliée par l'**allure** (`paceMult`, min/km) ET la **distance** (`distMult`).
  - Allure : ≤4 min/km → ×1,5 · ≤5 → ×1,25 · ≤6 → ×1,12 · ~7 → ×1 · plus lent → ×0,9/×0,8.
  - Distance : neutre ~5 km, jusqu'à ×1,8 à 21 km+.
- **XP** total → **niveau** + **rang** (🥉 Débutant → 👑 Légende).

---

## 5. Avatar (canvas 2D)

Silhouette dessinée qui évolue **automatiquement** : **4 corpulences × 5 corps**.
- La **corpulence** vient de l'**IMC** (renseigne taille + poids dans la fenêtre 🏋️ Avatar).
- La **masse musculaire** vient de l'**entraînement** (volume cumulé).
- → IMC élevé **+ entraîné = Musclé** ; IMC élevé **sans entraînement = Gros**.
- Couleur de peau unie réglable (sélecteur de teint). Fonctions clés : `paintBody`, `avatarProgress`.

---

## 6. Statistiques & journal

**📊 Progrès** : graphe séances/semaine, courbe du poids corporel, **série** (jours d'affilée),
**défi du mois**, **records** (allure, distance), **badges**, historique des courses, **zones travaillées**.

**🗂️ Journal = source unique.** Toutes les stats sont **recalculées** à partir du journal des séances
terminées (`DATA.stats.completed`). **Journal vide → tout à zéro.** Fonctions : `sessionGain`, `recomputeStats`.

---

## 7. ⚙️ Paramètres (zone protégée)

- **Thème** : « Actuel » ou « Futuriste ✨ » (néon). Réglage `DATA.stats.theme`.
- **Effacement** (protégé) : retirer une séance (confirmation) ou **vider tout le journal** (double confirmation
  → remise à zéro). C'est le **seul** endroit où l'on peut effacer.

---

## 8. Connexion (SSO Luvumbu ID)

L'app exige une connexion via `sso/`. **Deux façons** :
- **Google** (fonctionne dans un navigateur et dans l'APK TWA).
- **Mot de passe** (sans Google) : champ « — ou avec un mot de passe — » sur l'écran de connexion.
  → La valeur est dans **`sso/secret.local.php`** (clé `password`), identique en local et en ligne.

---

## 9. Application Android (APK)

### 9.1 Type : TWA (Trusted Web Activity)
L'APK ouvre le site **dans Chrome** (pas un WebView). C'est **obligatoire pour que la connexion Google
marche** (Google bloque les WebViews). Nécessite **Chrome** installé sur le téléphone.

- APK en ligne : **`https://luvumbu.com/objectifs/Elan.apk`**
- `package_name` : `com.luvumbu.elan`
- Plein écran validé par **`https://luvumbu.com/.well-known/assetlinks.json`** (empreinte de la clé de signature).

### 9.2 Installer
1. Sur le tel : ouvrir `https://luvumbu.com/objectifs/Elan.apk` → télécharger → installer (autoriser la source).
2. ⚠️ Si une **ancienne version** (WebView) est déjà installée, la **désinstaller d'abord** (clé de signature différente).

### 9.3 Reconstruire l'APK (toolchain déjà installée dans `C:\androidbuild\`)
- **JDK 17** : `C:\androidbuild\jdk\jdk-17.0.19+10`
- **SDK Android** : `C:\androidbuild\sdk` (build-tools **34.0.0**, platforms;android-34). NB : un dossier vide
  `sdk\tools` est nécessaire pour que Bubblewrap valide le SDK.
- **Projet TWA** : `C:\androidbuild\twa\` (`twa-manifest.json`, keystore `android.keystore`).

Build (PowerShell / bash, depuis `C:\androidbuild\twa`) :
```
set JAVA_HOME=C:\androidbuild\jdk\jdk-17.0.19+10
set ANDROID_HOME=C:\androidbuild\sdk
./gradlew assembleRelease --no-daemon
```
→ produit `app\build\outputs\apk\release\app-release-unsigned.apk`. Puis signer :
```
zipalign -f 4 app-release-unsigned.apk aligned.apk
apksigner sign --ks android.keystore --ks-pass pass:<PASS> --key-pass pass:<PASS> --out Elan.apk aligned.apk
apksigner verify Elan.apk
```
(zipalign / apksigner sont dans `C:\androidbuild\sdk\build-tools\34.0.0`.)

Le mot de passe du keystore est dans mes notes (garde-le secret ; ne pas le mettre ici).
Après un rebuild avec un **nouveau** keystore, il faut **régénérer `assetlinks.json`** avec la nouvelle
empreinte SHA-256 (`keytool -list -v -keystore android.keystore -alias android`).

### 9.4 Publier sur le Play Store
Générer un **AAB** : `./gradlew bundleRelease` → `app-release.aab`, à signer et téléverser sur la Play Console.

---

## 10. Déploiement (mettre à jour le site)

Tout se déploie sur le serveur via l'API du **gestionnaire de fichiers** `_gestion/` :
- Fichiers **texte** (`index.php`, `manifest.webmanifest`, `sw.js`, `assetlinks.json`) → action `save` ou `upload`.
- Fichiers **binaires** (icônes `.png`, `Elan.apk`) → action `upload` (multipart) uniquement.

La **clé d'API** est dans **`_gestion/apikey.local.php`** (hors dépôt). ⚠️ Ne jamais l'écrire ici ni dans un fichier servi sur le web.

---

## 11. Secrets — où ils vivent (à protéger)

| Secret | Emplacement | Note |
|---|---|---|
| Mot de passe de connexion Élan | `sso/secret.local.php` → `password` | change quand tu veux (local **et** serveur) |
| Secret de signature JWT du SSO | `sso/secret.local.php` → `secret` | ne pas exposer |
| Clé API du gestionnaire de fichiers | `_gestion/apikey.local.php` | **accès écriture au site entier** — très sensible |
| Mot de passe du keystore APK | (notes privées) | sert à re-signer l'APK |

> Recommandation : ces secrets ont pu transiter dans des échanges — pense à les **régénérer** périodiquement.

---

## Récap express
1. Éditer le contenu/logique → `objectifs/index.php` (constantes en tête du script).
2. Déployer → API `_gestion` (`save` pour le texte, `upload` pour le binaire).
3. App mobile → APK TWA déjà en ligne ; rebuild via `C:\androidbuild\twa` (§9.3).
4. Connexion → Google **ou** mot de passe (dans `sso/secret.local.php`).
5. Tout part du **journal Terminé** ; effacement seulement dans ⚙️ Paramètres.
