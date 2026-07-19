# 12 — Questionnaires (quiz) : affichage, effets, résultat, connexion

Tout ce qui concerne la page de quiz : les **trois réglages administrateur** (affichage, effet de transition, moment de l'annonce du résultat), l'**obligation de se connecter pour voir son résultat**, le code impliqué, l'environnement serveur utilisé pour vérifier, et les limites connues.

Référence croisée : [03-database.md](03-database.md) (tables `quizzes`, `quiz_questions`, `quiz_options`), [08-admin.md](08-admin.md) (panel admin), [09-security.md](09-security.md) (auth, CSRF, open redirect).

---

## 1. Ce que fait la page de quiz

Un quiz est une suite de questions à choix unique. Le visiteur clique une option par question, et à la fin il obtient un score sur N avec un badge (`🏆 Parfait !`, `👍 Très bien`, `🙂 À consolider`, `📖 À revoir`).

Trois comportements sont **paramétrables par l'administrateur uniquement**, dans **Paramètres du site** (`pages/settings.php`). Ils s'appliquent à **tous les questionnaires du site** — il n'y a pas de réglage par quiz, ni de réglage par visiteur.

| Réglage | Clé `settings` | Valeurs possibles | Défaut |
|---|---|---|---|
| Affichage | `quiz_mode` | `one` = une question à la fois · `all` = toutes les questions d'un coup | `one` |
| Effet entre les questions | `quiz_effect` | `none` · `fade` · `slide` · `up` · `zoom` · `flip` | `slide` |
| Annonce du résultat | `quiz_reveal` | `live` = correction après chaque question · `end` = correction et score à la fin | `live` |

**Le visiteur ne peut rien changer.** Il n'y a aucun sélecteur dans la page publique, et aucune préférence visiteur n'est écrite (une version intermédiaire proposait un sélecteur + `localStorage`, elle a été retirée à la demande).

### Les 6 effets

| Valeur | Libellé admin | Animation |
|---|---|---|
| `none` | Aucun (désactivé) | Aucune animation, bascule instantanée |
| `fade` | Fondu | Opacité 0 → 1 |
| `slide` | Glissement latéral | La question sortante part à gauche, l'entrante arrive de la droite (45 px) |
| `up` | Glissement vers le haut | Sortie vers le haut (24 px), entrée depuis le bas (32 px) |
| `zoom` | Zoom | Sortie en `scale(1.06)`, entrée depuis `scale(.9)` |
| `flip` | Retournement 3D | `rotateY` sur le conteneur en `perspective:1000px` |

Durées : **entrée 340 ms** (450 ms pour `flip`), **sortie 180 ms** (220 ms pour `flip`). La constante JS `OUT_MS = 200` doit rester **≥ à la durée des animations de sortie** : c'est le délai après lequel le script masque la question sortante et affiche la suivante. Si tu allonges une animation `.qz-out-*`, allonge `OUT_MS` en conséquence, sinon la question sera masquée avant la fin de son animation.

L'effet est **automatiquement neutralisé** pour les visiteurs qui ont activé « réduire les animations » dans leur système (`@media (prefers-reduced-motion: reduce)`).

### Les 2 modes d'affichage

- **`one` (une question à la fois)** : seule la question courante est affichée. Une fois répondue, un bouton **« Suivante → »** apparaît en bas à droite et déclenche l'effet de transition. À la dernière question, le bouton disparaît et on passe au résultat.
- **`all` (toutes les questions)** : les questions sont toutes visibles, empilées (comportement historique). Le bouton « Suivante » n'existe pas ; après chaque réponse, la **question suivante non répondue est animée et défile automatiquement** dans le champ de vision (délai 450 ms en mode `live` — le temps de lire la correction — 150 ms en mode `end`).

### Les 2 moments d'annonce du résultat

- **`live` (en direct)** : dès le clic, l'option choisie devient verte (`.correct`) ou rouge (`.wrong`), la bonne réponse est révélée si l'on s'est trompé, et l'explication de la question s'affiche.
- **`end` (à la fin)** : le clic ne fait que **noter** la réponse — l'option est surlignée en ambre (`.chosen`), sans dire si elle est juste, sans explication, sans score. À la dernière question, **tout se dévoile d'un coup** : bonnes/mauvaises réponses de toutes les questions, toutes les explications, et — en mode `one` — **toutes les questions redeviennent visibles** pour servir de correction complète.

---

## 2. Connexion obligatoire pour voir son résultat

Règle : **n'importe qui peut jouer, seul un utilisateur connecté voit son score.**

### Parcours d'un visiteur non connecté

1. Il ouvre `pages/quiz.php?id=N`. Un bandeau ambre l'avertit dès le départ :
   > *Tu peux commencer tout de suite, sans compte. **La connexion ne sera demandée qu'à la fin**, pour afficher ton résultat.*
2. Il répond à toutes les questions normalement (avec correction en direct si `quiz_reveal = live`).
3. À la dernière question, **le bloc de score ne s'affiche pas**. À la place apparaît la **porte de connexion** (`#qz-gate`) :
   > 🔒 **Test terminé !** Connecte-toi pour découvrir ton score. Tes réponses sont conservées.
   > `[Se connecter]`  `Créer un compte`
4. Ses réponses sont enregistrées dans le **stockage local du navigateur**, sous la clé `qz_answers_<id du quiz>` (ex. `qz_answers_7`), au format `[0,0,1]` = index de l'option choisie pour chaque question. **Rien n'est écrit en base**, rien n'est envoyé au serveur.
5. Les deux liens le mènent à `pages/login.php?next=quiz&qid=N` ou `pages/register.php?next=quiz&qid=N`. Après connexion **ou** inscription, il est **renvoyé sur le même quiz**.
6. Au retour, la page **rejoue ses réponses** : options désactivées, correction complète affichée, barre de progression à 100 %, score et badge calculés, puis **le stockage local est vidé**.

Le score n'est **jamais** rendu à un visiteur anonyme, même en mode `live` : le compteur `#qz-num` vit à l'intérieur du bloc résultat, lui-même masqué tant que l'on n'est pas connecté.

### Redirection de retour : pas d'open redirect

`pages/login.php` utilise une **liste blanche** de destinations (constante `LOGIN_NEXT`) — jamais une URL arbitraire venant de l'utilisateur. J'y ai ajouté une entrée `quiz` :

```php
const LOGIN_NEXT = [
    'admin'    => 'pages/admin.php',
    'tokens'   => 'pages/api_tokens.php',
    'settings' => 'pages/settings.php',
    'quiz'     => 'pages/quiz.php',   // complété par ?id=<qid>
];

$qid = (int)($_GET['qid'] ?? $_POST['qid'] ?? 0);
if ($next === 'quiz') {
    if ($qid > 0) { $target .= '?id=' . $qid; }
    else          { $target = 'index.php'; }
}
```

Seul un **entier** est repris depuis l'URL (`(int)$_GET['qid']`), et il est réinjecté dans un **chemin relatif fixe** : impossible de transformer la page de login en tremplin vers un domaine externe. `pages/register.php` applique exactement la même logique (`$target`), et les formulaires des deux pages transportent `next` + `qid` en champs cachés pour survivre au POST.

---

## 3. Le code, fichier par fichier

Aucune migration de base n'est nécessaire : les trois réglages vivent dans la table `settings` existante (`key` / `value`), et les valeurs par défaut sont injectées en PHP quand la clé est absente.

### `blog/includes/settings.php`

Ajout de trois clés dans `settings_defaults()` et de six fonctions — un catalogue + un accesseur validé par réglage :

```php
function settings_defaults() {
    return [
        // ... site_name, tagline, header_baseline, about_text
        'quiz_effect' => 'slide',
        'quiz_mode'   => 'one',
        'quiz_reveal' => 'live',
    ];
}

function quiz_effects() { return ['none' => 'Aucun (désactivé)', 'fade' => 'Fondu', /* ... */]; }
function quiz_modes()   { return ['one' => 'Une question à la fois', 'all' => "Toutes les questions d'un coup"]; }
function quiz_reveals() { return ['live' => 'En direct (correction après chaque question)', 'end' => 'À la fin (correction et score une fois le test terminé)']; }

// Les *_default() relisent le setting et retombent sur la valeur sûre si la
// base contient une valeur inconnue (clé bidouillée à la main, ancienne version…).
function quiz_effect_default() { $v = get_setting('quiz_effect', 'slide'); return isset(quiz_effects()[$v]) ? $v : 'slide'; }
function quiz_mode_default()   { /* idem, défaut 'one'  */ }
function quiz_reveal_default() { /* idem, défaut 'live' */ }
```

La clé de chaque tableau sert **à la fois** de valeur stockée en base et de **nom d'animation CSS** (`slide` → `.qz-in-slide` / `.qz-out-slide`). Ajouter un effet = ajouter une entrée dans `quiz_effects()` + les deux `@keyframes` correspondantes dans `pages/quiz.php`. Rien d'autre.

`includes/settings.php` est chargé par `includes/bootstrap.php` (ligne 156), donc ces fonctions sont disponibles dans toutes les pages.

### `blog/pages/settings.php` (admin)

- Trois nouveaux champs dans le tableau `$fields`, avec un **nouveau type `select`** (le formulaire ne connaissait que `text` et `textarea`) :

```php
'quiz_mode'   => ['label' => 'Questionnaires : affichage',                'type' => 'select', 'max' => 20, 'choices' => quiz_modes()],
'quiz_effect' => ['label' => 'Questionnaires : effet entre les questions','type' => 'select', 'max' => 20, 'choices' => quiz_effects()],
'quiz_reveal' => ['label' => 'Questionnaires : annonce du résultat',      'type' => 'select', 'max' => 20, 'choices' => quiz_reveals(), 'hint' => '...'],
```

- **Validation** : une valeur qui n'est pas une clé de `choices` est rejetée (« choix invalide ») et l'ancienne valeur est conservée. On ne fait donc jamais confiance au `<select>` renvoyé par le navigateur.
- Support optionnel d'un `hint` (petite ligne d'explication grise sous le champ).
- La page est protégée par `require_admin()` (déjà en place) : **seul un admin peut lire ou écrire ces réglages**, et le POST est protégé par CSRF (`csrf_check`).

