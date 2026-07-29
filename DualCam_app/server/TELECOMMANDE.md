# DualCam — Déclenchement à distance depuis le PC (Télécommande)

Piloter l'enregistrement du téléphone **depuis un ordinateur**, via une page web, avec
confirmation visuelle en temps réel (**REC** rouge + chrono). Conçu comme une caméra de
sécurité : le déclenchement fonctionne **téléphone verrouillé, écran éteint, app fermée**.

> Page PC : **https://luvumbu.com/DualCam/web/remote.php**

---

## 1. Principe

Le PC **dépose un ordre** sur le serveur (`start` / `stop`). Le téléphone **va chercher**
cet ordre régulièrement et l'exécute. Le serveur ne « pousse » rien vers le téléphone :
c'est le téléphone qui interroge, ce qui permet de fonctionner sans connexion permanente.

```
PC (remote.php)  ──POST cmd=start──▶  serveur (dualcam_remote)
téléphone        ──GET poll+rec───▶  serveur  ──renvoie l'ordre──▶  démarre l'enregistrement
téléphone        ──rec=1 à chaque poll──▶  serveur
PC (remote.php)  ──GET status──────▶  serveur  ──recording:true──▶  affiche REC + chrono
```

## 2. Sécurité : opt-in obligatoire

Le téléphone **n'obéit que si** l'option **« 🖥️ Déclenchement à distance »** est cochée
dans l'app (écran **⚡ Modes d'activation**). Tant qu'elle est décochée :

- l'app **n'interroge jamais** le serveur ;
- aucun ordre n'est relevé ni exécuté ;
- personne ne peut piloter le téléphone, même connecté au bon compte.

Tout est en plus **cloisonné par compte Google** : seul le compte connecté peut envoyer
des ordres à ses propres téléphones.

## 3. Ce que voit l'utilisateur sur le PC

- **Pastille d'état** : « Téléphone à l'écoute — vu il y a X s », ou dernier contact.
- **Boutons toujours actifs** : ▶️ Démarrer / ⏹ Arrêter (l'ordre attend si le téléphone
  est momentanément hors ligne).
- **Effet REC** : dès que l'enregistrement est **réellement confirmé** sur l'appareil, un
  **cadre rouge pulsant** couvre toute la page + un badge **🔴 REC** avec un **chrono**
  (`00:07`, `00:08`…). Ce n'est pas « ordre envoyé » mais « la caméra tourne vraiment ».
