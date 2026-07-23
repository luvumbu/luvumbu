# Card Screen — de la carte 2D au relief 3D

Chaîne complète : **capturer une carte** (OSM/CARTO via Leaflet) → **la reconstruire en 3D**
à partir de ses couleurs, sans rien inventer. Servi par XAMPP, ouvrir via
`http://localhost/luvumbu/3D_simulator/card_screen/`.

Three.js arrive du CDN jsDelivr, épinglé en **0.160.0** (import map). Aucun build, aucun npm.

## Ce que l'outil sait faire

Le parcours normal, de bout en bout :

```
1. index.html      choisir un lieu · un style de fond · un niveau de détail · 📸
2. « Traiter cette capture → »
3. echantillon.html  régler la grille → le relief → plier en globe → arranger → filmer
4. sortir           .glb (3D) · canvas 2D (PNG) · projet (JSON, réouvrable)
```

| Sujet | Ce qui est possible |
|---|---|
| **Capturer** | 6 styles de fond (Voyager, OSM, épuré, sombre, topographique, satellite) · détail ×1/×2/×4 (tuiles d'un zoom plus fin) · relevé des lieux par Overpass |
| **Reconstruire** | classification par teinte en 9 familles · échantillonnage 16→500 · 4 formes de case · 7 répartitions de hauteurs |
| **Plier** | globe de 0 à 100 % (une **courbure**, pas un mélange) · pliage animé de 0,2 à 90 s |
| **Arranger** | zones sélectionnées par couleur (monter, recolorer, fondre, vider, rétablir) · 9 objets posables · retouche cube par cube · ↶ Ctrl+Z |
| **Visiter** | 1ʳᵉ personne à plat **et sur le globe** · soleil pilotable · netteté ×0,75→×2 |
| **Montrer** | mode cinéma (touche **C**) · séquences filmées minutées · échelle réelle et mesure de distances |
| **Sortir** | export **.glb** · canvas 2D · projets enregistrés dans `projets/` |

Chaque point a sa section plus bas, avec **le piège qu'il évite** — c'est la convention de ce
document : on n'y explique pas seulement ce que fait le code, mais pourquoi il le fait ainsi.

## Les pages

| Page | Rôle |
|---|---|
| `index.html` + `app.js` | l'app de **capture** (carte Leaflet, recherche, 📸) |
| `echantillon.html` | **l'outil principal** : image → relief 3D (le cœur du projet) |
| `3d.html` | la carte entière posée en **dalle 3D** texturée |
| `zones.html` | ancienne piste : zones en terrain low-poly (rochers/arbres) |
| `save.php` | reçoit une capture → l'écrit dans `captures/` |
| `save-canvas.php` | reçoit un canvas 2D → l'écrit dans `canvas2d/` |
| `list.php` | liste les captures de `captures/` (JSON, avec `url`) |
| `supprimer.php` | efface une capture **et** son `.json` (deux verrous anti-remontée) |
| `projet.php` | liste / lit / écrit / supprime un **projet** dans `projets/` |

## L'outil principal, fichier par fichier

`echantillon.html` avait fini par tout contenir (structure, styles, 1 500 lignes de script).
C'est découpé :

| Fichier | Rôle |
|---|---|
| `echantillon.html` | **la structure seule** — le menu en sections repliables, les panneaux |
| `echantillon.css` | **toute** la mise en page de l'outil |
| `js/echantillon.js` | le chef d'orchestre : scène, **état** (image, grille, pliage, marcheur), menu |
| `js/couleurs.js` | TSL, les 9 familles, rangement d'un pixel — **sans état** |
| `js/formes.js` | l'enveloppe des cases (carré, arrondi, lisse, boule) — **sans état** |
| `js/pliage.js` | la courbure du globe : `rayonPlein`, `plier`, `orienterPlie` — **sans état** |

La règle du découpage : **ce qui n'a pas d'état sort**, le reste tient dans un seul fichier qui
assume de tenir l'état. Les trois modules extraits sont des fonctions pures — on peut les lire,
les tester ou les réutiliser sans rien savoir de la scène. Répartir l'état entre plusieurs
fichiers aurait obligé à un module « état partagé » que tout le monde importe : le même
couplage, en moins visible.

## Les dossiers de stockage

| Dossier | Contenu | Écrit par |
|---|---|---|
| `captures/` | les captures d'écran : **PNG + JSON** de même nom | `save.php` |
| `canvas2d/` | les vues 2D reconstruites | `save-canvas.php` |
| `projets/` | les arrangements enregistrés (JSON de ~20 ko) | `projet.php` |

Rien n'est stocké dans la racine du projet.

## L'app de capture (`index.html` / `app.js`)

- Carte Leaflet : recherche (Nominatim), clic (géocodage inverse), géolocalisation.
- **Style de la carte** (liste à côté du 📸) — c'est **le fond lui-même qui change**, à l'écran
  comme à la capture :

  | Style | Ce qu'il dessine | Zoom max | 🏷️ |
  |---|---|---|---|
  | **Voyager** (défaut) | routes, zones, eau — épuré | 20 | ✔ |
  | **OSM standard** | bâtiments, chemins, numéros de route — très dense | 19 | ✘ |
  | **Épuré (clair)** · **Sombre** | aplats minimaux, fort contraste | 20 | ✔ |
  | **Topographique** | relief, courbes de niveau, forêts | 17 | ✘ |
  | **Satellite** | la photo aérienne (Esri), noms en surimpression | 19 | ✔ |

  La 3D se reconstruisant **à partir des couleurs**, changer de style change complètement ce
  que `echantillon.html` en tire : c'est le levier le plus fort du projet. Un fond satellite
  donne un relief de teintes naturelles là où Voyager donne des aplats nets.

  Trois points : le bouton **🏷️ se désactive** sur les styles dont le texte est dessiné *dans*
  la tuile (impossible de retirer les noms sans changer le paysage) — plutôt que de mentir ;
  le **zoom redescend** si le nouveau style plafonne plus bas (17 pour le topo), sinon la carte
  reste vide sans rien dire ; et `crossOrigin` est exigé partout, sans quoi la capture en
  canvas est refusée.

- **📸 Capturer** : compose l'image des tuiles chargées, l'envoie à `save.php`.
  Effet **flash appareil photo** instantané au clic + bandeau « Capturé ✔ », puis un lien
  **🏔️ Traiter cette capture →** apparaît en pied de page. Il porte le **nom du fichier**
  (`echantillon.html?capture=…`) : sans lui on ouvrirait la plus récente — presque toujours la
  bonne, mais « presque » suffit à traiter la voisine sans s'en apercevoir. Le lien disparaît
  dès qu'une nouvelle capture démarre, pour ne jamais désigner la précédente ; et si le nom
  demandé n'existe plus, l'outil le dit et ouvre la plus récente.
  La capture reprend **le style affiché** — capturer autre chose que ce qu'on voit n'aurait
  pas de sens.
- **Niveau de détail** (liste à côté du 📸) : *détail écran* · *×2* · *×4*.

  | Choix | Ce qui est capturé | Taille typique | Durée |
  |---|---|---|---|
  | écran | les tuiles **déjà affichées** | 900×504 | instantané |
  | ×2 | les tuiles du zoom **+1**, retéléchargées | 1800×1008 | ~0,6 s |
  | ×4 | les tuiles du zoom **+2** | 3600×2016 | ~3 s |

  **Ce n'est pas un agrandissement, c'est un autre dessin** : au zoom supérieur, CARTO fait
  apparaître des rues, des bâtiments et des contours absents du zoom courant — donc plus de
  matière pour la reconstruction 3D. Les tuiles sont chargées **par paquets de 12** : tout
  demander d'un coup sature la file du navigateur et fait refuser des requêtes.

  ⚠️ Le zoom inscrit dans le nom du fichier est le zoom **réellement capturé** (`z13` pour un
  écran à z12 en ×2). C'est lui qui donne l'échelle à `echantillon.html` — mentir dessus
  fausserait toute la reconstruction. Le relevé Overpass suit le même zoom.
- **Le zoom est inscrit dans le nom** : `card-maps-z12-AAAA-MM-JJ-HH-MM-SS.png`.
  C'est **indispensable** : les pixels seuls ne disent pas l'échelle.
  `mètres_par_pixel = 156543.03 × cos(latitude) / 2^zoom`.
- **Bouton rouge 🏷️ « Masquer les noms »** : bascule le **calque des noms** (CARTO
  `voyager_only_labels`) sur un **paysage constant** (`voyager_nolabels`). Deux couches
  séparées → masquer les noms **ne change pas le paysage**.
- Quand le 🏷️ est actif, au 📸 on **relève les lieux** (Overpass) dans un `.json` de même
  nom : `{ zoom, centre, bornes, elements:[{nom, type, lat, lon, population}] }`.
  Les types de lieux dépendent du zoom (z≤3 : pays seulement ; puis villes, villages…).

## L'outil principal (`echantillon.html`)

### Le principe
Une image → **classée par teinte** → **échantillonnée en cases** → chaque case devient un
**bloc 3D** (relief) ou un **carré 2D** (canvas). Vu de dessus = l'image ; vu de côté = le relief.
**Rien n'est inventé** : chaque case garde la vraie couleur de sa famille.

### La classification (TSL, 9 familles)
Chaque pixel est rangé par sa **teinte** (analyse faite sur de vraies captures) :

| Famille | Signature | Sens |
|---|---|---|
| Eau | teinte bleue (170–260°) | mer, océan |
| Terre / bâti | beige clair (20–45°, très lumineux) | continents *ou* urbain (selon le zoom) |
| Végétation | vert (70–170°) | parcs, forêts |
| Routes principales | jaune (45–70°) | grands axes |
| Routes secondaires | orange/tan (20–45°, sombre) | voies secondaires |
| Rouge | rouge saturé (<20°) | autoroute *ou* frontière forte |
| Frontière (rosé) | rouge peu saturé | traits admin |
| Fond / gris | saturation quasi nulle, très clair | fond/vide (masqué par défaut) |
| **Frontière grise** | filet de gris **dans une case de terre** | traits admin gris (déduit au niveau case) |

Deux familles sont **ambiguës selon le zoom** : le **beige** (terre en région, bâti en ville) et
le **rouge** (frontière en région, autoroute en ville) — réglées à la main par les curseurs.

### Bandes de zoom (lues dans le nom du fichier)
- **z0–z3** : monde (terre / océan).
- **z4–z6** : région (terre + **frontières en relief**).
- **z7+** : ville (bâti, routes, végétation).

### Détails techniques importants
- Les pixels sont **classés une seule fois au chargement** (tableau `fam[]`) → curseurs fluides.
- Échantillonnage sur les **pixels natifs** (pas la moyenne) : les **traits fins** (routes,
  frontières) sont détectés et **priment** dans leur case, sinon ils disparaîtraient à la moyenne.
- `grille.frustumCulled = false` : sinon l'InstancedMesh (boîte englobante d'un seul cube) est
  **éliminé au zoom** et l'écran se vide.
