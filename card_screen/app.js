// Card Maps — carte gratuite avec Leaflet + OpenStreetMap (aucune clé API requise).

// --- Initialisation de la carte ---
const map = L.map("map").setView([48.8566, 2.3522], 12); // Paris par défaut

// ============================================================
//  STYLES DE CARTE — c'est le fond lui-même qui change, pas seulement sa finesse.
//
//  Chaque style dessine AUTRE CHOSE : Voyager résume, OSM standard montre les bâtiments et
//  les chemins, le topographique ajoute le relief, le satellite remplace tout par la photo.
//  Comme la 3D se reconstruit à partir des COULEURS, changer de style change complètement
//  ce que `echantillon.html` en tire — c'est le levier le plus fort du projet.
//
//  `noms` : l'adresse du calque de noms SÉPARÉ, quand il existe. C'est lui qui permet au
//  bouton 🏷️ de retirer les étiquettes sans toucher au paysage. Les styles où le texte est
//  dessiné DANS la tuile (OSM, topographique) ne peuvent pas l'offrir — le bouton se
//  désactive alors, plutôt que de mentir.
//  `crossOrigin` est indispensable partout : sans lui, la capture en canvas est refusée.
// ============================================================
const STYLES = {
  voyager: {
    nom: "Voyager (défaut)", max: 20, sub: "abcd",
    url: "https://{s}.basemaps.cartocdn.com/rastertiles/voyager_nolabels/{z}/{x}/{y}.png",
    noms: "https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}.png",
    credit: "&copy; OpenStreetMap &copy; CARTO",
  },
  osm: {
    nom: "OSM standard (très détaillé)", max: 19, sub: "abc",
    url: "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
    noms: null,
    credit: "&copy; OpenStreetMap",
  },
  positron: {
    nom: "Épuré (clair)", max: 20, sub: "abcd",
    url: "https://{s}.basemaps.cartocdn.com/rastertiles/light_nolabels/{z}/{x}/{y}.png",
    noms: "https://{s}.basemaps.cartocdn.com/rastertiles/light_only_labels/{z}/{x}/{y}.png",
    credit: "&copy; OpenStreetMap &copy; CARTO",
  },
  dark: {
    nom: "Sombre", max: 20, sub: "abcd",
    url: "https://{s}.basemaps.cartocdn.com/rastertiles/dark_nolabels/{z}/{x}/{y}.png",
    noms: "https://{s}.basemaps.cartocdn.com/rastertiles/dark_only_labels/{z}/{x}/{y}.png",
    credit: "&copy; OpenStreetMap &copy; CARTO",
  },
  topo: {
    nom: "Topographique (relief)", max: 17, sub: "abc",
    url: "https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png",
    noms: null,
    credit: "&copy; OpenStreetMap &copy; OpenTopoMap",
  },
  satellite: {
    nom: "Satellite (photo)", max: 19, sub: null,
    url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
    noms: "https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}.png",
    credit: "&copy; Esri, Maxar, Earthstar Geographics",
  },
};

let styleCourant = "voyager";
let fondPaysage = null, coucheNoms = null;
let tilesLoading = false;

function suivreChargement(couche) {
  couche.on("loading", () => { tilesLoading = true; });
  couche.on("load", () => { tilesLoading = false; });
  return couche;
}

// Remplace le fond (et son calque de noms) par le style demandé.
function appliquerStyle(cle) {
  const s = STYLES[cle] || STYLES.voyager;
  styleCourant = cle;
  if (fondPaysage) map.removeLayer(fondPaysage);
  if (coucheNoms) { map.removeLayer(coucheNoms); coucheNoms = null; }

  fondPaysage = suivreChargement(L.tileLayer(s.url, {
    maxZoom: s.max, crossOrigin: "anonymous",
    subdomains: s.sub || "abc", attribution: s.credit,
  })).addTo(map);

  if (s.noms) {
    coucheNoms = suivreChargement(L.tileLayer(s.noms, {
      maxZoom: s.max, crossOrigin: "anonymous",
      subdomains: "abcd", pane: "overlayPane",
    }));
  }
  // Le zoom courant peut dépasser le maximum du nouveau style (17 pour le topo) : on
  // redescend, sinon la carte reste vide sans rien dire.
  if (map.getZoom() > s.max) map.setZoom(s.max);
  majBoutonNoms();
  appliquerNoms();
}

