# Guide IA — Blog `luvumbu/articles` : API, clés, articles & quiz

Point d'entrée pour un agent IA (ou un développeur) qui doit piloter le blog
**à distance**, sans passer par l'interface web : comprendre l'authentification par
clé, publier / modifier / supprimer des articles, **créer des QCM liés aux articles**,
et retrouver le compte admin.

Écrit d'après le code réel de `blog/` (`api/`, `pages/`, `includes/`). En cas de doute,
**le code fait foi**.

---

## 0. Deux applications distinctes (ne pas confondre)

| App | URL | Rôle | Quiz ? |
|---|---|---|---|
| **Blog** | `https://luvumbu.com/articles/blog` | articles + **quiz** (ajouté) | ✅ oui |
| **RPN** | `https://bokonzi.com/rpn` | plateforme d'apprentissage | ✅ oui (système propre) |

Ce sont deux bases de données séparées. **Un quiz du blog ne peut être lié qu'à un
article du blog** ; un quiz RPN qu'à un article RPN. Ce guide couvre **le blog**.

---

## 1. Où ça tourne

- **Base URL de l'API** : `https://luvumbu.com/articles/blog/api/`
- Chaque endpoint inclut `api/_bootstrap.php` : force `Content-Type: application/json`,
  active CORS, charge la config + la base (`$pdo`), définit `json_response()` / `json_error()`.
- Réponses toujours en JSON. Erreurs : `{ "error": "message" }` + code HTTP 4xx/5xx.
- Les tables sont créées/migrées automatiquement au chargement (`includes/bootstrap.php`),
  y compris les tables quiz. Rien à faire manuellement en base.

---

## 2. Le système de clés (IMPORTANT)

Deux secrets 64-hexadécimaux **différents** cohabitent. Les confondre = erreur n°1.

| | Clé API (token) | Clé de synchronisation |
|---|---|---|
| Table / fichier | `api_tokens` (BDD) | `config/sync_keys.json` |
| Sert à | articles + quiz à distance | recevoir un dump SQL entre instances |
| Endpoints | `api/article.php`, `api/quiz.php`, `api/me.php` | `api/sync_receive.php` |
| Vérifiée par | `api/_auth.php` → `api_current_user()` | `includes/sync_keys.php` |

> ⚠️ Une clé de synchro **ne fonctionne pas** comme clé API (toujours `401` sur `api/article.php`).

### 2.1 Obtenir une clé API valide

Une clé n'existe que si elle est **inscrite dans `api_tokens`**. Trois voies :

1. `POST api/login.php` avec `{ "email", "password" }` → token 30 jours.
2. Page admin **« Clés API »** (`pages/api_tokens.php`) : bouton *Générer* (30 j → 10 ans).
   Réaffichable plus tard via *Révéler* (reconfirmation du mot de passe admin).
3. Insertion SQL directe dans `api_tokens` (rattachée à un `user_id` admin).

Un token qui ne vient pas de cette table donnera toujours `401`.
Diagnostic : `SELECT LEFT(token,12), expires_at, (expires_at>NOW()) FROM api_tokens;`

### 2.2 Fournir la clé dans une requête

`api/_auth.php` → `api_extract_token()` accepte la clé de **trois** façons, dans l'ordre.
Les 2 dernières existent car en FastCGI/FPM (Hostinger), Apache supprime parfois le
header `Authorization` → `401` trompeur.

1. Header `Authorization: Bearer <token>`
2. Header `X-Api-Key: <token>`
3. Champ **`api_key`** dans le corps (query string, form-data, ou JSON)

> ✅ **Recommandé pour un agent : le champ `api_key` dans le JSON.** Seul canal
> qu'aucune configuration serveur ne peut casser.

---

## 3. Articles (recettes cURL)

### 3.1 Qui suis-je (récupérer l'email admin)
```bash
curl -s -X POST https://luvumbu.com/articles/blog/api/me.php \
  -H "Content-Type: application/json" -d '{"api_key":"<TOKEN>"}'
# -> {"user":{"id":1,"email":"...","is_admin":1}, "login_url":"..."}
```

### 3.2 Tester une clé sans rien créer
`422` = clé valide (auth OK, contenu manquant). `401` = clé invalide.
```bash
curl -s -X POST https://luvumbu.com/articles/blog/api/article.php \
  -H "Content-Type: application/json" -d '{"api_key":"<TOKEN>","titre":"","contenu":""}'
```

### 3.3 Publier
Champs : `titre`* (≤190), `contenu`*, `sources`, `parent_id`, `visible` (0/1).
```bash
curl -s -X POST https://luvumbu.com/articles/blog/api/article.php \
  -H "Content-Type: application/json" \
  -d '{"api_key":"<TOKEN>","titre":"Mon titre","contenu":"Mon texte"}'
# -> 201 {"id":204,"message":"Article publié"}
```
> Piège shell : apostrophes et sauts de ligne cassent le JSON en ligne. Écrire le JSON
> dans un fichier et l'envoyer avec `--data-binary @fichier.json`.

### 3.4 Modifier / Supprimer
Override via `_method` (`api/article.php`). Un `id` seul (>0) déclenche aussi l'édition.
```bash
# Modifier
-d '{"api_key":"<TOKEN>","_method":"PUT","id":204,"titre":"...","contenu":"..."}'
# Supprimer
-d '{"api_key":"<TOKEN>","_method":"DELETE","id":204}'
```
> Droits : édition/suppression réservées à l'**auteur** ou à un **admin**.