- **Zoom molette progressif maison** (OrbitControls n'amortit pas la molette) : la distance
  glisse vers une cible, image par image.

### Les commandes du menu

Le menu est fait de **sections repliables** (`<details class="bloc">`), dans l'ordre du travail :
**📷 Capture · 🧱 Relief · 📐 Hauteurs · 🌍 Globe** ouvertes (le trajet normal : choisir une image
→ régler la grille → régler le relief → plier), puis **💾 Projet · 🎯 Zones · 🧩 Objets ·
🎬 Séquence · 📏 Échelle · 🎥 Vue · ☀️ Soleil · ✨ Animations · 🎨 Familles · 🖌️ Palette** repliées.

**🧱 Relief** décrit **la grille** (combien de cases, quelle forme) ; **📐 Hauteurs** décrit **de
quoi elles se lèvent** (multiplicateur global + répartition). Deux sujets distincts, deux
onglets — les mélanger rendait la section Relief illisible.
Une quinzaine de réglages à plat étaient devenus illisibles et débordaient de l'écran.

**Chaque titre porte l'état de sa section** (`majEtats()`) — *z14-2026-…*, *220 · carrés*,
*45 %*, *NO · 45°* — sinon il faudrait ouvrir une section pour savoir ce qu'elle contient
comme réglage. Le titre du panneau est **collant** pendant le défilement.

- **Capture** : choix de la capture (`list.php`) ou fichier local.
- **Échantillonnage** (16→500), **Relief global**, **Globe** (voir plus bas).
- **Forme des cases** — *carrés (net)* par défaut, puis **arrondis**, **très lisses**, **boules**.
  L'enveloppe seulement : la case garde sa place, sa taille et sa couleur, aucune donnée
  n'est inventée. Deux détails qui comptent :
  - **Trois finesses par forme, choisies d'après le nombre de cases.** La géométrie est unique
    (InstancedMesh) mais ses sommets sont retraités pour *chaque* case : un galet à 6 segments
    (~300 sommets) × 50 000 cases = 15 M de sommets par image, et l'écran se fige. Au-delà de
    30 000 cases on retombe donc sur la version la plus simple.
  - **Les formes rondes sont élargies** (`ELARGIR`) : un bloc rond inscrit dans sa case perd
    ses coins, les voisines ne se touchent plus et la carte se lit en pointillé. On les élargit
    juste assez pour qu'elles se rejoignent — la case ne bouge pas, sa hauteur non plus.
  - L'arrondi est défini sur le **cube unité**, donc il suit l'échelle : une case haute devient
    une gélule allongée, une case basse un galet plat. C'est inhérent à l'instanciation (une
    seule géométrie pour toutes les cases), et c'est ce qui donne son allure au mode.
- **Netteté** (section Vue) — *économique 0,75× · normale 1× · nette 1,5× · très nette 2×*.
  C'est du **super-échantillonnage** : l'image est calculée plus grande que l'écran, la carte
  graphique la réduit ensuite, et les **bords crénelés** des cases se lissent. Deux garde-fous :
  le coût monte au **carré** (×1,5 = 2,25 fois plus de pixels à calculer), et le rapport final
  est **plafonné à 3** — sans ça, un écran déjà 2× demanderait 36 fois les pixels d'un écran
  normal et se figerait. À ne pas confondre avec l'**Échantillonnage**, qui change la *taille
  des carrés* ; la netteté ne change que la façon de les dessiner.
- **Hauteurs** (section Relief) + **⚙️ Appliquer** — sept façons d'attribuer le relief :

  | Mode | Effet |
  |---|---|
  | *d'origine (par nature)* | les hauteurs de départ — **c'est le retour en arrière**, toujours disponible |
  | *inversées* | **le bas devient le haut** : l'eau se lève, les autoroutes s'aplatissent |
  | *sombre = haut* · *clair = haut* | selon la luminosité de la couleur |
  | *toutes au même niveau* | une dalle plate, pour ne lire que les couleurs |
  | *au hasard* | relief arbitraire |
  | *en escalier* | une hauteur croissante par famille |

  Deux points : l'inversion est une **symétrie autour du milieu de la plage existante**
  (`bas + haut − h`), pas un `max − h` — celui-ci écraserait tout vers zéro dès que les
  hauteurs ne partent pas de zéro, et les écarts entre familles seraient perdus. Et le calcul
  s'applique au **groupe actif** — les familles, ou les couleurs détectées si la palette est
  active : l'ancienne version basculait d'autorité en mode palette, alors que demander
  « inverser » ne doit pas changer la façon dont l'image est classée. Les curseurs du menu
  sont recalés après coup, sinon ils mentiraient sur ce qu'on voit.
- **Rotation auto** de la caméra + **vitesse**.
- **▲ Monter (animer)** + **vitesse** : le relief pousse depuis le sol, **case par case**
  (vague diagonale).
- **Soleil** : *piloter le soleil* → **Direction** (N/E/S/O), **Hauteur**, **ombres** on/off,
  et **tourner tout seul** (+ vitesse) = cycle jour/nuit. Repère jaune. Orienté comme la carte
  (Nord = -Z, Est = +X, Sud = +Z, Ouest = -X).
- **1 ligne par famille** : pastille, afficher/masquer, curseur de hauteur.

### Le globe (curseur 🌍 + bouton)

La carte se **plie** sur une sphère, comme la Terre, puis le **même bouton** (devenu
🗺️ *À plat*) la **remet exactement comme avant**.

**Trois commandes pour le même réglage**, toujours d'accord entre elles :
- le **curseur « 🌍 Globe » du menu, de 0 à 100 %** : on tient la courbure à la main — 0 % la
  dalle, 40 % une calotte bombée, 80 % une boule presque fermée, 100 % le globe. Réponse
  immédiate, sans animation : le pliage suit le doigt. Le toucher **interrompt** une animation.
- le **pliage animé** : **« Animer vers »** (0→100 %) + **« en »** (0,2 → **90 s**), puis le bouton
  **▶**, qui annonce ce qu'il va faire (*« ▶ Plier à 60 % en 3,0 s »*) et propose le retour une
  fois arrivé (*« ▶ Revenir à plat »*) — jamais un bouton mort.
- le **bouton flottant 🌍** : 0 % ⇄ 100 %, **à la durée réglée** dans le menu.

L'animation avance en **avancement normalisé** (`prog` de 0 à 1 en `duree` secondes) : la durée
demandée est donc tenue quel que soit le nombre d'images par seconde. Le pourcentage, lui, suit
une **courbe adoucie** (`e²(3−2e)`) — à vitesse constante, le démarrage et l'arrêt sont secs et
on voit la carte « claquer » en fin de course.

⚠️ **Le pourcentage est une COURBURE, pas un mélange entre deux formes.** C'est le point
délicat : mélanger la position « à plat » et la position « sur la sphère » (le réflexe naturel)
écrase les bords bien avant 100 % — les rangées qui finiront aux pôles s'y effondrent dès
40 %, pendant que le centre est encore plat. Ici, à `globeT`, la carte est posée sur une
**sphère de rayon `R1 / globeT`** : à 1 % le rayon est énorme (la dalle bombe à peine), à
100 % il vaut `R1`. Les angles valant `distance / rayon`, **les longueurs sont conservées** :
une case fait la même taille à 1 %, à 80 % et à 100 %. Rien ne s'écrase.

- **Rien n'est reconstruit** : ce sont les mêmes cases, seule leur **pose** change (`poser()`
  et `plier()`). C'est ce qui rend le retour **exact** : relief, couleurs, familles masquées et
  retouches d'édition sont intacts, puisqu'ils n'ont jamais été touchés.