### `blog/pages/quiz.php`

Le cœur. Structure du fichier :

1. **PHP** — charge le quiz, ses questions et options (inchangé), applique la règle de visibilité (un quiz `active = 0` reste visible à son auteur et aux admins, 404 pour les autres), puis lit les trois réglages :

```php
$qzMode   = quiz_mode_default();    // one | all
$qzEffect = quiz_effect_default();  // none | fade | slide | up | zoom | flip
$qzReveal = quiz_reveal_default();  // live | end
$isLogged = is_logged_in();
```

2. **HTML** — les réglages sont passés au JS par des `data-*` sur le conteneur ; **c'est le serveur qui décide, le client ne fait qu'obéir** :

```html
<div id="qz-quiz"
     data-mode="one" data-effect="slide" data-reveal="live"
     data-logged="0" data-quiz="7">
```

Les questions sont **toutes rendues dans le HTML** (une `.qz-q` par question, avec `data-answer="<index de la bonne option>"`), le JS se contente de les montrer/masquer. Suivent le bouton `#qz-next`, le bloc résultat `#qz-final` (masqué par défaut) et la porte de connexion `#qz-gate` (masquée par défaut).

3. **CSS** — styles des questions/options + les 12 `@keyframes` des 6 effets + la règle `prefers-reduced-motion`. Classes d'état d'une option : `.chosen` (ambre, réponse notée en attente de correction), `.correct` (vert), `.wrong` (rouge).

