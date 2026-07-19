# 🐣 Tamagotchi Éducatif — Guide complet

Application web éducative (enfants de 3 à 16 ans) : une créature virtuelle qu'on
nourrit et fait évoluer en **réussissant des exercices** (maternelle → lycée).
En ligne sur **https://luvumbu.com/tamagotchi/public/** + une **app Android**.

---

## 1. 🧩 Architecture — comment ça marche

```
📱 App Android / 🌐 Navigateur   ←→   🖥️ Serveur PHP (API)   ←→   🗄️ Base MySQL
        (affichage)                     (règles du jeu)          (toutes les données)
```

- **Tout est stocké sur le serveur** (comptes, enfants, créatures, points, progression).
- L'app et le site ne font qu'**afficher** et **envoyer** les changements.
- L'**app Android** est une coquille (WebView) qui charge le site en direct → dès que
  tu modifies le site, l'app se met à jour **toute seule** (sauf le « natif » : voix, scroll, connexion Google).

### ⏱️ Les jauges baissent sans cron
Il n'y a **aucune tâche automatique**. Chaque créature a une date `last_update`.
Quand on **ouvre l'app**, le serveur calcule le temps écoulé et applique la baisse d'un coup :

```
faim = faim + hunger_rate × (heures écoulées depuis last_update)
```

→ C'est le **serveur PHP** qui calcule, avec **l'horloge du serveur** (pas celle du
téléphone → impossible de tricher en changeant la date du tél).

---

## 2. ❤️ Les jauges de la créature

| Jauge | Sens | Vitesse (réglable) |
|---|---|---|
| 🍖 Faim | **monte** | `hunger_rate` (8/h → 1 point / ~7 min) |
| ❤️ Santé | descend | `health_rate` (3/h → 1 point / 20 min) |
| 😊 Bonheur | descend | `happiness_rate` (5/h → 1 point / 12 min) |
| ⚡ Énergie | descend | `energy_rate` (6/h → 1 point / 10 min) |

**Actions :** `feed_hunger` (nourrir), `play_happiness` / `play_energy` (jouer),
`sleep_energy` (dormir). `max_stat` = 100, `death_threshold` = mort à 0.

- 🍖 Faim pleine (100) → **mort** (≈ 12–13 h sans nourrir).
- ❤️ Santé à 0 → **mort** (baisse plus vite si la créature est affamée/épuisée).
- La santé **remonte** avec les bons aliments (carotte +6, poisson +8…).

> 💡 Tous les chiffres des réglages sont **positifs** : c'est juste la *vitesse*.
> Le sens (+ faim, − le reste) est géré par le code.

---

## 3. 🧠 L'apprentissage (récompenses)

À **chaque bonne réponse** l'enfant gagne :
- 💰 `points_per_correct` — points (monnaie pour la boutique)
- 🧠 `knowledge_per_correct` — connaissance
- 😊 `happiness_per_correct` — bonheur

À la **fin d'un quiz** : bonus selon le nombre de questions (5→10, 10→25, 25→75).
Sans aucune faute → bonus **× `perfect_multiplier`** (ex. ×1,5).

---

## 4. 👨‍👩‍👧 Comptes : parents + enfants

- Le **parent** se connecte avec **Google** (Sign in with Google).
- Il crée jusqu'à **8 profils enfants** (prénom + avatar).
- **Chaque enfant a SA créature, ses points, sa progression** (isolés).
- Même compte = mêmes données **partout** (app, navigateur, autre appareil).

---

## 5. 🗑️ Effacer les données — qui peut quoi

| Qui | Peut effacer |
|---|---|
| 👤 **Parent** (dans le jeu) | **Seulement ses propres** enfants + son propre compte |
| 👨‍💼 **Admin** (admin.php) | **Tout** : n'importe quel compte, n'importe quel enfant |

Sécurité : chaque action d'un parent est vérifiée côté serveur (il ne peut pas
toucher aux données des autres parents).

---

## 6. 🛠️ L'espace admin

Accès : lien **« 🛠️ Espace admin »** sur l'écran de connexion, ou directement
**`/tamagotchi/public/admin.php`**. Protégé par un **mot de passe** (défini au 1er accès).

| Onglet | Rôle |
|---|---|
| 📊 Stats | Nb de parents, enfants, créatures, bonnes réponses |
| 👥 Comptes | Voir / supprimer les comptes et profils |
| ⚙️ Jeu | Régler points, vitesses, seuils… (sans toucher au code) |
| 🗄️ Base | Changer hôte / nom / utilisateur / mot de passe de la base |
| 🏪 Boutique | Modifier / ajouter / supprimer les aliments |

> ⚠️ Mettre un **mot de passe admin solide** au premier accès.

---

## 7. 📱 L'application Android

- APK : `public/TamagotchiEducatif.apk` (téléchargeable en ligne).
- Charge le site en direct → **mises à jour de contenu automatiques**.
- Parties **natives** (nécessitent de recompiler l'APK, rare) :
  - 🔊 Voix (synthèse vocale)
  - 📜 Scroll fluide
  - 🔄 Bouton actualiser
  - 🔐 **Connexion Google native** (Google la bloque dans une WebView)

### Config Google (pour la connexion)
- **ID client Web** (dans le code) : sert de `serverClientId`.
- **ID client Android** (console Google) : package `com.tamagotchi.edu` + empreinte SHA-1.
- Écran de consentement : ajouter son Gmail en **testeur** ou **publier l'app**.
- ⚠️ Piège vécu : bien écrire `com.tamagotchi.edu` (avec le **u**).

---

## 8. 🚀 Déployer une mise à jour

| Type de changement | À faire |
|---|---|
| Contenu, textes, exercices, **design/CSS**, logique JS, API/PHP | Envoyer le(s) fichier(s) sur le serveur → 🔄 dans l'app. **Pas d'APK.** |
| Voix / scroll / connexion Google / adresse du site | Recompiler + réinstaller l'**APK** (rare) |
| Nouvelle table / colonne en base | Lancer le script de migration (ex. `setup-accounts.php`) |

**Astuce fiable :** mettre les fichiers dans un **.zip** rangé (bonne arborescence),
l'envoyer sur le serveur, puis **clic droit → Extraire** → tout se place au bon endroit.

### ⚠️ À NE jamais faire
- Ne **jamais** envoyer le `config.php` de ton PC (identifiants `root` locaux → casse la base en ligne).
- Après installation, **supprimer** `install.php` et `setup-accounts.php` du serveur.

---

## 9. 🔧 Dépannage rapide

| Symptôme | Cause probable | Solution |
|---|---|---|
| `DB connection failed` partout | `config.php` a de mauvais identifiants | Refaire `install.php` avec les vrais identifiants MySQL |
| Page renvoie vers `install.php` | Base non connectée | Idem ci-dessus |
| Modif pas visible | Fichier pas arrivé / cache | Vérifier la taille du fichier en ligne, re-envoyer, 🔄 |
| Bouton Google ne s'affiche pas (app) | Ancien APK ou vieux `auth.js` | Installer le nouvel APK + envoyer le bon `auth.js` |
| Google « during begin » | ID client Android absent / mauvais package | Créer l'ID client Android (bon package + SHA-1) |
| Google « cancelled by user » | Fenêtre fermée / pas de compte Google sur le tél | Choisir son compte / ajouter un compte Google |

---

*Dernière mise à jour du guide : 2026-07-19.*
