/**
 * Catalogue des évolutions (formes possibles des Tamago).
 * Chaque espèce a une lignée : Œuf → Bébé → Enfant → Ado → Adulte.
 * Noms volontairement décalés, dans l'esprit de la planche d'inspiration
 * (personnages loufoques à noms descriptifs absurdes).
 *
 * ⚠️ Données front pour l'instant — pourront venir d'un endpoint /evolutions plus tard.
 */
export const EVOLUTIONS = [
  {
    species: 'Blob',
    line: [
      { stage: 'egg',   name: 'Œuf Suspect',        emoji: '🥚' },
      { stage: 'baby',  name: 'Petite Goutte',      emoji: '💧' },
      { stage: 'child', name: 'Blob Curieux',       emoji: '🫠' },
      { stage: 'teen',  name: 'Gélatine Rebelle',   emoji: '🟢' },
      { stage: 'adult', name: 'Roi de la Flaque',   emoji: '👑' },
    ],
  },
  {
    species: 'Dragon',
    line: [
      { stage: 'egg',   name: 'Œuf Fumant',         emoji: '🥚' },
      { stage: 'baby',  name: 'Lézard Grognon',     emoji: '🦎' },
      { stage: 'child', name: 'Cracheur Débutant',  emoji: '🐲' },
      { stage: 'teen',  name: 'Ado en Feu',         emoji: '🔥' },
      { stage: 'adult', name: 'Dragon Majestueux',  emoji: '🐉' },
    ],
  },
  {
    species: 'Chaton',
    line: [
      { stage: 'egg',   name: 'Œuf Ronronnant',     emoji: '🥚' },
      { stage: 'baby',  name: 'Boule de Poils',     emoji: '🐣' },
      { stage: 'child', name: 'Chat Sardonique',    emoji: '😼' },
      { stage: 'teen',  name: 'Félin Nonchalant',   emoji: '🐈' },
      { stage: 'adult', name: 'Seigneur du Canapé', emoji: '🦁' },
    ],
  },

  // ---------- Lignées inspirées de la planche ----------

  {
    species: "Chien de l'Espace",   // "Space Dog"
    line: [
      { stage: 'egg',   name: 'Œuf Orbital',         emoji: '🥚' },
      { stage: 'baby',  name: 'Chiot Apesanteur',    emoji: '🐶' },
      { stage: 'child', name: 'Toutou Astronaute',   emoji: '🚀' },
      { stage: 'teen',  name: 'Cabot Cosmique',      emoji: '🛸' },
      { stage: 'adult', name: 'Commandant Aboyeur',  emoji: '🐕‍🦺' },
    ],
  },
  {
    species: 'Troll des Poubelles', // "Garbage Troll"
    line: [
      { stage: 'egg',   name: 'Œuf Moisi',           emoji: '🥚' },
      { stage: 'baby',  name: 'Détritus Vivant',     emoji: '🦠' },
      { stage: 'child', name: 'Gobelin des Ordures', emoji: '👺' },
      { stage: 'teen',  name: 'Ado Recyclé',         emoji: '🗑️' },
      { stage: 'adult', name: 'Empereur du Compost', emoji: '♻️' },
    ],
  },
  {
    species: 'Maître du Cube',      // "Cube Master"
    line: [
      { stage: 'egg',   name: 'Œuf Pixelisé',        emoji: '🥚' },
      { stage: 'baby',  name: 'Petit Bloc',          emoji: '🟦' },
      { stage: 'child', name: 'Cube Bavard',         emoji: '🧊' },
      { stage: 'teen',  name: 'Constructeur Ado',    emoji: '⛏️' },
      { stage: 'adult', name: 'Architecte Carré',    emoji: '🏗️' },
    ],
  },
  {
    species: 'Plombier Musclé',     // "Michael the Contractor"
    line: [
      { stage: 'egg',   name: 'Œuf à Moustache',     emoji: '🥚' },
      { stage: 'baby',  name: 'Apprenti Tuyau',      emoji: '🔩' },
      { stage: 'child', name: 'Bricoleur Sautillant',emoji: '🔧' },
      { stage: 'teen',  name: 'Plombier Rebelle',    emoji: '🚿' },
      { stage: 'adult', name: 'Roi du Marteau',      emoji: '🔨' },
    ],
  },
  {
    species: 'Sorcier Pourpre',     // "Crimson Conjurer"
    line: [
      { stage: 'egg',   name: 'Œuf Ensorcelé',       emoji: '🥚' },
      { stage: 'baby',  name: 'Bambin Magicien',     emoji: '🎩' },
      { stage: 'child', name: 'Illusionniste Timide',emoji: '✨' },
      { stage: 'teen',  name: 'Conjurateur Écarlate',emoji: '🔮' },
      { stage: 'adult', name: 'Grand Mage Cramoisi', emoji: '🧙' },
    ],
  },
  {
    species: 'Camion Transformé',   // "Changed Truck"
    line: [
      { stage: 'egg',   name: 'Œuf Boulonné',        emoji: '🥚' },
      { stage: 'baby',  name: 'Mini-Robot',          emoji: '🤖' },
      { stage: 'child', name: 'Bidouilleur Méca',    emoji: '⚙️' },
      { stage: 'teen',  name: 'Ado Blindé',          emoji: '🚙' },
      { stage: 'adult', name: 'Camion Suprême',      emoji: '🚚' },
    ],
  },
];