- **`R1` = le plus petit rayon sur lequel la carte tient sans se recouvrir** :
  `max(largeur / 2π, profondeur / π)` — sa largeur ne peut pas dépasser un tour, sa
  profondeur un demi-tour. Conséquence assumée : **une carte n'est pas une sphère**
  (théorème de Gauss) — seule une image **2:1** la ferme exactement. Plus étroite, il reste un
  **joint ouvert** derrière. On préfère ce joint à des cases comprimées : *on ne déforme pas
  la carte pour boucler*.
- La seule variation de taille est le **resserrement des colonnes** en s'éloignant de la ligne
  centrale (`cos b`) : c'est la géométrie de la sphère elle-même, comme les méridiens qui se
  rapprochent vers les pôles. Ni la hauteur ni la profondeur des cases ne bougent.
- Chaque cube est orienté par une **base explicite** (`makeBasis(est, haut, sud)`), pas par
  `setFromUnitVectors` : celui-ci alignerait bien le +Y sur la normale, mais laisserait le cube
  tourner librement autour — sa largeur ne serait plus dans l'axe est-ouest, et le
  resserrement des colonnes s'appliquerait de travers.
- Le centre de la carte reste **à l'origine** à tous les pourcentages (le centre de la sphère,
  lui, descend à `-Rt`) : c'est ce qui rend le pliage continu — à 0 % on retombe exactement
  sur `(x, hauteur, z)`.