### 3.5 Limite de rendu
`pages/article.php` affiche le contenu via `nl2br(e($contenu))` : le HTML est **échappé**.
Le corps d'article = texte mis en forme par sauts de ligne, **pas** de HTML/JS interactif.
Pour un QCM interactif → utiliser le **module quiz** ci-dessous (pas le corps d'article).

---

## 4. Module Quiz (QCM lié à un article)

Ajouté sur le modèle de RPN. Un quiz interactif s'affiche en bas de l'article auquel
il est rattaché, et se répond sur une page dédiée (correction + score côté client).

### 4.1 Tables (créées auto par `includes/bootstrap.php`)
- `quizzes` (id, title, description, active, author_id, author_name, …)
- `quiz_questions` (id, quiz_id, body, explanation, type `single`|`multiple`, position)
- `quiz_options` (id, question_id, label, is_correct 0/1, position)
- `article_quizzes` (article_id, quiz_id, position) — le lien article ⇄ quiz

### 4.2 Créer un QCM à distance — `POST api/quiz.php`
Protégé par **clé API**. Corps JSON :
```jsonc
{
  "api_key":     "<TOKEN>",
  "title":       "QCM : mon sujet",      // requis, ≤200
  "description": "Présentation courte",  // facultatif
  "active":      1,                        // 1 = publié (défaut)
  "author_name": "Cours",                 // affiché (défaut « Quiz »)
  "article_id":  203,                      // rattache le quiz à cet article (facultatif)
  "questions": [                           // requis, ≥1 question valide
    {
      "body": "Énoncé de la question ?",
      "explanation": "Pourquoi c'est la bonne réponse.",  // facultatif
      "type": "single",                    // 'single' (défaut) | 'multiple'
      "options": [                          // ≥2 options, ≥1 correcte
        { "label": "Bonne réponse", "correct": true },
        { "label": "Mauvaise",      "correct": false },
        { "label": "Mauvaise",      "correct": false },
        { "label": "Mauvaise",      "correct": false }
      ]
    }
  ]
}
```
Réponse : `201 { "ok":true, "quiz_id":201, "questions":12, "options":48, "linked_article":203, "url":"...pages/quiz.php?id=201" }`

Notes :
- **Anti-doublon** : un quiz du **même `title`** est *mis à jour* (ses questions sont
  remplacées) au lieu d'en créer un second. Republier est donc idempotent.
- Questions invalides (< 2 options ou aucune `correct`) : **ignorées** en silence.
- Plafonds : 200 questions, 20 options/question. Texte des énoncés/options dé-balisé (anti-XSS).
- ⚠️ **Bonne pratique** : mélanger l'ordre des options avant l'envoi, sinon la bonne
  réponse peut toujours tomber en 1ʳᵉ position (prévisible). Ex. `shuffle()` côté générateur.

### 4.3 Où ça s'affiche
- Sur l'article : `pages/article.php` ajoute en bas un encadré **« 📝 Teste tes
  connaissances »** listant les quiz liés (publiés pour le public ; brouillons visibles
  par l'auteur/admin).
- Page de réponse : `pages/quiz.php?id=<quizId>` — QCM interactif, correction immédiate
  (vert/rouge), explication par question, score final `/n` + badge. Un quiz en brouillon
  (`active=0`) n'est visible que par son auteur ou un admin.

### 4.4 Recette complète : cours + QCM
```bash
# 1) publier l'article (récupérer son id, ex. 203)
curl -s -X POST .../api/article.php --data-binary @article.json
# 2) créer le QCM lié à l'article 203
curl -s -X POST .../api/quiz.php     --data-binary @quiz.json   # quiz.json contient "article_id":203
```

---

## 5. Connexion à l'administration

Deux voies vers `pages/admin.php` :
1. **Par email** : `pages/login.php`, email du compte (`is_admin=1`) + mot de passe.
2. **Par identifiants de base** : bouton « 🔐 Administration » → `login.php?next=admin`.
   Mot de passe attendu = **`DB_PASS`** de `config/config.php` (le nom d'utilisateur n'est
   pas vérifié). La session s'ouvre au nom du 1er compte admin.
   ⚠️ Aucune limite d'essais : `DB_PASS` doit rester long et aléatoire.

---

## 6. Sécurité

- Ne jamais exposer publiquement `config/config.php`, `config/sync_keys.json`,
  `_diagnostic.php`, `_fix_db.php` (gitignorés ou protégés par `require_admin()`).
- Révoquer une clé API dès qu'elle fuit : page « Clés API » → *Révoquer*, ou
  `DELETE FROM api_tokens WHERE token='...';`.
- `DB_PASS` sert aussi à la connexion admin depuis Internet : le changer périodiquement,
  et le reporter dans `blog/config/config.php` **et** `direct_file/config/database.php`.
- Requêtes SQL toujours préparées (jamais d'interpolation directe).

---

## 7. Endpoints (résumé)

| Méthode | Endpoint | Auth | Rôle |
|---|---|---|---|
| POST | `api/login.php` | — | email+password → token |
| POST | `api/me.php` | clé API | qui suis-je |
| GET  | `api/articles.php` | — | liste paginée |
| GET  | `api/article.php?id=X` | — | détail article |
| POST | `api/article.php` | clé API | créer (`_method=PUT` éditer, `DELETE` supprimer) |
| POST | `api/quiz.php` | clé API | créer un QCM (+ `article_id` pour le lier) |
| GET  | `pages/quiz.php?id=X` | — | répondre au QCM (public si `active=1`) |
| GET  | `api/site_info.php` | — | paramètres publics |
| GET  | `api/version.php` | — | version app mobile |
| POST | `api/sync_receive.php` | clé **synchro** | recevoir un dump (⚠️ pas une clé API) |

---

*Document maintenu pour servir de point d'entrée à un agent IA. Vérifier `blog/api/`
et `blog/includes/bootstrap.php` si un comportement diffère : le code fait foi.*