4. **JS** (IIFE, vanilla, ~150 lignes) — les fonctions clés :

| Fonction | Rôle |
|---|---|
| `answer(qi, i)` | Enregistre la réponse, désactive les options, incrémente le score, applique `correction()` (mode `live`) ou `markChosen()` (mode `end`), avance la barre, puis enchaîne (bouton Suivante en mode `one`, animation + scroll vers la suivante en mode `all`) |
| `correction(q, i)` | Révèle bonne/mauvaise réponse + l'explication |
| `animateIn(q)` / `animateOut(q, done)` | Posent/retirent les classes `.qz-in-*` / `.qz-out-*`. `animateIn` force un reflow (`void q.offsetWidth`) pour pouvoir **rejouer** la même animation. Si l'effet est `none`, elles ne font rien (et `animateOut` appelle son callback immédiatement) |
| `goTo(i)` | Transition vers la question `i` : sortie → masquage → affichage → entrée → scroll. Un verrou `busy` empêche le double-clic pendant l'animation |
| `finish()` | Fin du test : en mode `end`, révèle toute la correction ; **si connecté** → score + badge ; **sinon** → sauvegarde `localStorage` + affichage de la porte de connexion |
| `store()` / `loadStore()` / `clearStore()` | Persistance des réponses entre la fin du test et le retour après connexion. `loadStore()` rejette un tableau de mauvaise taille ou incomplet (quiz modifié entre-temps) |