- La caméra : dès **1 %**, `maxPolarAngle` passe à `π` (on peut voir le **pôle sud**) et le **sol
  d'ombre est masqué** — sinon le plan y=0 trancherait la sphère. L'état caméra d'avant
  (distance visée, limites de zoom, near/far, cible) est **mémorisé** et rendu **au retour à 0 %**.
- Le **cadrage suit le pourcentage** : `mesuresGlobe()` calcule analytiquement l'encombrement
  de la calotte au % courant (mesurer la boîte de 50 000 cubes à chaque image coûterait cher),
  **relief compris** — des pics de 3 unités sur un rayon de 5, ça déborde de l'écran si on ne
  les compte pas. On ne téléporte jamais la caméra : elle rejoint la distance visée en
  glissant, comme au zoom molette.
- Le **point regardé descend** avec la calotte (son centre passe sous l'origine) — et **la
  caméra suit du même delta**, sinon la vue bascule à ras du sol. Même correction au retour à
  0 %, sinon un saut direct depuis 80 % laisserait la caméra trop basse.
  « Recadrer » sur le globe recalcule la référence *dalle* puis réapplique le %.
- **Non disponible dès qu'il y a du globe (même 1 %)** : les animations ⬇ (chutes/effondrements
  — elles supposent un « bas » unique, ce qu'une sphère n'a pas). La **montée ▲** et la
  **1ʳᵉ personne**, elles, marchent sur le globe.