let marker = null;

const statusEl = document.getElementById("status");
const inputEl = document.getElementById("search-input");

function setStatus(message, isError = false) {
  statusEl.textContent = message;
  statusEl.classList.toggle("status--error", isError);
}

// Place (ou déplace) le marqueur et centre la carte.
function placeMarker(lat, lon, label) {
  if (marker) {
    marker.setLatLng([lat, lon]);
  } else {
    marker = L.marker([lat, lon]).addTo(map);
  }
  if (label) marker.bindPopup(label).openPopup();
  map.setView([lat, lon], 14);
}

// --- Recherche d'adresse via Nominatim (géocodage gratuit OpenStreetMap) ---
async function searchPlace(query) {
  setStatus(`Recherche de « ${query} »…`);
  const url =
    "https://nominatim.openstreetmap.org/search?format=json&limit=1&q=" +
    encodeURIComponent(query);

  try {
    const res = await fetch(url, { headers: { "Accept-Language": "fr" } });
    const results = await res.json();

    if (!results.length) {
      setStatus(`Aucun résultat pour « ${query} ».`, true);
      return;
    }

    const { lat, lon, display_name } = results[0];
    placeMarker(parseFloat(lat), parseFloat(lon), display_name);
    setStatus(display_name);
  } catch (err) {
    setStatus("Erreur réseau pendant la recherche.", true);
  }
}

// --- Géocodage inverse : récupérer une adresse à partir d'un clic ---
async function reverseGeocode(lat, lon) {
  const url =
    "https://nominatim.openstreetmap.org/reverse?format=json&lat=" +
    lat + "&lon=" + lon;
  try {
    const res = await fetch(url, { headers: { "Accept-Language": "fr" } });
    const data = await res.json();
    return data.display_name || null;
  } catch {
    return null;
  }
}

// --- Événements ---

// Recherche au submit du formulaire.
document.getElementById("search-form").addEventListener("submit", (e) => {
  e.preventDefault();
  const query = inputEl.value.trim();
  if (query) searchPlace(query);
});

// Clic sur la carte → marqueur + adresse.
map.on("click", async (e) => {
  const { lat, lng } = e.latlng;
  placeMarker(lat, lng, "Chargement de l'adresse…");
  setStatus(`Point sélectionné : ${lat.toFixed(5)}, ${lng.toFixed(5)}`);
  const address = await reverseGeocode(lat, lng);
  if (address) {
    marker.setPopupContent(address);
    setStatus(address);
  }
});

// Flash "appareil photo" : un éclair blanc INSTANTANÉ sur la carte, au moment où on appuie.
// (N'affecte pas l'image : captureMapCanvas lit les tuiles, pas l'écran.)
function flashAppareilPhoto() {
  const flash = document.getElementById("flash");
  if (!flash) return;
  flash.classList.remove("flash--show");
  void flash.offsetWidth; // force le navigateur à réinitialiser l'animation
  flash.classList.add("flash--show");
}

// Bandeau "Capturé ✔" affiché une fois l'enregistrement confirmé.
function playCaptureAnimation() {
  const toast = document.getElementById("toast");
  if (!toast) return;
  toast.classList.remove("toast--show");
  void toast.offsetWidth;
  toast.classList.add("toast--show");
}

// Attend que toutes les tuiles visibles soient chargées (avec sécurité de délai).
function waitForTiles() {
  return new Promise((resolve) => {
    const debut = Date.now();
    const verifier = () => {
      // Résolu dès que plus rien ne charge, ou au bout de 3 s (filet de sécurité).
      if (!tilesLoading || Date.now() - debut > 3000) resolve();
      else setTimeout(verifier, 100);
    };
    verifier();
  });
}

