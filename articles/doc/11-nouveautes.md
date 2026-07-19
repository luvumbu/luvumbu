# 11 — Nouveautés (mai 2026)

Récapitulatif, **par thème**, de tout ce qui a été ajouté lors de cette itération. Les détails de référence restent dans les fichiers dédiés (03-database, 04-api, 06-sync, 07-mobile-app, 08-admin, 09-security).

---

## 1. Visibilité des articles (publié / masqué)

Chaque article peut être **visible** ou **masqué** (brouillon).

- **BDD** : nouvelle colonne `articles.visible` (`TINYINT(1) NOT NULL DEFAULT 1`).
- **Création / édition** (`article_new.php`, `article_edit.php`) : case à cocher « Article visible publiquement ».
- **Affichage public** (`index.php`, `pages/article.php`) : un article masqué est **invisible au public** (404), mais **reste visible à son auteur et aux admins** avec un badge `🔒 masqué`. Les sous-articles masqués sont filtrés de la même façon.
- **Bascule rapide** : bouton `🔒 Masquer` / `👁️ Rendre visible` sur la page de l'article et dans le **tableau de bord admin** (colonne *Statut*).
- **API** : `api/articles.php` et `api/article.php` sont *author-aware* (token Bearer) — un article masqué reste accessible à son auteur/admin, invisible (404) pour le public. Le POST accepte un champ optionnel `visible`.

## 2. Compteur de vues (par adresse IP unique)

- **BDD** : nouvelle table `article_views(article_id, ip_hash, created_at)` avec contrainte **`UNIQUE(article_id, ip_hash)`** → une même IP n'est comptée qu'une fois par article.
- L'IP est **hashée en SHA-256** avant stockage (pas d'IP en clair). Helper `client_ip()` (gère Cloudflare / `X-Forwarded-For`).
- Enregistrement best-effort (`record_article_view()`), qui **crée la table si elle manque** puis réessaie.
- **Vues de la page d'accueil** distinctes des articles : on réutilise la table avec `article_id = 0` (`record_home_view()`).
- **Affichage** : `👁️ N vues` sur l'article et les cartes d'accueil ; tableau de bord admin avec **Visiteurs uniques (IP)**, **Vues page d'accueil**, **Vues articles**.

## 3. Application mobile (PWA)

- **Actions complètes comme le web** dans le détail d'un article (pour l'auteur / admin) : **Modifier**, **Masquer / Rendre visible**, **Supprimer** (suppression en cascade via un nouvel endpoint), **+ Sous-article**. Badge `🔒 masqué` dans la liste et le détail.
- **Bouton « Vérifier les mises à jour »** (🔄) : vérifie la version serveur et donne un retour clair (« À jour ✓ » ou « Nouvelle version dispo 🚀 »), au lieu d'une mise à jour brutale.
- **Qualité photo** : on ne recompresse plus qu'au-delà de la limite serveur (**14 Mo**) ; toute photo en dessous part **intacte**. Limite serveur relevée 12 → 14 Mo.
- **Visionneuse plein écran** : bouton **🔍 Voir en entier** sur la couverture et les images de galerie → photo complète, non recadrée (`object-fit: contain`).
- **Icône** : remplacée par une **feuille** (script `generate-icons.ps1` réécrit, GDI+). Cache-bust des icônes `?v=18`.
- **Affichage/zoom** : champ légende passé à 16 px (évite le zoom auto iOS), `text-size-adjust`, `overflow-x: hidden`.

## 4. Web — voir la photo en entier

- Même **visionneuse plein écran** que l'app, sur `pages/article.php` **et** les vignettes de l'accueil. Markup + script centralisés dans `includes/footer.php` (donc présents sur toutes les pages).
- **Cache-bust automatique du CSS** : `styles.css?v=<filemtime>` dans `header.php` → fini le CSS périmé après déploiement.

## 5. Synchronisation local → serveur