### Marcher sur le globe (1ʳᵉ personne)

Le marcheur est un **« porteur »** : un repère posé au sol dont le +Y est le **haut local**, et
la caméra en est l'**enfant**, à hauteur d'yeux. Deux conséquences décisives :

- sur le globe, le porteur **s'incline avec la surface** — on marche la tête vers le ciel local,
  et l'horizon reste horizontal même de l'autre côté de la boule ;
- `PointerLockControls` agit sur la rotation **locale** de la caméra : son lacet tourne donc
  autour du haut local et non de l'axe Y du monde. Sans ce montage, le regard part de travers
  dès qu'on quitte le sommet — c'est *la* raison du porteur.

La position du marcheur est gardée en **coordonnées de la dalle** (`fpX`, `fpZ`), jamais en
coordonnées du monde : le pliage conservant les longueurs, avancer d'un mètre sur la carte,
c'est avancer d'un mètre sur la sphère. Le même `plier()` que les cases place ensuite le
porteur — donc **le marcheur suit le pliage en direct**, on peut plier ou déplier pendant qu'on
marche. Le sol se trouve par un rayon lancé **le long du haut local** (vertical à plat, rayon de
la sphère sur le globe), et son élévation est lissée pour ne pas téléporter la caméra d'une
marche à l'autre. Le marcheur est **borné à la carte** : au-delà du bord il n'y a rien, et sur
le globe c'est le vide.

Pendant ce mode, `cadrerSelonGlobe()` et `sortirEtatGlobe()` **ne touchent pas à la caméra** :
elle appartient au porteur, et sa position est devenue *locale*. Le cadrage d'ensemble est
reconstruit à la sortie par `recadrer()`.

### Projets, annulation, export (section 💾 Projet)

**Un projet retient l'ouvrage, pas l'image.** Il désigne la capture et emporte les réglages,
les hauteurs, les retouches de cases et les objets posés — une vingtaine de kilo-octets dans
`projets/<nom>.json`. Dupliquer 7 Mo de PNG à chaque enregistrement n'aurait servi à rien.

Ce qu'un projet ne stocke **pas**, parce que ça se recalcule : la grille (elle se reconstruit)
et la palette par pixel (`palIdx`). L'ordre de restitution est ce qui compte :

1. **l'image d'abord** — son chargement remet tout à zéro et il est *asynchrone*, d'où le
   rappel passé à `chargerImage()` ; appliquer les réglages avant les ferait écraser ;
2. les réglages, les hauteurs, les retouches ;
3. **une seule** reconstruction ;
4. les objets, puis **le pliage en dernier** — il repose toute la grille, objets compris.

**↶ Annuler (Ctrl+Z)** garde 25 instantanés. On ne mémorise que **l'ouvrage** — retouches,
objets, hauteurs — jamais les réglages de vue : annuler doit défaire ce qu'on a *fait*, pas le
point de vue qu'on a choisi, et une grille de 50 000 cases par instantané rendrait l'historique
impraticable. Un instantané est une **chaîne JSON** : aucune référence partagée, donc rien qui
puisse être modifié après coup. Les curseurs mémorisent **une fois par glissement** (premier
`input`, remis à zéro au `change`), sinon un simple mouvement remplirait l'historique.