// Dessine un marqueur (pin vectoriel) à un point pixel donné.
function drawPin(ctx, x, y) {
  const r = 8, h = 24;
  ctx.save();
  ctx.fillStyle = "#e11d48";
  ctx.strokeStyle = "#ffffff";
  ctx.lineWidth = 2;
  // pointe
  ctx.beginPath();
  ctx.moveTo(x - 6, y - h + 4);
  ctx.lineTo(x, y);
  ctx.lineTo(x + 6, y - h + 4);
  ctx.closePath();
  ctx.fill();
  // tête
  ctx.beginPath();
  ctx.arc(x, y - h, r, 0, Math.PI * 2);
  ctx.fill();
  ctx.stroke();
  // point central
  ctx.fillStyle = "#ffffff";
  ctx.beginPath();
  ctx.arc(x, y - h, 3, 0, Math.PI * 2);
  ctx.fill();
  ctx.restore();
}

// Compose l'image de la carte à partir des tuiles déjà chargées (fiable).
function captureMapCanvas() {
  const mapEl = document.getElementById("map");
  const rect = mapEl.getBoundingClientRect();
  const scale = window.devicePixelRatio || 1;

  const canvas = document.createElement("canvas");
  canvas.width = Math.round(rect.width * scale);
  canvas.height = Math.round(rect.height * scale);
  const ctx = canvas.getContext("2d");
  ctx.scale(scale, scale);

  // Fond neutre au cas où une tuile manquerait.
  ctx.fillStyle = "#e5e7eb";
  ctx.fillRect(0, 0, rect.width, rect.height);

  // Dessine chaque tuile à sa position réelle (relative au conteneur carte).
  const tiles = mapEl.querySelectorAll(".leaflet-tile-pane img");
  tiles.forEach((img) => {
    if (!img.complete || !img.naturalWidth) return;
    if (parseFloat(img.style.opacity || "1") === 0) return;
    const r = img.getBoundingClientRect();
    const x = r.left - rect.left;
    const y = r.top - rect.top;
    try {
      ctx.drawImage(img, x, y, r.width, r.height);
    } catch (e) {
      /* tuile non dessinable : ignorée */
    }
  });

  // Dessine le marqueur par-dessus, au bon pixel.
  if (marker) {
    const p = map.latLngToContainerPoint(marker.getLatLng());
    drawPin(ctx, p.x, p.y);
  }

  return canvas;
}

// ============================================================
//  CAPTURE DÉTAILLÉE — ×2 et ×4
//
//  La capture normale recopie les tuiles DÉJÀ à l'écran : elle ne peut donc pas contenir
//  plus que ce que l'écran montre. Ici on va chercher les tuiles d'un (ou deux) zoom(s)
//  PLUS FIN(S) pour la même zone : ce n'est pas un agrandissement, c'est un autre dessin —
//  CARTO y fait apparaître des rues, des bâtiments et des contours absents du zoom courant.
//
//  Conséquence assumée : le zoom inscrit dans le nom du fichier est le zoom RÉELLEMENT
//  capturé (z+1 ou z+2). C'est lui qui donne l'échelle à `echantillon.html` — mentir
//  dessus fausserait toute la reconstruction.
// ============================================================
const TUILE = 256;

// Le sous-domaine tourne pour paralléliser ; les adresses sans {s} (satellite) l'ignorent,
// et l'ordre {y}/{x} d'Esri marche aussi puisqu'on remplace par nom, pas par position.
function urlTuile(modele, z, x, y, sub) {
  const d = sub || "abc";
  return modele
    .replace("{s}", d[(x + y) % d.length])
    .replace("{z}", z).replace("{x}", x).replace("{y}", y);
}

// Charge une image sans jamais rejeter : une tuile manquante laisse un trou, elle
// n'annule pas la capture.
function chargerTuile(url) {
  return new Promise((resolve) => {
    const img = new Image();
    img.crossOrigin = "anonymous";
    img.onload = () => resolve(img);
    img.onerror = () => resolve(null);
    img.src = url;
  });
}

