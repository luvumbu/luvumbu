# Guide — Modifier & publier la page Compétences

Comment modifier les éléments de la page compétences/portfolio, puis la mettre en ligne.

---

## 1. Les deux fichiers (important à comprendre)

| Fichier | Rôle | Faut-il l'éditer ? |
|---|---|---|
| `competences.php` | **Source lisible** : tout le contenu est dans des tableaux PHP en haut du fichier. | ✅ **Oui, c'est ici qu'on modifie.** |
| `competences.html` | **Version figée servie en ligne** (`luvumbu.com/competences.html`). Générée à partir du `.php`. | ❌ Non à la main — on la **régénère** (voir §4). |

> Règle d'or : on modifie **`competences.php`**, puis on **régénère** `competences.html`, puis on **publie**.

---

## 2. Où se trouve chaque élément (dans `competences.php`)

Tout est en haut du fichier, dans des variables PHP :

| Variable | Ce qu'elle contient |
|---|---|
| `$IDENTITE` | Nom, titre, sous-titre, phrase de présentation (pitch). |
| `$STATS` | Les 4 chiffres clés (ex. `['12', 'projets réalisés']`). |
| `$SKILLS` | Les blocs de compétences : `['émoji', 'Titre', ['chip1','chip2',...]]`. |
| `$PROJECTS` | **La liste des projets** (voir §3). |
| `$CATS` | Les boutons de filtre (`Tous`, `Web`, `Mobile`, …). |

---

## 3. Ajouter / modifier un projet

Chaque projet est un tableau dans `$PROJECTS`. Modèle à copier-coller :

```php
[
  // 'flag'=>true,                      // décommente pour la carte "réalisation phare" (mise en avant)
  'emoji'=>'🚀',
  'name'=>'Nom du projet',
  'sub'=>'Sous-titre court',
  'cats'=>['Web','API'],               // doivent exister dans $CATS
  'context'=>"Ce que fait le projet, en 1–2 phrases.",
  'role'=>"Ton rôle sur le projet.",
  'stack'=>['PHP','MySQL','JavaScript'], // technos → affichées en 'chips'
  'skills'=>[
    "Compétence démontrée n°1",
    "Compétence démontrée n°2",
  ],
  'result'=>"Le résultat concret / l'état final.",
  'url'=>'https://luvumbu.com/mon-projet/', // LIEN "Voir en ligne" (bouton orange)
  'urlLabel'=>'luvumbu.com/mon-projet',      // texte affiché du lien
],
```

### Pour AJOUTER un lien à un projet qui n'en a pas
Remplace ses deux lignes vides :
```php
'url'=>'','urlLabel'=>'',
```
par l'URL réelle :
```php
'url'=>'https://luvumbu.com/mon-projet/','urlLabel'=>'luvumbu.com/mon-projet',
```
> Si `url` est vide, **aucun bouton** n'est affiché pour ce projet. C'est voulu (ex. PhotoSync = app mobile sans page web).

⚠️ Le serveur est sensible à la **casse** (majuscules/minuscules) : `Cours_complet_canvas` ≠ `cours_complet_canvas`. Vérifie que l'URL existe vraiment en ligne.

---

## 4. Changer les couleurs

Deux endroits, dans la balise `<style>` de `competences.php` :

- **Palette générale** : la section `:root{ --bg:… --accent:… --gold:… }` (couleurs de fond, texte, accents).
- **Bouton "Voir en ligne"** (le lien orange) : la règle `.link{ … background:linear-gradient(90deg,#ff7a00,#ff9d2f); … }`.
  Change les deux codes `#ff7a00` et `#ff9d2f` pour une autre couleur, et adapte l'ombre `box-shadow: … rgba(255,122,0,…)`.

---

## 5. Régénérer `competences.html` (obligatoire après toute modif)

Une seule commande, dans le dossier du projet :

```
C:\xampp\php\php.exe competences.php > competences.html
```

Elle exécute le `.php` et écrit le résultat dans `competences.html`. À faire **à chaque fois** que tu modifies le `.php`, sinon la version en ligne ne changera pas.

---

## 6. Publier en ligne

Le fichier `competences.html` doit être envoyé sur le serveur. Deux méthodes :

### Méthode A — via l'API du gestionnaire de fichiers (rapide, en 1 commande)
Le site expose `_gestion/api.php` avec une **clé d'API** (dans `_gestion/apikey.local.php`).

```
curl -sS "https://luvumbu.com/_gestion/api.php" \
  --data-urlencode "action=save" \
  --data-urlencode "key=TA_CLE_API" \
  --data-urlencode "path=competences.html" \
  --data-urlencode "content@C:/xampp/htdocs/luvumbu/competences.html"
```
Réponse attendue : `{"ok":true,"path":"competences.html"}`.

> Fonctionne pour **n'importe quel fichier texte** du site : change juste `path=` et le fichier local.

### Méthode B — gestionnaire de fichiers Hostinger (sans clé, à la souris)
1. hpanel.hostinger.com → **Gestionnaire de fichiers** → dossier `public_html`
2. **Upload** `competences.html` → **Remplacer / Overwrite** = Oui

### Puis, dans les deux cas
Ouvre `https://luvumbu.com/competences.html` et fais **Ctrl + F5** (vide le cache du navigateur).

---

## 7. Sécurité de la clé API

- La clé vit dans `_gestion/apikey.local.php` (hors dépôt Git) : `<?php return 'ta_cle';`
- **Ne la colle jamais** dans un chat, un e-mail, un fichier versionné.
- Si elle a fuité, régénère-la des deux côtés (local + serveur) :
  ```
  C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(24));"
  ```
  Mets la nouvelle valeur dans `apikey.local.php` en local **et** en ligne.

---

## Récap express

1. J'édite **`competences.php`** (§2–4)
2. Je régénère : `C:\xampp\php\php.exe competences.php > competences.html` (§5)
3. Je publie : commande `curl … action=save` **ou** upload Hostinger (§6)
4. `Ctrl + F5` sur `luvumbu.com/competences.html`