**🧊 Exporter en .glb** sort la scène telle qu'elle est à l'écran — pliage compris, puisqu'on
relit les matrices réellement posées. Les cases étant un `InstancedMesh` (50 000 copies d'un
cube, un seul objet pour la carte graphique), le glTF ne sait pas les reprendre telles quelles :
on les **fusionne** en un maillage unique, la couleur passant par les sommets. D'où le plafond
à **30 000 cases** — au-delà le fichier dépasse la centaine de mégaoctets ; on refuse en disant
quoi baisser, plutôt que de faire semblant. Vérifié par aller-retour : le fichier se recharge
dans une visionneuse indépendante, objets et couleurs compris.

### Séquences filmées (section 🎬 Séquence)

Des étapes minutées, jouées à la suite : *plier vers X %*, *faire monter le relief*, *tourner
d'un tour*, *course du soleil*, *attendre*. Avec « masquer l'interface », la lecture bascule en
mode cinéma et le rend à la fin — de quoi filmer une démonstration d'un seul geste.

Chaque étape ne fait que **déclencher ce qui existe déjà** puis attendre sa durée : une séquence
est un chef d'orchestre, pas un second moteur d'animation. Le compte à rebours se fait sur le
**temps réel** (`dt`), donc la durée annoncée est tenue quel que soit le nombre d'images par
seconde.

### Échelle et mesures (section 📏 Échelle)

    mètres_par_pixel = 156543,03 × cos(latitude) / 2^zoom

Le **zoom** vient du nom du fichier, la **latitude** du `.json` de la capture — elle compte : à
l'équateur un pixel couvre le double de ce qu'il couvre à 60°. La section annonce la largeur
réelle de la carte, ce que vaut **une case** et ce que vaut un pixel d'origine. Sans zoom
connu, on le dit au lieu d'inventer un chiffre.

**📏 Mesurer une distance** : deux clics donnent la distance à vol d'oiseau, et une ligne relie
les deux points. La mesure se fait sur les **coordonnées de la carte**, pas dans le monde 3D :
pliée en globe, la distance entre deux lieux ne change pas.

### Sélection de zones (section 🎯 Zones)