// Compose la vue courante à partir des tuiles du zoom `z`. Les tuiles sont chargées par
// paquets : tout demander d'un coup fait tomber le navigateur dans sa file d'attente et
// certains serveurs refusent la rafale.
async function composerAuZoom(z, avecNoms, surAvancement) {
  const b = map.getBounds();
  const hg = map.project(b.getNorthWest(), z);
  const bd = map.project(b.getSouthEast(), z);
  const largeur = Math.round(bd.x - hg.x), hauteur = Math.round(bd.y - hg.y);

  const canvas = document.createElement("canvas");
  canvas.width = largeur; canvas.height = hauteur;
  const ctx = canvas.getContext("2d");
  ctx.fillStyle = "#e5e7eb";
  ctx.fillRect(0, 0, largeur, hauteur);

  const nMax = Math.pow(2, z);                     // nombre de tuiles par côté à ce zoom
  const x0 = Math.floor(hg.x / TUILE), x1 = Math.floor((bd.x - 1) / TUILE);
  const y0 = Math.floor(hg.y / TUILE), y1 = Math.floor((bd.y - 1) / TUILE);

  // On recompose avec le style AFFICHÉ : capturer autre chose que ce qu'on voit n'aurait
  // aucun sens. Le calque de noms ne s'ajoute que si le style en a un de séparé.
  const st = STYLES[styleCourant];
  const modeles = [{ url: st.url, sub: st.sub }];
  if (avecNoms && st.noms) modeles.push({ url: st.noms, sub: "abcd" });

  const travaux = [];
  for (const modele of modeles) {
    for (let ty = y0; ty <= y1; ty++) {
      if (ty < 0 || ty >= nMax) continue;           // hors des pôles : rien à charger
      for (let tx = x0; tx <= x1; tx++) {
        const txm = ((tx % nMax) + nMax) % nMax;    // la longitude boucle, pas la latitude
        travaux.push({ url: modele.url, sub: modele.sub, tx, ty, txm });
      }
    }
  }

  let faits = 0;
  const PAQUET = 12;
  for (let i = 0; i < travaux.length; i += PAQUET) {
    const lot = travaux.slice(i, i + PAQUET);
    const images = await Promise.all(lot.map((t) => chargerTuile(urlTuile(t.url, z, t.txm, t.ty, t.sub))));
    images.forEach((img, k) => {
      if (!img) return;
      const t = lot[k];
      ctx.drawImage(img, t.tx * TUILE - hg.x, t.ty * TUILE - hg.y, TUILE, TUILE);
    });
    faits += lot.length;
    if (surAvancement) surAvancement(faits, travaux.length);
  }

  // Marqueur : à la même place, et grossi du même facteur que l'image (sinon un pin de
  // 24 px se perd dans une image quatre fois plus grande).
  if (marker) {
    const p = map.project(marker.getLatLng(), z);
    const k = largeur / map.getSize().x;          // 2 pour ×2, 4 pour ×4
    ctx.save();
    ctx.scale(k, k);
    drawPin(ctx, (p.x - hg.x) / k, (p.y - hg.y) / k);
    ctx.restore();
  }
  return canvas;
}

// --- Relevé des noms de lieux (villes, pays…) via Overpass (données OSM réelles) ---

// Les types de lieux à relever DÉPENDENT du zoom : à z0 (le monde), on ne veut que les pays,
// sinon la requête ramènerait des dizaines de milliers de villages. Plus on zoome, plus on
// descend vers le village et le quartier.
function typesLieuxPourZoom(zoom) {
  if (zoom <= 3) return ["country"];
  if (zoom <= 6) return ["country", "state", "city"];
  if (zoom <= 9) return ["city", "town"];
  if (zoom <= 12) return ["city", "town", "village", "suburb"];
  return ["town", "village", "suburb", "neighbourhood", "hamlet"];
}

// Interroge Overpass pour les lieux nommés dans le rectangle visible.
// Renvoie [] en cas d'échec : le relevé ne doit jamais empêcher l'enregistrement de l'image.
async function releverLieux(bounds, zoom) {
  // Web Mercator plafonne aux pôles ; on borne aussi la longitude (le monde peut "boucler" à z0).
  const s = Math.max(-90, bounds.getSouth()), n = Math.min(90, bounds.getNorth());
  const w = Math.max(-180, bounds.getWest()), e = Math.min(180, bounds.getEast());
  const filtre = typesLieuxPourZoom(zoom).join("|");
  const requete =
    `[out:json][timeout:25];` +
    `(node["place"~"^(${filtre})$"]["name"](${s},${w},${n},${e}););` +
    `out body 1000;`;

  try {
    // Délai maximal : si Overpass est lent/saturé, on abandonne le relevé au lieu de bloquer le 📸.
    const ctrl = new AbortController();
    const minuteur = setTimeout(() => ctrl.abort(), 12000);
    const res = await fetch("https://overpass-api.de/api/interpreter", {
      method: "POST",
      body: "data=" + encodeURIComponent(requete),
      signal: ctrl.signal,
    });
    clearTimeout(minuteur);
    const data = await res.json();
    return (data.elements || [])
      .filter((el) => el.tags && el.tags.name)
      .map((el) => ({
        nom: el.tags.name,
        type: el.tags.place,          // country / city / town / village…
        lat: el.lat,
        lon: el.lon,
        population: el.tags.population ? parseInt(el.tags.population, 10) : null,
      }));
  } catch (err) {
    return [];
  }
}

