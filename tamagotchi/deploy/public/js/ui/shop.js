/**
 * Écran « Boutique » : acheter un aliment le fait manger tout de suite.
 * Reçoit `getPet` (état courant) et `onUpdate(pet)` pour propager les changements.
 */
import { api } from '../api/client.js';

let ctx = null;   // { getPet, onUpdate }
let foods = [];

const $ = (id) => document.getElementById(id);

export function initShop({ getPet, onUpdate }) {
  ctx = { getPet, onUpdate };
}

/** Ouvre et (re)dessine la boutique. */
export async function openShop() {
  $('shop-feedback').textContent = '';
  try {
    if (foods.length === 0) foods = await api.shopList();
    renderWallet();
    renderGrid();
  } catch (e) {
    $('shop-feedback').textContent = '⚠️ ' + e.message;
  }
}

function renderWallet() {
  $('shop-points').textContent = ctx.getPet().points;
}

function renderGrid() {
  const pet = ctx.getPet();
  const grid = $('shop-grid');
  grid.innerHTML = '';

  foods.forEach((f) => {
    const affordable = pet.points >= Number(f.price);
    const card = document.createElement('div');
    card.className = 'shop-card' + (affordable ? '' : ' broke');
    card.innerHTML = `
      <div class="shop-emoji">${f.emoji}</div>
      <div class="shop-name">${f.name}</div>
      <div class="shop-effects">
        ${badge('🍖', f.d_hunger, true)}
        ${badge('⚡', f.d_energy)}
        ${badge('❤️', f.d_health)}
        ${badge('😊', f.d_happy)}
      </div>
      <button class="shop-buy" ${affordable ? '' : 'disabled'}>${f.price} 💰</button>`;
    card.querySelector('.shop-buy').addEventListener('click', () => buy(f.id));
    grid.appendChild(card);
  });
}

/** Petite pastille d'effet. `invert` = pour la faim (négatif = bon). */
function badge(icon, value, invert = false) {
  const v = Number(value);
  if (v === 0) return '';
  const good = invert ? v < 0 : v > 0;
  const shown = invert ? `+${Math.abs(v)}` : (v > 0 ? `+${v}` : `${v}`);
  return `<span class="badge ${good ? 'up' : 'down'}">${icon}${shown}</span>`;
}

async function buy(itemId) {
  try {
    const res = await api.buy(ctx.getPet().id, itemId);
    ctx.onUpdate(res.pet);           // met à jour le jeu (points, stats)
    $('shop-feedback').textContent = res.message;
    renderWallet();
    renderGrid();                    // rafraîchit ce qu'on peut encore acheter
  } catch (e) {
    $('shop-feedback').textContent = e.message;  // ex : pas assez de points
  }
}