Toutes ces fonctions sont enveloppées dans des `try/catch` côté `localStorage` : un navigateur en navigation privée qui refuse le stockage ne casse pas le quiz (le visiteur devra juste rejouer après connexion).

### `blog/pages/login.php` et `blog/pages/register.php`

Retour vers le quiz après authentification (voir § 2). `register.php` n'avait aucune notion de `next` : il a désormais la même liste blanche minimale (`quiz` + `qid` entier), et les liens croisés « Déjà un compte ? » / « Pas encore inscrit ? » **transportent** le `next` + `qid` pour ne pas perdre le fil.

### Récapitulatif des fichiers touchés

| Fichier | Nature du changement |
|---|---|
| `blog/includes/settings.php` | 3 clés par défaut + 6 fonctions (catalogues et accesseurs validés) |
| `blog/pages/settings.php` | 3 champs admin, type de champ `select`, validation par liste blanche, `hint` |
| `blog/pages/quiz.php` | Réécriture : modes d'affichage, effets, correction différée, porte de connexion, reprise après login |
| `blog/pages/login.php` | Destination `quiz` en liste blanche + `qid` |
| `blog/pages/register.php` | Même destination de retour + champs cachés |
| `doc/12-questionnaires.md` | Ce document |
| `doc/README.md`, `doc/index.html` | Sommaire + navigation du visualiseur |

---

## 4. Le serveur : où il est, comment on l'appelle

### En local (poste de dev Windows)

