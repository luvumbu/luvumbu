# DualCam — Enregistrement simultané avant + arrière (Android)

Application Android native (Kotlin) qui filme **la caméra avant et la caméra arrière en même temps**
et combine les deux flux dans **une seule vidéo MP4** en écran partagé (arrière en haut, avant en bas),
avec le son du micro.

## Comment ça marche

- **Camera2** ouvre les deux caméras.
- Un **compositeur OpenGL ES** (thread dédié) fusionne les deux flux dans une seule image.
- **MediaCodec + MediaMuxer** encodent la vidéo (H.264) et l'audio (AAC) dans un `.mp4`.
- Le même rendu alimente à la fois l'aperçu écran et l'encodeur.

### Deux modes automatiques

1. **Simultané** — si le téléphone supporte matériellement les deux caméras en même temps
   (`CameraManager.getConcurrentCameraIds()`, Android 11+). Les deux flux sont réellement live.
2. **Bascule rapide (fallback)** — si le matériel ne le permet pas, l'app alterne très vite entre
   avant et arrière (~0,8 s chacun). La moitié inactive affiche la dernière image. Fonctionne sur
   quasiment tous les appareils.

Le mode choisi est affiché en haut à gauche de l'écran.

## Compilation

### Avec Android Studio (recommandé)
1. `Fichier > Ouvrir…` et sélectionner ce dossier (`front_back`).
2. Android Studio télécharge Gradle et les dépendances automatiquement.
3. Brancher un téléphone (débogage USB activé) puis **Run ▶**.

### En ligne de commande
Il faut d'abord générer le wrapper Gradle (une seule fois, si Gradle est installé) :
```bash
gradle wrapper --gradle-version 8.7
./gradlew assembleDebug        # génère app/build/outputs/apk/debug/app-debug.apk
./gradlew installDebug         # installe sur l'appareil branché
```

## Où sont les vidéos ?

Dans le dossier privé de l'app :
`Android/data/com.frontback.dualcam/files/Movies/DualCam_AAAAMMJJ_HHMMSS.mp4`

(accessible via un explorateur de fichiers ; le chemin exact est affiché à la fin de l'enregistrement).

## Configuration requise

- **minSdk 24** (Android 7.0). Le vrai mode simultané exige **Android 11+** ET un matériel compatible
  (ex. Pixel récents, certains Samsung / OnePlus). Sinon → mode bascule automatique.
- Autorisations : **Caméra** et **Micro** (demandées au lancement).

## Réglages rapides

Dans `gl/RenderThread.kt` :
- `RECORD_WIDTH` / `RECORD_HEIGHT` — résolution de la vidéo finale (720×1280 par défaut).

Dans `DualCameraController.kt` :
- `SWITCH_INTERVAL_MS` — vitesse de bascule en mode fallback.

Dans `record/VideoRecorder.kt` :
- `bitRate` (défaut 8 Mb/s), `fps`, réglages audio.

## Limitations connues / pistes d'amélioration

- **Orientation / aspect** : l'orientation est gérée via l'orientation capteur (portrait verrouillé).
  Selon le modèle de téléphone, l'image d'une caméra peut apparaître tournée ou légèrement étirée.
  Ajuster `backRotation` / `frontRotation` (dans `MainActivity.startEverything`) et, si besoin,
  ajouter une correction d'aspect ratio dans `RenderThread.drawComposite`.
- L'aperçu remplit chaque moitié (peut déformer un flux 16:9). On peut ajouter un letterbox.
- En mode fallback, la moitié inactive est figée sur la dernière image (comportement attendu).
- Export automatique vers la galerie (MediaStore) non implémenté : la vidéo reste dans le dossier
  privé de l'app. À ajouter si tu veux qu'elle apparaisse dans Google Photos.

## Structure

```
app/src/main/java/com/frontback/dualcam/
  MainActivity.kt              UI, permissions, câblage
  DualCameraController.kt      Camera2 : détection support + simultané/fallback
  gl/
    EglCore.kt                 contexte EGL
    OesTextureProgram.kt       shader OES + matrice d'orientation
    RenderThread.kt            thread GL, compositing, pilotage de l'enregistrement
  record/
    VideoRecorder.kt           MediaCodec (H.264 + AAC) + MediaMuxer
```