- **Clés permanentes** : en plus des clés à usage unique, on peut générer une clé **♾️ sans expiration, réutilisable** (`sync_key_generate(0)`), utile pour automatiser (`includes/sync_keys.php`, `pages/sync_keys.php`).
- **Clé consommée seulement après validation du payload** : un envoi incomplet ne « brûle » plus une clé à usage unique. Le **dry-run ne consomme pas** la clé.
- **Payload conditionnel** : `sync_build_payload()` n'inclut `data.json` et/ou `uploads/` que selon les cases cochées (n'envoie plus les images inutilement).
- **Option SSL** : case « Ignorer la vérification SSL » (dépannage XAMPP local sans bundle CA) + garde-fou `CURLOPT_CONNECTTIMEOUT`.
- **Trois modes d'application** (`sync_apply_payload`) :
  - `miroir` — remplace tout (clone) ;
  - `fusion` — ajoute sans écraser (`INSERT IGNORE`) ;
  - **`upsert`** (nouveau) — ajoute **ou met à jour** par ID (`INSERT … ON DUPLICATE KEY UPDATE`), **sans jamais écraser `users` / `settings`** (préserve comptes et réglages du serveur).
- **Envoi d'un seul article en un clic** : bouton **📤 Envoyer vers le serveur** à côté de *Modifier* (auteur/admin). Endpoint `pages/article_push.php` : construit un payload avec l'article + sa chaîne de parents + ses images, et l'envoie en mode `upsert`.
  - **Cible enregistrée** : URL + **clé permanente** mémorisées une fois (réglages `sync_remote_url`, `sync_remote_key`) ; un champ clé éditable est aussi présent à côté du bouton sur l'article.
  - **Garde-fou anti-écrasement** : avant l'envoi réel, un **dry-run vérifie que le serveur connaît le mode `upsert`**. Si la prod n'est pas à jour (vieux `sync_receive` qui mapperait `upsert` → `miroir`), l'envoi est **refusé** (pas de destruction de la base).
- **Rapport d'envoi détaillé** (`pages/sync_push.php`) : journal des étapes, mode, code HTTP, durée, taille envoyée, ce que le serveur a appliqué (table par table), et réponse brute.

> ⚠️ Le mode `upsert` requiert que `api/sync_receive.php` + `includes/sync_dump.php` soient **déployés sur la prod**. Tant que ce n'est pas fait, le bouton « Envoyer » d'un article refuse l'envoi (par sécurité).

## 6. Service worker & cache

- `sw.js` remis en **kill-switch** : ne met plus rien en cache, vide les caches et se désinstalle (l'ancienne version cachait des chemins absolus erronés → erreurs `addAll Request failed` + pages/CSS périmés).
- `header.php` **désinscrit tout service worker** et vide les caches à chaque chargement.

## 7. Base de données — migrations

- Auto-migration (`includes/bootstrap.php`) passée en **version 4** :
  - `articles.visible` (TINYINT, défaut 1) ;
  - table `article_views`.
- Idem dans `pages/migrate.php` et `sql/schema.sql`. Migration **idempotente** et auto-réparante.

## 8. Sécurité

- IP des visiteurs **hashée (SHA-256)**, jamais stockée en clair.
- Mode `upsert` qui **ne touche jamais** `users` / `settings` (pas d'écrasement de mots de passe/réglages).
- Garde-fou dry-run avant tout envoi réel par article (anti-`miroir` accidentel).
- Rappel : ne jamais committer les clés/secrets ; la clé de sync permanente est stockée en **BDD locale** (réglages), pas dans un fichier versionné.

---

## Fichiers déployés en prod nécessaires aux nouveautés

À déployer côté serveur (les autres sont côté émetteur local) :

- `includes/bootstrap.php` (auto-migration v4 : `visible`, `article_views`)
- `api/sync_receive.php` + `includes/sync_dump.php` (mode `upsert`)
- `api/article.php`, `api/articles.php` (visibilité, vues)
- `index.php`, `pages/*` et `assets/css/styles.css` (affichage public, visionneuse)
- `mobile-app/*` + `api/version.php` (app v17 : actions, icône feuille, qualité photo, visionneuse)