| Élément | Valeur |
|---|---|
| Pile | **XAMPP** — Apache `2.4.58 (Win64)` + OpenSSL `3.1.3` + **PHP `8.2.12`** (bannière `Server:` renvoyée par Apache) |
| Racine web | `C:\xampp\htdocs\` |
| Projet | `C:\xampp\htdocs\luvumbu\articles\` (le blog : `...\luvumbu\articles\blog\`) |
| URL du blog | `http://localhost/luvumbu/articles/blog/` |
| URL d'un quiz | `http://localhost/luvumbu/articles/blog/pages/quiz.php?id=1` |
| Réglages admin | `http://localhost/luvumbu/articles/blog/pages/settings.php` |
| PHP en ligne de commande | `C:\xampp\php\php.exe` (⚠️ `php` tout court **n'est pas dans le PATH** de ce poste : il faut le chemin complet) |
| Base de données | MySQL/MariaDB de XAMPP, identifiants dans `blog/config/config.php` (constantes `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`), connexion PDO dans `blog/includes/db.php` |

**État actuel de ce poste : le blog n'est pas installé en local.** Le fichier `blog/config/config.php` est **absent** (il est d'ailleurs exclu du dépôt et du déploiement, c'est voulu : il contient les identifiants de base). Conséquence, `includes/bootstrap.php` redirige toute page vers l'installateur :

```bash
$ curl -s -D- -o /dev/null "http://localhost/luvumbu/articles/blog/pages/quiz.php?id=1" | grep -i location
Location: /luvumbu/articles/blog/install.php
```

Pour faire tourner le quiz en vrai sur ce poste : démarrer Apache **et MySQL** dans le panneau XAMPP, créer une base, puis ouvrir `http://localhost/luvumbu/articles/blog/install.php` (voir [02-installation.md](02-installation.md)). Tant que ce n'est pas fait, **aucune page du blog ne s'affiche en local** — ce n'est pas un bug du quiz.

### En production (Hostinger)

| Élément | Valeur |
|---|---|
| Hébergeur | Hostinger, serveur Apache mutualisé |
| Racine | `/public_html/` |
| Le blog | `/public_html/blog/` → **https://blog.mariondelval.com/** (sous-domaine configuré dans hPanel), également joignable via `https://mariondelval.com/blog/` |
| La page de quiz en prod | `https://blog.mariondelval.com/pages/quiz.php?id=N` |
| Les réglages en prod | `https://blog.mariondelval.com/pages/settings.php` (connexion admin requise) |
| Déploiement | **GitHub Actions → FTPS**. Un `push` sur `master` déclenche `.github/workflows/deploy.yml`, qui envoie `./blog/` vers `/public_html/blog/` (action `SamKirkland/FTP-Deploy-Action@v4.3.5`, secrets `FTP_HOST` / `FTP_USER` / `FTP_PASSWORD`). Détails ligne par ligne : [05-deployment.md](05-deployment.md) |
| Exclusions du déploiement | `config/config.php`, `uploads/**`, `APK/**`, `.github/**`, … — donc **la config et les fichiers uploadés du serveur ne sont jamais écrasés** |

Après déploiement, **rien à faire côté base** : les trois réglages n'ont pas de colonne dédiée, ils atterrissent dans la table `settings` (une ligne `key`/`value` chacun) à la **première sauvegarde** du formulaire admin. Avant cette première sauvegarde, `settings_defaults()` fournit `one` / `slide` / `live`.

---

## 5. Comment ça a été vérifié

Le site local n'étant pas installé (§ 4), la vraie page PHP n'a **pas** pu être exercée dans un navigateur. La vérification a donc porté sur les deux moitiés du code, séparément.

**1. Le PHP — analyse syntaxique des 5 fichiers modifiés :**

```bash
cd C:/xampp/htdocs/luvumbu/articles/blog
for f in pages/quiz.php pages/settings.php pages/login.php pages/register.php includes/settings.php; do
  /c/xampp/php/php.exe -l "$f"
done
# → No syntax errors detected (×5)
```

**2. Le JavaScript — le comportement réel, piloté dans un DOM :**

Un harnais Node + [jsdom](https://github.com/jsdom/jsdom) **extrait le `<script>` directement de `blog/pages/quiz.php`** (pas une copie, donc pas de dérive possible), le charge dans une page dont le HTML reproduit exactement celui rendu par PHP (3 questions, `data-mode` / `data-effect` / `data-reveal` / `data-logged`), puis clique sur les options comme le ferait un visiteur. Il vérifie 36 assertions sur 4 combinaisons :

| Scénario | Ce qui est vérifié |
|---|---|
| `mode=one`, `reveal=live`, connecté | Seule Q1 visible au départ ; correction immédiate ; bouton Suivante ; transition (effet `slide`) → seule Q2 visible ; bonne réponse révélée en cas d'erreur ; score final 2/3 ; barre à 100 % |
| `mode=one`, `reveal=end`, connecté | Réponse notée `.chosen` sans correction ni explication ; à la fin, correction complète révélée et **toutes** les questions réaffichées ; score correct |
| `mode=all`, `reveal=end`, **non connecté** | Toutes les questions visibles ; **rien n'est sauvegardé tant que le test n'est pas fini** ; à la fin : porte de connexion affichée, **score jamais montré**, réponses conservées (`qz_answers_7 = [0,0,1]`) |
| Retour après connexion | Réponses rejouées, score recalculé (2/3), badge `🙂 À consolider`, correction complète, barre à 100 %, porte cachée, **stockage nettoyé** |
| Réglages admin | Grep sur `quiz.php` : **aucun** `id="qz-effect"`/`id="qz-mode"`, **aucune** écriture de préférence visiteur, les réglages viennent bien des `data-*` serveur |

Le harnais vit dans le dossier temporaire de la session (`…\scratchpad\test.js`) — ce n'est pas un test du dépôt. Pour le rejouer : `npm i jsdom` puis `node test.js` (il relit `quiz.php` à chaque exécution).

**Ce qui n'a pas été vérifié en conditions réelles** : le rendu visuel des animations (jsdom n'exécute pas les CSS), l'enregistrement effectif des réglages en base, et le parcours login/register de bout en bout. Tout cela demande une installation locale ou un déploiement.

---

## 6. Limites connues et pistes

### ⚠️ La porte de connexion est une barrière d'interface, pas de sécurité

Les bonnes réponses sont **présentes dans le HTML de la page** (`<div class="qz-q" data-answer="0">`) — c'était déjà le cas avant ces changements. Quelqu'un qui ouvre l'inspecteur du navigateur peut donc :

- lire les bonnes réponses **sans répondre** ;
- afficher son score sans se connecter (le bloc `#qz-final` n'est masqué que par un attribut `hidden`).

C'est acceptable pour un quiz pédagogique et sans enjeu. **Ça ne l'est pas** si le quiz sert à évaluer ou noter quelqu'un. Pour un vrai verrou, il faut **corriger côté serveur** :

1. Ne plus émettre `data-answer` dans le HTML (le client ne connaît plus les réponses).
2. Poster les réponses à un endpoint (`api/quiz.php` existe déjà) qui compare en base et renvoie le score + la correction.
3. Refuser cet endpoint aux utilisateurs non authentifiés (le score devient alors **réellement** réservé aux connectés), et éventuellement enregistrer la tentative en base.

Cette étape n'a pas été faite : dis-le si tu la veux.

### Autres limites

- **Le score n'est stocké nulle part.** Pas d'historique des tentatives, pas de « tu as déjà fait ce quiz ». Les réponses en `localStorage` ne servent qu'au passage par la connexion et sont effacées juste après. Ajouter une table `quiz_attempts(user_id, quiz_id, score, created_at)` serait le prolongement naturel.
- **Les réglages sont globaux au site**, pas par quiz. Si un jour tu veux un quiz noté à la fin et un autre corrigé en direct, il faudra des colonnes `mode` / `effect` / `reveal` sur la table `quizzes`, avec repli sur les réglages du site.
- **Une seule bonne réponse par question** (le code prend le **premier** `quiz_options.is_correct = 1`). Les questions à réponses multiples ne sont pas gérées — la colonne `quiz_questions.type` existe mais n'est pas exploitée ici.
- **Pas de retour en arrière** : en mode `one`, on ne peut pas revenir sur une question précédente (elle est de toute façon déjà validée et verrouillée).
- **Navigation privée / stockage refusé** : la sauvegarde des réponses échoue silencieusement, le visiteur devra refaire le quiz après s'être connecté.

---

## 7. Déployer : les 5 fichiers vont ensemble

⚠️ **Piège vécu en production le 13/07/2026.** `pages/quiz.php` a été mis en ligne seul, sans `includes/settings.php` : **toutes les pages de quiz du site renvoyaient une erreur fatale** —

```
Fatal error: Call to undefined function quiz_effects() in .../blog/pages/quiz.php:31
```

`quiz.php` appelle `quiz_effect_default()`, `quiz_mode_default()` et `quiz_reveal_default()`, qui vivent dans `includes/settings.php`. Déployer une moitié casse l'autre. **Envoyer toujours ce lot complet :**

| Fichier | Pourquoi il doit partir avec les autres |
|---|---|
| `blog/includes/settings.php` | Définit les catalogues et les accesseurs (`quiz_effects()`, `quiz_modes()`, `quiz_reveals()`) |
| `blog/pages/quiz.php` | Les consomme — plante sans eux |
| `blog/pages/settings.php` | Le formulaire admin des 3 réglages (type de champ `select`) |
| `blog/pages/login.php` | Retour sur le quiz après connexion (`?next=quiz&qid=N`) |
| `blog/pages/register.php` | Même retour après inscription |

Contrôle après déploiement : `https://blog.mariondelval.com/pages/quiz.php?id=1` (ou `https://luvumbu.com/articles/blog/pages/quiz.php?id=1`) doit afficher le quiz, pas une erreur PHP.

## 8. Publier du contenu par l'API : où est la clé

Les cours et les QCM peuvent être créés à distance, sans passer par l'interface : `POST` JSON sur `api/article.php` (articles et sous-articles) et `api/quiz.php` (questionnaires, avec `article_id` pour les rattacher à un chapitre). Les deux exigent un champ `api_key`. Voir [04-api.md](04-api.md).

🔐 **La clé n'est écrite nulle part dans ce dépôt, et surtout pas ici.** Ce dossier `doc/` est **servi publiquement** (`https://luvumbu.com/articles/doc/` répond `200`) : une clé écrite dans un `.md` serait lisible par tout Internet, et permettrait à n'importe qui de publier, modifier ou supprimer des articles.

- **La clé se génère** sur *Paramètres du site → Gérer mes clés API* (`pages/api_tokens.php`).
- **Elle se range** dans un fichier **local**, hors du site et hors du dépôt (sur ce poste : `C:\Users\maste\.claude\projects\C--xampp-htdocs-luvumbu-articles\secrets\api_key.txt`).
- **Si elle fuite** : la révoquer immédiatement depuis la même page et en générer une nouvelle.

Même logique que `config/config.php` (identifiants de base) : exclu du dépôt **et** du déploiement FTP.

## 9. Dépannage rapide

| Symptôme | Cause probable | Solution |
|---|---|---|
| Toutes les pages du blog renvoient vers `install.php` | `blog/config/config.php` absent → blog non installé sur ce poste | Lancer Apache **et MySQL**, puis `install.php` ([02-installation.md](02-installation.md)) |
| Aucun effet visible entre les questions | Réglage sur « Aucun (désactivé) », ou système du visiteur en « réduire les animations » | Paramètres du site → *Questionnaires : effet* ; vérifier `prefers-reduced-motion` |
| La question disparaît avant la fin de l'animation de sortie | `OUT_MS` (200 ms) est plus court que l'animation `.qz-out-*` modifiée | Aligner `OUT_MS` sur la durée la plus longue |
| Le visiteur voit son score sans se connecter | Il a modifié le DOM à la main, ou l'attribut `data-logged` est mal rendu | Voir § 6 : la correction côté serveur est la seule vraie parade |
| Après connexion, le résultat ne réapparaît pas | Stockage local refusé (navigation privée), ou le quiz a changé de nombre de questions depuis (le tableau sauvegardé est alors rejeté) | Refaire le quiz une fois connecté |
| Le `<select>` d'un réglage renvoie « choix invalide » | Valeur POST hors liste blanche (formulaire bidouillé, ou clé retirée de `quiz_effects()`) | Vérifier que la valeur existe bien dans le catalogue correspondant |
| `php: command not found` (Git Bash) | PHP n'est pas dans le PATH de ce poste | Utiliser `/c/xampp/php/php.exe` |