// --- Bouton rouge 🏷️ "Masquer les noms" : retire/remet UNIQUEMENT le calque des noms ---
// Actif   → calque des noms RETIRÉ (le paysage reste identique ; les noms sont relevés à part au 📸).
// Inactif → calque des noms visible.
const releverBtn = document.getElementById("relever");
function nomsRetires() { return !!releverBtn && releverBtn.classList.contains("actif"); }
function appliquerNoms() {
  // On ne touche QU'au calque des noms — le paysage (fondPaysage) ne bouge jamais.
  if (!coucheNoms) return;                        // style sans calque de noms séparé
  if (nomsRetires()) {
    if (map.hasLayer(coucheNoms)) map.removeLayer(coucheNoms);
  } else {
    if (!map.hasLayer(coucheNoms)) coucheNoms.addTo(map);
  }
}
// Le bouton 🏷️ n'a de sens que si les noms sont sur un calque à part : sur OSM standard ou
// le topographique, ils sont dessinés DANS la tuile, impossible de les retirer.
function majBoutonNoms() {
  if (!releverBtn) return;
  const possible = !!STYLES[styleCourant].noms;
  // L'apparence du désactivé (voile + curseur barré) est dans ui.css, pour tous les
  // boutons du projet : ici on ne dit plus QUE l'état.
  releverBtn.disabled = !possible;
  releverBtn.title = possible
    ? "Masquer les noms — le paysage reste identique (les noms sont relevés à part au 📸)"
    : "Ce style dessine les noms dans la tuile : impossible de les retirer sans changer le paysage";
}
if (releverBtn) {
  releverBtn.addEventListener("click", () => {
    releverBtn.classList.toggle("actif");
    releverBtn.setAttribute("aria-pressed", nomsRetires() ? "true" : "false");
    appliquerNoms();
  });
}
appliquerStyle(styleCourant);   // pose le fond de départ (et son calque de noms)

const selStyle = document.getElementById("style");
if (selStyle) {
  for (const [cle, s2] of Object.entries(STYLES)) {
    const o = document.createElement("option");
    o.value = cle; o.textContent = s2.nom;
    selStyle.appendChild(o);
  }
  selStyle.value = styleCourant;
  selStyle.addEventListener("change", (e) => {
    appliquerStyle(e.target.value);
    setStatus("Style : " + STYLES[styleCourant].nom + " · zoom max z" + STYLES[styleCourant].max);
  });
}

// Le niveau de détail ne change RIEN à l'écran — il ne concerne que l'image enregistrée.
// Sans ce message, on croit que le réglage ne marche pas : on le dit au moment du choix.
const selDetail = document.getElementById("detail");
if (selDetail) {
  selDetail.addEventListener("change", () => {
    const n = parseInt(selDetail.value, 10);
    const z = Math.min(STYLES[styleCourant].max, Math.round(map.getZoom()) + n);
    const t = map.getSize();
    setStatus(n === 0
      ? `Détail écran : la capture fera ${t.x}×${t.y} px (z${Math.round(map.getZoom())}). L'affichage ne change pas.`
      : `Détail ×${Math.pow(2, n)} : la capture ira chercher les tuiles z${z} → environ ${t.x * Math.pow(2, n)}×${t.y * Math.pow(2, n)} px. L'écran, lui, ne change pas.`);
  });
}