**🎯 Sélectionner une zone** ouvre le mode : **un clic** et toute la zone de même couleur se
sélectionne (teintée d'orange, mêlé à sa vraie couleur — il faut continuer à reconnaître ce
qu'on manipule). Deux réglages décident de ce qu'« une zone » veut dire :

| Réglage | Choix |
|---|---|
| **Reconnaître** | la même **famille** (eau, végétation…) · la même **couleur** ± tolérance |
| **Étendue** | la **tache touchée**, de proche en proche · **tout ce qui correspond** sur la carte |

La propagation de proche en proche se fait **sur une pile**, pas en récursif : une carte à
250 000 cases ferait exploser la pile d'appels.

⚠️ **La sélection ne teinte pas les cases — elle en souligne le contour.** Première version :
on mêlait de l'orange à la couleur de chaque case sélectionnée. Résultat, ce qu'on voyait
n'était **pas le rendu final** — impossible de juger une couleur qu'on venait d'appliquer, et
la zone semblait « changer de couleur » à la désélection. Maintenant un marqueur orange,
posé **au-dessus** des cases, ne couvre que les **cases du bord** (celles qui ont un voisin hors
zone) : l'étendue reste lisible, l'intérieur garde sa couleur exacte. L'interrupteur
**« souligner le contour »** l'éteint sans perdre la sélection.

Le panneau de droite manipule la zone d'un bloc :

- **Hauteur** — un curseur qui donne la même hauteur à toute la zone ;
- **▲ Monter / ▼ Descendre** — ×1,25 et ÷1,25, qui **gardent le relief interne** (une valeur
  unique aplatirait la zone) ;
- **Couleur** — recolore toute la zone ;
- **🔗 Associer à cette famille** — la zone prend la **couleur et la hauteur** d'une autre
  famille : c'est la façon de fondre deux couleurs en une ;
- **🌳 Planter dans la zone** — remplit la zone d'objets (arbres, sapins, rochers…) : la zone
  dit **où**, la **densité** (2→100 %) dit **combien**. Chaque objet est décalé au hasard dans sa
  case et sa taille varie de ±25 % — sans ça, une forêt plantée d'un clic ressemble à un
  damier, pas à une forêt ;
- **remplacer** (coché par défaut) — replanter **repart d'une zone nette**. Sans ça, bouger la
  densité et recliquer empile les couches et le résultat ne correspond plus au réglage affiché.
  Décoché, on superpose : deux passes de types différents donnent un mélange crédible ;
- **🗑 Retirer** — enlève les objets **de la zone** (le pendant de « planter » : une zone se
  gère dans les deux sens ou elle ne se gère pas) ;
- **🗑 Vider les cases** — la zone devient un trou. Les objets qui s'y trouvaient partent avec,
  sinon ils flotteraient au-dessus du vide ;
- **↺ Rétablir** — jette **toutes** les retouches de la zone (hauteur, couleur, association,
  vidage) : les cases reprennent ce que l'image dit d'elles ;
- **Désélectionner**.

Un vidage **ne lâche pas la sélection** : c'est elle qui rend « ↺ Rétablir » possible. Le
rétablissement travaille sur les **clés** de la sélection, pas sur les cases — une case vidée
n'existe plus dans la grille, mais sa clé est toujours là. C'est ce qui rend l'opération
réversible.

Les objets plantés sont des objets ordinaires : on les retire un par un au clic (mode 🧩) ou
tous d'un coup. Un **modèle par type** est fabriqué une seule fois puis **cloné** — `clone()`
partage géométries et matériaux, donc 300 arbres ne créent pas 900 objets GPU distincts.
Corollaire : on ne libère jamais ces géométries en retirant un objet, elles appartiennent au
modèle. Un **plafond à 900 objets** protège l'affichage, et le message le dit quand il est atteint.

Tout passe par **`overrides`**, la même table que l'édition d'un cube : rien de nouveau à
mémoriser, les retouches s'appliquent par-dessus l'auto, survivent aux reconstructions, et le
**pliage du globe les emporte** sans rien savoir d'elles. La sélection est en revanche remise à
zéro si l'**échantillonnage** change ou si on charge une autre image — les cases ne sont plus
les mêmes.

Trois modes lisent le clic sur la scène — **zone**, **édition**, **pose d'objets** : activer
l'un coupe les autres.

### Objets posés sur la carte (section 🧩 Objets)

**➕ Poser des objets** ouvre le mode : **clic sur une case** y pose l'objet choisi, **clic sur un
objet** le retire. Neuf formes en géométries simples, sans fichier à charger (fidèle au projet :
aucun build, aucun asset) :

| Décor | Êtres vivants |
|---|---|
| 🌳 arbre · 🌲 sapin · 🏠 maison · 🗼 tour · 🪨 rocher · 📍 repère | 🧍 personne · 🐕 chien · 🐈 chat |

Les êtres vivants ont des **proportions relatives à l'arbre** (≈ 1 de haut) : une personne fait
la moitié d'un arbre, un chien le quart, un chat le sixième. Sans ça, il faudrait rerégler la
taille à chaque changement de type — et un chat de la taille d'un sapin n'est plus un repère
d'échelle, c'est un décor. Chacun garde sa silhouette lisible de loin : le chien a un museau et
la queue basse, le chat des oreilles pointues et **la queue dressée**.
Réglages : type et taille ; « 🗑 Tout effacer » vide la carte.

**La taille se change après coup** : le curseur vaut pour les prochains objets **et retaille en
direct ceux déjà posés**. Il applique le **rapport** de sa variation, pas sa valeur — chaque
objet garde donc sa taille propre (celle tirée au hasard à la plantation, ou celle d'un objet
posé plus gros exprès) et tout le monde grandit ou rétrécit ensemble. Les boutons **＋ / －** du
panneau de zone, eux, ne retaillent que **les objets de la zone** : une forêt de grands arbres
ici, des buissons là.

Ce sont les **seules choses inventées** de la page. Elles se posent donc **par-dessus** et
n'entrent dans aucun calcul : ni la reconstruction, ni le canvas 2D, ni le sol du marcheur.

⚠️ **Charger une autre capture efface les objets.** Leur place étant relative, rien ne les
empêcherait de survivre au changement — ils se retrouveraient plantés sur une autre ville.
(Un **changement d'échantillonnage**, lui, les garde : c'est la même carte, seulement
redécoupée.)

Le point technique : chaque objet retient sa place en **coordonnées relatives** (`u`, `v` dans
0→1), pas en numéro de case ni en position monde. C'est ce qui le rend solide — changer
l'**échantillonnage** redécoupe les cases (un objet rangé par numéro de case sauterait
ailleurs), et **plier le globe** déplace tout le monde. Avec (`u`, `v`) on retrouve la case sous
l'objet, sa hauteur, puis on le pose avec **le même `plier()`** que les cases et le marcheur :
il reste debout sur la surface, à sa place, à tous les pourcentages.

Le mode **pose**, le mode **édition** et le mode **zone** lisent tous le clic sur la scène :
activer l'un coupe les autres.

### Mode cinéma (🎬 ou touche **C**)

Pour **filmer l'écran** : menu, boutons flottants, panneau d'édition et aperçu 2D disparaissent
— il ne reste que la scène. Trois choix qui comptent :

