# ztransfert — Récapitulatif des corrections & fonctionnalités

Document de synthèse de tout ce qui a été corrigé et ajouté sur le projet
**ztransfert** (application de transfert de fichiers façon WeTransfer, en PHP/MySQL).

Copie de travail : `C:\xampp\htdocs\luvumbu\ztransfert\`
URL locale : `http://localhost/luvumbu/ztransfert/`

---

## 1. Le flux de l'application

1. **`index.php`** — page d'accueil (offres + zone d'envoi `#upload`).
2. L'utilisateur choisit un fichier → **`name.php`** enregistre un nom unique (timestamp) en session.
3. **`upload.js`** découpe le fichier en morceaux et les envoie à **`upload.php`**, qui réassemble le fichier dans `uploads/`.
4. **`config.php`** crée la table si besoin et insère le transfert en base, puis redirige.
5. **`envoi_ok.php`** affiche le lien de partage et le formulaire d'envoi par mail.
6. **`all_doc.php?name=...`** = page de téléchargement du fichier.
7. **`send_mail.php`** envoie le lien par e-mail.

---

## 2. Corrections effectuées

### 2.1 `index.php` — double document HTML
Le fichier contenait **deux pages HTML collées** (2 `<!DOCTYPE>`, 2 `<body>`), UI dédoublée.
→ Réécrit en **un seul document propre**, avec génération du nom au moment de la
sélection du fichier (`onchange`) et affichage du nom choisi.

### 2.2 Liens cassés (`404`) → passage en `?name=`
Les liens de téléchargement étaient codés en dur (`/ztransfert/...`) ou en
**PATH_INFO** (`all_doc.php/nom`), ce qui donnait des **404** selon la config Apache
et selon le dossier de déploiement (l'app est sous `/luvumbu/ztransfert/`).
→ Tout est passé en **paramètre d'URL** `all_doc.php?name=...`, avec préfixe de
dossier **dynamique** (`dirname($_SERVER['SCRIPT_NAME'])`). Fonctionne partout.
Fichiers touchés : `envoi_ok.php`, `send_mail.php`, `all_doc.php`.

### 2.3 `send_mail.php` — mauvais chemin
Le lien de l'e-mail pointait vers `/we_transfert/` (dossier inexistant).
→ Corrigé (chemin dynamique `?name=`).

### 2.4 `DatabaseHandler.php` — compatibilité PHP 8.1+
Depuis PHP 8.1, `mysqli` **lève des exceptions fatales** par défaut. Tout le code
qui « ignorait » les erreurs plantait (écran noir).
→ Ajout de `mysqli_report(MYSQLI_REPORT_OFF)` + `CREATE DATABASE/TABLE IF NOT EXISTS`.

### 2.5 `config.php` — écran noir après upload
Cumul de : identifiants Hostinger inexistants en local + erreur mysqli fatale +
message d'erreur en **texte noir sur fond noir**.
→ Résolu par la correction 2.4, la création de la base locale (voir §4) et le
passage du texte en blanc.

### 2.6 `all_doc.php` — `TypeError` sur `count()`
`count()` sur une valeur potentiellement absente (fatal en PHP 8).
→ Remplacé par un test `empty()` robuste. Nom d'URL nettoyé (anti-injection SQL).

### 2.7 Bouton « MENU PRINCIPAL »
Pointait vers `../` (sortait de l'app).
→ Pointe désormais vers `index.php#upload` (retour direct à la zone d'envoi).

---

## 3. Fonctionnalités ajoutées

### 3.1 Boutons de paiement Stripe (page d'accueil)
Les offres payantes renvoient vers les **Payment Links Stripe** (issus de
`BOKONZI_COM/core/stripe_config.php`), ouverts dans un nouvel onglet.
Prix alignés sur ce que Stripe facture réellement :

| Offre ztransfert | Prix | Lien Stripe |
|---|---|---|
| Essentiel | 1,99 € | Bronze |
| Pro | 6,99 € | Or |
| Premium | 12,99 € | Platine |

> ⚠ Le reçu Stripe affiche « BOKONZI Bronze/Or/Platine » (produits BOKONZI).
> Aucun webhook branché côté ztransfert : le paiement n'active rien automatiquement.

### 3.2 Espace admin (`admin.php`)
Espace protégé pour **gérer tous les fichiers en interne**.

- **Connexion façon luvumbu** : tentative de **connexion MySQL réelle** avec
  l'utilisateur + mot de passe tapés. Si MySQL accepte → admin.
  - En local : `root` + mot de passe **vide**
  - En prod : `u489596434_marion` + `v3p9r3e@59A`
  - Champs **vides** par défaut (pas de pré-remplissage).
- **Statistiques** : nb de transferts, nb de fichiers orphelins, poids total disque.
- **Tableau des transferts** (base) : nom (lien), fichier, taille, date, télécharger, supprimer.
- **Tableau des fichiers orphelins** (présents dans `uploads/` mais absents de la base).
- **Sélection multiple** : case à cocher par ligne + « **Tout sélectionner** » (en-tête
  et bas de tableau) + bouton « **🗑️ Supprimer la sélection** ».
- Bouton « **🛠️ Admin** » dans la barre du haut, à côté de « Envoyer un fichier ».

**Sécurité admin** :
- Jeton **CSRF** sur toutes les suppressions.
- **Requêtes préparées** (pas d'injection SQL).
- Protection **anti-`../`** (`safe_upload_path`) : suppression impossible hors de `uploads/`.
- **Confirmation** avant suppression (avec le nombre d'éléments) + garde-fou si rien n'est coché.

---

## 4. Base de données

- Identifiants **Hostinger (prod)** : utilisateur `u489596434_marion`,
  mot de passe `v3p9r3e@59A`, base `u489596434_marion` (l'utilisateur EST le nom de base).
- **En local**, la base + l'utilisateur ont été **recréés à l'identique** dans le MySQL
  de XAMPP, pour que **les mêmes identifiants marchent en local ET en prod**.
- Table `we_transfert` : `id_transfert`, `file_path`, `total`, `name`, `date_inscription_user`.

---

## 5. E-mail

- L'envoi utilise la fonction PHP `mail()`.
- **En local (XAMPP), l'envoi ne fonctionne pas** (aucun serveur SMTP configuré) :
  le formulaire affichera « Échec de l'envoi ». C'est normal — ça marchera sur Hostinger.
- Le lien de téléchargement reste affiché et copiable manuellement.
- Une adresse en dur (`contact@bokonzi.com`) reçoit une **copie de chaque transfert** (voulu).

---

## 6. Points d'attention / à faire éventuellement

- [ ] **Sécuriser `config.php` et `all_doc.php`** avec des requêtes préparées (injection SQL).
- [ ] **Synchroniser les deux copies** du projet : `luvumbu/ztransfert/` (celle utilisée)
      et `ztransfert/ztransfert/` (ancienne copie de travail, non à jour).
- [ ] **Webhook Stripe** si l'on veut activer une offre après paiement.
- [ ] **Produits Stripe dédiés** à ztransfert (pour ne plus afficher « BOKONZI » sur le reçu).
- [ ] Envoi d'e-mail via **PHPMailer/SMTP** si besoin de tester le mail en local.

---

*Dernière mise à jour : 2026-07-20.*