// Bouton "Capturer" → compose l'image de la carte et l'enregistre.
document.getElementById("capture-btn").addEventListener("click", async () => {
  // Éclair IMMÉDIAT, dès l'appui — comme le déclencheur d'un appareil photo.
  flashAppareilPhoto();

  const ancienLien = document.getElementById("traiter");
  if (ancienLien) ancienLien.hidden = true;      // il désignerait la capture précédente

  setStatus("Chargement des tuiles…");

  // On s'assure que la carte remplit bien son conteneur, puis on attend les tuiles.
  map.invalidateSize();
  await waitForTiles();

  try {
    // Niveau de détail : 0 = ce que montre l'écran · 1 = un zoom plus fin (×2) · 2 = (×4).
    // On plafonne au zoom maximal de la couche, sinon le CDN renvoie des tuiles vides.
    const niveau = parseInt((document.getElementById("detail") || {}).value || "0", 10);
    const zEcran = Math.round(map.getZoom());
    const zoom = Math.min(20, zEcran + niveau);
    let canvas;
    if (zoom > zEcran) {
      setStatus(`Détail ×${Math.pow(2, zoom - zEcran)} : chargement des tuiles z${zoom}…`);
      canvas = await composerAuZoom(zoom, !nomsRetires(), (fait, total) => {
        setStatus(`Détail ×${Math.pow(2, zoom - zEcran)} : ${fait}/${total} tuiles…`);
      });
    } else {
      setStatus("Capture en cours…");
      canvas = captureMapCanvas();
    }
    const dataUrl = canvas.toDataURL("image/png");

    // Bouton "Retirer les noms" actif : on interroge OSM pour relever les lieux de la vue.
    let elements = [];
    if (nomsRetires()) {
      setStatus("Relevé des lieux (villes, pays…)…");
      elements = await releverLieux(map.getBounds(), zoom);
    }

    // Métadonnées enregistrées à côté de l'image : zoom, cadre géo, centre, et les lieux relevés.
    const centre = map.getCenter();
    const bounds = map.getBounds();
    const meta = {
      zoom,
      centre: { lat: centre.lat, lng: centre.lng },
      bornes: {
        sud: bounds.getSouth(), ouest: bounds.getWest(),
        nord: bounds.getNorth(), est: bounds.getEast(),
      },
      elements,
    };

    // Envoi au serveur : le zoom pour le nom du fichier, meta pour le .json à côté.
    const body = new URLSearchParams();
    body.set("image", dataUrl);
    body.set("zoom", zoom);
    body.set("meta", JSON.stringify(meta));

    const res = await fetch("save.php", { method: "POST", body });
    const result = await res.json();

    if (result.ok) {
      const n = elements.length;
      const suffixe = n ? ` · ${n} lieu${n > 1 ? "x" : ""} relevé${n > 1 ? "s" : ""}` : "";
      // On annonce la taille et le zoom obtenus : c'est la seule preuve visible que le
      // niveau de détail a bien été pris en compte.
      const detail = zoom > zEcran ? ` · détail ×${Math.pow(2, zoom - zEcran)}` : "";
      setStatus(`Image enregistrée : ${result.filename} ✔ · ${canvas.width}×${canvas.height} px · z${zoom}${detail}${suffixe}`);
      playCaptureAnimation();
      // Le lien vers la reconstruction, avec le nom de CETTE capture : sans lui, il faudrait
      // aller la retrouver dans la liste de l'autre page — et se tromper de voisine.
      const lien = document.getElementById("traiter");
      if (lien) {
        lien.href = "echantillon.html?capture=" + encodeURIComponent(result.filename);
        lien.hidden = false;
        lien.title = "Ouvrir « " + result.filename + " » dans l'outil de relief 3D";
      }
    } else {
      setStatus(`Erreur d'enregistrement : ${result.error}`, true);
    }
  } catch (err) {
    // Message explicite : une image « tainted » (CORS) fait échouer toDataURL — on veut le savoir.
    console.error("Capture échouée :", err);
    setStatus("Échec de la capture : " + (err && err.message ? err.message : err), true);
  }
});

// Bouton "Ma position" → géolocalisation du navigateur (gratuit).
document.getElementById("locate-btn").addEventListener("click", () => {
  if (!navigator.geolocation) {
    setStatus("La géolocalisation n'est pas disponible.", true);
    return;
  }
  setStatus("Localisation en cours…");
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const { latitude, longitude } = pos.coords;
      placeMarker(latitude, longitude, "Vous êtes ici");
      setStatus("Position trouvée.");
    },
    () => setStatus("Impossible d'obtenir votre position.", true)
  );
});
