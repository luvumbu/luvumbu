/**
 * Boucle principale du jeu (hub enfant).
 * - charge/crée la créature
 * - affiche stats, points, niveau, emoji selon le stade
 * - branche les 5 boutons : Nourrir, Apprendre, Jouer, Boutique, Maison
 */
import { api } from '../api/client.js';
import { EVOLUTIONS } from './evolutions.js';
import { initLearn, resetLearn, playTopic } from '../ui/learn.js';
import { initShop, openShop } from '../ui/shop.js';

const statusEl = document.getElementById('status');
let pet = null;

// Emoji affiché selon le stade d'évolution
const STAGE_EMOJI = { egg: '🥚', baby: '🐣', child: '🐱', teen: '🐈', adult: '🐉' };

/** Rafraîchit tout l'affichage à partir de `pet`. */
function render() {
  if (!pet) return;
  document.getElementById('points').textContent = pet.points;
  document.getElementById('level').textContent  = pet.level;

  document.getElementById('health').value    = pet.health;
  document.getElementById('hunger').value     = 100 - pet.hunger;   // Satiété
  document.getElementById('happiness').value  = pet.happiness;
  document.getElementById('energy').value     = pet.energy;
  document.getElementById('knowledge').value  = Math.min(100, pet.knowledge); // barre plafonnée à 100

  document.getElementById('pet-emoji').textContent = STAGE_EMOJI[pet.stage] || '🐣';
  document.getElementById('pet-name').textContent  = pet.name;

  if (!Number(pet.is_alive)) {
    statusEl.textContent = `🪦 ${pet.name} nous a quittés…`;
  } else if (Number(pet.is_sleeping)) {
    statusEl.textContent = `😴 ${pet.name} dort`;
  } else {
    statusEl.textContent = `🧠 Connaissance : ${pet.knowledge}`;
  }
}

/** Met à jour la créature courante + l'affichage (appelé aussi par le module Apprendre). */
function updatePet(newPet) {
  pet = newPet;
  render();
}

async function loadPet() {
  const pets = await api.pets();
  if (pets.length > 0) {
    pet = pets[0];
  } else {
    const name = prompt('Nomme ta créature :', 'Bidou') || 'Bidou';
    pet = await api.create(name);
  }
  render();
}

/** Actions directes sur la créature. */
async function doAction(action) {
  if (!pet) return;
  try {
    const before = { ...pet };
    pet = await api[action](pet.id);
    render();
    if (action === 'feed' && before.hunger === pet.hunger) {
      statusEl.textContent = `😋 ${pet.name} n'a pas faim.`;
    } else if (action === 'feed') {
      statusEl.textContent = `🍎 Miam !`;
    } else if (action === 'play') {
      statusEl.textContent = `🎮 ${pet.name} s'amuse !`;
    } else if (action === 'sleep') {
      statusEl.textContent = `😴 ${pet.name} récupère.`;
    }
  } catch (e) {
    statusEl.textContent = `⚠️ ${e.message}`;
  }
}

// ---------- Galerie d'évolutions (écran Maison) ----------
function buildEvolutionsGallery() {
  const grid = document.getElementById('evolutions-grid');
  grid.innerHTML = '';
  EVOLUTIONS.forEach((sp) => {
    const title = document.createElement('h3');
    title.className = 'evo-species';
    title.textContent = sp.species;
    grid.appendChild(title);

    const row = document.createElement('div');
    row.className = 'evo-row';
    sp.line.forEach((form, i) => {
      const cell = document.createElement('figure');
      cell.className = 'evo-cell';
      cell.innerHTML = `<div class="evo-art">${form.emoji}</div><figcaption>${form.name}</figcaption>`;
      row.appendChild(cell);
      if (i < sp.line.length - 1) {
        const arrow = document.createElement('div');
        arrow.className = 'evo-arrow';
        arrow.textContent = '→';
        row.appendChild(arrow);
      }
    });
    grid.appendChild(row);
  });
}

// ---------- Modales / navigation ----------
function openModal(id) { document.getElementById(id).hidden = false; }
function closeModal(el) { el.hidden = true; }

function wireModals() {
  // Boutons de fermeture (✕) et clic sur le fond
  document.querySelectorAll('.modal').forEach((modal) => {
    modal.addEventListener('click', (e) => {
      if (e.target === modal || e.target.hasAttribute('data-close')) closeModal(modal);
    });
  });
}

function wireHub() {
  document.querySelectorAll('.hub-btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const nav = btn.dataset.nav;
      switch (nav) {
        // Nourrir = acheter à manger → on ouvre la boutique
        case 'feed':
        case 'shop':  openModal('shop-modal'); openShop(); break;
        case 'learn': resetLearn(); openModal('learn-modal'); break;
        case 'home':  buildEvolutionsGallery(); openModal('evolutions-modal'); break;
      }
    });
  });
}

async function init() {
  try {
    await api.ping();
    await loadPet();

    // Arrivée depuis la page de cours : cours.html → « Faire l'exercice » (index.html?play=topic)
    const playTopicParam = new URLSearchParams(location.search).get('play');
    if (playTopicParam) {
      // On nettoie l'URL tout de suite : un rechargement repartira du menu principal.
      history.replaceState({}, '', location.pathname);
      resetLearn();
      openModal('learn-modal');
      playTopic(playTopicParam);
    }
  } catch (e) {
    // Base pas encore configurée → on envoie vers l'assistant d'installation.
    const notConfigured = /DB connection failed|base de données|database|1045|1049|SQLSTATE/i.test(e.message || '');
    if (notConfigured) {
      statusEl.textContent = '⚙️ Configuration nécessaire… redirection vers l\'installation.';
      setTimeout(() => { window.location.href = 'install.php'; }, 800);
      return;
    }
    statusEl.textContent = `❌ API injoignable : ${e.message}`;
  }
}

initLearn({ getPetId: () => pet.id, onReward: updatePet });
initShop({ getPet: () => pet, onUpdate: updatePet });
wireHub();
wireModals();
init();