- L'envoi de l'ordre se fait en **AJAX** (pas de rechargement, l'effet REC reste stable).

## 4. Délais et validité

| Élément | Valeur | Pourquoi |
|---|---|---|
| Cadence d'interrogation du téléphone | **5 s** | Réactivité déclenchement + REC |
| Validité d'un ordre non relevé (TTL) | **1 heure** | Déclenche dès que le téléphone se reconnecte |
| Fenêtre « en ligne / enregistre » | **30 s** | Au-delà, l'état n'est plus considéré fiable |

Un clic « Démarrer » se traduit donc par un démarrage en **~5–10 s**.

## 5. Le chrono

Le serveur fige l'**heure de début réelle** (transition « n'enregistre pas » → « enregistre »)
dans `dualcam_remote.rec_since`. La page calcule le temps écoulé et l'affiche en continu.
Conséquence : **recharger la page** ou l'ouvrir en cours d'enregistrement montre la **vraie
durée**, pas un compteur remis à zéro. Format `MM:SS` (et `HH:MM:SS` au-delà d'une heure).

## 6. Fonctionnement écran verrouillé / éteint

Trois verrous Android devaient être levés (sinon rien ne se déclenche en arrière-plan) :

1. **Type de service caméra** — le service d'écoute (`TriggerService`) détient le type FGS
   `camera` + `microphone`. Sans lui, Android 14 **refuse en silence** d'ouvrir la caméra
   depuis un déclencheur en arrière-plan.
2. **WakeLock partiel** — pris pendant l'écoute **et** pendant l'enregistrement : le
   processeur ne se suspend plus écran éteint, donc l'interrogation continue et l'encodage
   ne se fige pas.
3. **Exemption d'optimisation batterie** — demandée automatiquement à l'activation de
   l'option. Sinon Android endort/tue le service d'écoute et le déclenchement devient
   aléatoire. Sur Samsung/Xiaomi/Huawei/Oppo, mettre en plus l'app en **« Sans restriction »**
   dans les réglages batterie.

> ⚠️ Limite système : dès que la caméra filme, Android impose une **notification** et le
> **point vert** caméra/micro. Aucune app ne peut les masquer (protection vie privée depuis
> Android 12). Le mode « écran discret » de DualCam masque l'écran, pas ces deux éléments.

## 7. Configuration (une seule fois)

**Sur le téléphone**
1. Installer l'APK à jour.
2. Ouvrir l'app, **se connecter avec Google**.
3. ⚙️ → ⚡ Modes d'activation → cocher **« 🖥️ Déclenchement à distance »** (→ « Activé ✅ »).
4. Accepter l'autorisation **caméra** et la fenêtre **« Ne pas optimiser la batterie »**.
5. (Samsung/Xiaomi/…) Réglages → Applications → DualCam → Batterie → **Sans restriction**.

**Sur le PC**
6. Ouvrir `web/remote.php`, se connecter avec **le même compte Google**.
7. Cliquer ▶️ Démarrer → le cadre **REC** rouge + chrono apparaît en ~5–10 s.

## 7bis. Navigation (tuiles d'accès)

Les pages **Mes vidéos** (`dualcam.php`) et **Télécommande** (`remote.php`) partagent de
**grandes tuiles** avec logos bien en avant (emoji ~52–64 px + ombre, titre en gras, effet
de survol qui soulève la tuile) :

- 🎬 **Télécommande** · 🎞️ **Mes vidéos** · 📸 **Galerie PhotoSync**

## 8. Fichiers concernés

**Serveur** (`DualCam/`, répliqué à l'identique dans `DualCam_app/server/`)
- `api/remote.php` — dépôt d'ordre (start/stop), relève (`?poll=1&rec=`), état (`?status=1`), TTL, chrono.
- `web/remote.php` — page PC : boutons Démarrer/Arrêter, pastille, effet REC plein écran, chrono, tuiles, AJAX.
- Table `dualcam_remote` : `user_id, cmd, rec, rec_since, issued_at, polled_at` (créée
  automatiquement, migrations idempotentes).

**Application** (`DualCam_app/app/`)
- `TriggerService.kt` — boucle d'interrogation (poll 5 s), type FGS caméra, WakeLock,
  aiguillage des commandes start/stop.
- `RecordingService.kt` — WakeLock pendant l'enregistrement.
- `net/ApiClient.kt` — `pollRemoteCommand(recording)` envoie l'état + relève l'ordre.
- `ActivationActivity.kt` — case à cocher + demande d'exemption batterie.
- `AndroidManifest.xml` — `TriggerService` type `camera|microphone|specialUse`,
  permission `REQUEST_IGNORE_BATTERY_OPTIMIZATIONS`.

## 9. Enregistrement en ligne

Un enregistrement déclenché à distance suit exactement le même chemin qu'un enregistrement
manuel : gardé sur le téléphone **et** envoyé au serveur (fragments au fil de l'eau, puis
vidéo complète). On le retrouve ensuite dans **🎞️ Mes vidéos** (`web/dualcam.php`).

## 10. Points ouverts / historique

- La partie **« Direct / live »** (visionner le flux quasi temps réel sur le web) a été
  **abandonnée** et retirée du code.
- Les commandes **photo avant/arrière et capture d'écran** ont été **retirées de la
  télécommande** (trop d'aléas de capture selon les appareils). Le code côté app
  (`PhotoService`, capture d'écran via accessibilité) existe encore mais est **dormant** :
  plus aucun bouton ne l'invoque, et le serveur n'accepte que `start`/`stop`. Piste retenue
  si on y revient : ouvrir la caméra **avec un flux** (ImageAnalysis) avant de déclencher —
  une capture `ImageCapture` seule n'ouvre pas la caméra sur beaucoup d'appareils, ce qui
  expliquait « la vidéo marche mais pas la photo ».
- **Sécurité à traiter** : `DualCam/lib/db.config.php` (mot de passe MySQL en clair) est
  suivi par Git — à retirer du dépôt.
