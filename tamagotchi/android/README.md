# 🐣 Tamagotchi Éducatif — App Android (Kotlin)

Application Android **native (Kotlin)** qui affiche l'application web du Tamagotchi éducatif.

## 🎯 Principe : mise à jour automatique
L'app est une **WebView** qui charge le site **en ligne**. Résultat :
- Le **rendu est exactement identique** au web (c'est le même code HTML/CSS/JS).
- Quand tu **mets à jour le site en ligne**, l'app affiche **automatiquement** la nouvelle version — tu n'as **rien à republier** côté Android. 🎉

```
┌─────────────────┐     charge en ligne      ┌──────────────────────────┐
│  App Android     │ ───────────────────────▶ │  Ton serveur web (PHP)   │
│  (WebView Kotlin)│ ◀─────────────────────── │  tamagotchi/public/      │
└─────────────────┘   dernière version        └──────────────────────────┘
```

---

## ⚙️ 1. Configurer l'adresse du site
Ouvre **`app/build.gradle`** et change la ligne `APP_URL` :

```gradle
buildConfigField "String", "APP_URL", "\"http://192.168.1.20/tamagotchi/public/\""
```

- **Test sur le même wifi** : mets l'IP locale de ton PC (ex. `http://192.168.1.20/tamagotchi/public/`).
  Trouve ton IP avec `ipconfig` (Windows). Le PC doit avoir XAMPP (Apache + MySQL) allumé.
- **Vraie app publique** : héberge l'appli (PHP + MySQL) chez un hébergeur, puis mets
  l'URL `https://` de ton site. C'est cette version en ligne qui se mettra à jour.

> ⚠️ En `http` (IP locale) l'app fonctionne car `usesCleartextTraffic` est activé.
> Pour une vraie diffusion, utilise du **`https://`**.

---

## 🛠️ 2. Construire l'APK

### Avec Android Studio (le plus simple)
1. **Android Studio** → *Open* → sélectionne le dossier **`android/`**.
2. Laisse-le synchroniser Gradle (il télécharge les dépendances et crée le wrapper).
3. Menu **Build → Build Bundle(s) / APK(s) → Build APK(s)**.
4. L'APK se trouve dans `app/build/outputs/apk/debug/app-debug.apk`.
5. Copie-le sur ton téléphone et installe-le (autorise les « sources inconnues »).

### En ligne de commande (si le SDK Android est installé)
```bash
cd android
./gradlew assembleDebug        # ou gradlew.bat sur Windows
# → app/build/outputs/apk/debug/app-debug.apk
```

---

## 📱 Fonctionnalités de la coquille Android
- **Plein écran WebView**, orientation portrait.
- **Tirer vers le bas** = recharger (récupère la dernière version en ligne).
- **Bouton retour** = revient à l'écran précédent du jeu (navigation WebView).
- **Synthèse vocale** (les cours lus à voix haute) fonctionne via le moteur TTS du téléphone.
- **Écran hors-ligne** joli si pas de connexion (`assets/offline.html`).
- **localStorage** activé (pour les préférences côté web).

---

## 🧩 Structure du projet
```
android/
├── settings.gradle · build.gradle · gradle.properties
├── app/
│   ├── build.gradle          ← APP_URL à configurer ici
│   └── src/main/
│       ├── AndroidManifest.xml
│       ├── java/com/tamagotchi/edu/MainActivity.kt   ← la WebView
│       ├── res/layout/activity_main.xml
│       ├── res/drawable/ic_launcher.xml               ← icône
│       ├── res/values/ (strings, themes)
│       ├── res/xml/network_security_config.xml
│       └── assets/offline.html
```

## 🔁 Mettre à jour l'app pour les utilisateurs
- **Changer le contenu / les exercices** : tu modifies le **site web** → tout le monde a la MAJ automatiquement à la prochaine ouverture. Rien à faire côté Android.
- **Changer la coquille Android** (icône, nom, comportement WebView) : là il faut rebuild l'APK et le redistribuer.