- on masque par une **classe sur `<body>`**, pas en touchant chaque panneau : tout reste dans le
  DOM, donc la démo continue de lire ses réglages et **une animation lancée avant se poursuit
  pendant le tournage** (on peut lancer un pliage de 30 s puis passer en cinéma) ;
- le retour se fait à la **touche C** — le bouton, lui, a disparu avec le reste. La touche est
  ignorée quand le curseur est dans un réglage, sinon taper dans un champ masquerait l'écran ;
- un **message furtif** rappelle la touche puis s'efface en 2 s, pour ne pas polluer la vidéo.

Le voile de la 1ʳᵉ personne reste visible : sans lui, impossible d'entrer dans le mode.

### Barre flottante (haut-droite)

**Règle de rangement : tout ce qui se pilote au clic dans la scène est dans la barre ; les
réglages fins de chaque mode restent dans le menu.** Sans cette règle, un mode ajouté dans une
section repliée du menu est introuvable — c'est arrivé deux fois.

`🖼️ 2D · ✏️ Cube · 🎯 Zones · 🧩 Objets · 🌍 Globe · 🚶 Marcher · 🎬 Cinéma`

- **🖼️ 2D** : reconstruit la vue **2D à plat** (mêmes cases, familles masquées en trous),
  l'affiche en aperçu, la **télécharge** et l'**enregistre** dans `canvas2d/`.
- **✏️ Cube** : clic sur un cube → panneau (**hauteur**, **couleur**, **supprimer**). Les modifs
  sont des **overrides** par case `(i,j)`, appliqués par-dessus l'auto → ils survivent aux
  reconstructions (mais sont remis à zéro si l'**échantillonnage** change ou si on charge une
  autre image, car les repères de case changent).
- **🎯 Zones** · **🧩 Objets** · **🌍 Globe** · **🚶 Marcher** · **🎬 Cinéma** : voir plus haut.

Les boutons de la barre **relaient** ceux du menu (`.click()` sur le bouton du menu) : le mode
ne change d'état qu'à un seul endroit, jamais deux copies à garder d'accord. Ouvrir un mode
**déplie sa section** du menu, pour que ses réglages soient sous la main.

Les trois modes qui lisent le clic dans la scène — **cube**, **zone**, **objet** — sont arbitrés
par une seule fonction, `modeExclusif()` : activer l'un coupe les autres. Aucun des trois ne
connaît les deux autres. (La première version arbitrait deux à deux : ajouter le troisième mode
avait laissé passer la paire *objet → zone*.)

## Principe directeur du projet

**On ne devine pas, on ne réinvente pas :** tout part de l'image réelle et de son zoom.
(Seule exception assumée : les **objets posés à la main** — ils s'ajoutent par-dessus et
n'entrent dans aucun calcul.)
- Le **zoom** (nom du fichier) donne l'échelle et choisit les règles.
- Les **couleurs** (teinte) donnent la nature de chaque zone.
- Les **noms** (Overpass → `.json`) sont mis de côté, pour un repérage 3D ultérieur.

## État du projet

**Versionné** depuis le 23/07/2026 sur la branche **`card-screen-3d`** (rien n'est poussé).
Sont hors du dépôt, via `.gitignore` : `captures/`, `canvas2d/`, `projets/` — du contenu
produit par l'outil, régénérable, et lourd (une capture ×4 pèse 7 Mo à elle seule).

**Vérification.** Chaque fonction a été essayée dans un navigateur réel (Chromium piloté),
pas seulement relue : parcours complets, mesures dans la page (dimensions, débordements,
compteurs), et aller-retour pour l'export `.glb` — le fichier est rechargé dans une
visionneuse indépendante pour prouver qu'il s'ouvre ailleurs.

**Points connus, à traiter un jour :**

| Point | Détail |
|---|---|
| `.json` orphelins | quelques métadonnées dans `captures/` n'ont plus leur PNG (supprimé à la main). Sans effet, mais elles traînent — le 🗑 de l'outil, lui, efface bien les deux |
| `exemples/`, `simboles/` | dossiers hérités, référencés nulle part (1,6 Mo) |
| `3d.html`, `zones.html` | deux pistes antérieures, autonomes : elles n'ont ni les styles de fond, ni le niveau de détail |
| `js/echantillon.js` | ~2 100 lignes — il assume de tenir l'état ; les trois modules sans état sont déjà sortis |
| matériau de la grille | recréé à chaque reconstruction. Ce n'est **pas** une fuite (Three range ses propriétés en `WeakMap`), juste une allocation évitable |

## Pistes ouvertes
- Afficher les **noms du `.json`** en étiquettes 3D au bon endroit.
- **Pinceau** multi-cubes, **ajouter** un cube sur un trou, changer la **famille** d'un cube.
- Teinte de lumière selon la hauteur du soleil (aube/midi/crépuscule).
- **Refaire** (Ctrl+Y) : l'annulation empile déjà des instantanés, le retour serait symétrique.
- Rejouer une **séquence enregistrée dans un projet** (elle n'y est pas encore).
