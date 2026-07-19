/**
 * Écran « Apprendre » en mode QUIZ.
 * L'enfant choisit un niveau (1-4) et un palier (5, 10 ou 25 questions).
 * Chaque bonne réponse rapporte des points ; à la fin, bonus selon le palier
 * (×1.5 si sans faute). Enchaînement rapide entre les questions.
 */
import { api } from '../api/client.js';
import { speak, speakSequence, toggleVoice, warmUp } from './speech.js';

let ctx = null;               // { getPetId, onReward }
const sel = { topic: null, count: null };

let currentToken = null;
let currentTopic = '';        // thème concret joué (pour la progression)
let currentPrompt = '';       // consigne en cours (pour la réécouter)
let currentSay = '';          // élément à PRONONCER sans l'afficher (ex : lettre au son)
let currentQ = null;          // question complète (pour l'animation de calcul)

/** Lit la consigne (et, s'il y en a un, l'élément « caché » à reconnaître au son). */
function sayInstruction() {
  if (currentSay) {
    speakSequence([{ text: currentPrompt }, { text: currentSay }], 0.85);
  } else {
    speak(currentPrompt);
  }
}
let asked = 0;                // questions déjà posées
let correct = 0;             // bonnes réponses
let locked = false;

const $ = (id) => document.getElementById(id);

const shownLessons = new Set();   // leçons déjà vues cette session
let lessonReturnToPlay = false;   // vrai si la leçon a été ouverte PENDANT l'exercice
let recentPrompts = [];           // dernières questions posées (anti-répétition)

// Délais d'enchaînement (rapides)
const DELAY_OK    = 450;      // après une bonne réponse
const DELAY_WRONG = 900;      // après une erreur (le temps de voir la solution)

// Un vrai petit COURS avant chaque exercice (définition, méthode, exemples, astuce 💡).
const LESSONS = {
  // --- Éveil / découverte ---
  colors: { emoji: '🎨', title: 'Les couleurs', lines: [
    "Les couleurs sont partout : les objets, les vêtements, la nature.",
    "Regarde bien le rond coloré affiché en haut.",
    "Tu dois retrouver la case qui a EXACTEMENT la même couleur.",
    "Les couleurs de base : rouge, bleu, jaune.",
    "En les mélangeant on obtient : vert, orange, violet.",
    "💡 Astuce : ne confonds pas le bleu et le violet, regarde bien la teinte."] },
  shapes: { emoji: '🔷', title: 'Les formes', lines: [
    "Une forme se reconnaît à son contour et à ses coins.",
    "Le rond ⚪ est tout arrondi : aucun coin.",
    "Le carré 🟩 a 4 côtés égaux et 4 coins.",
    "Le triangle 🔺 a 3 côtés et 3 coins.",
    "L'étoile ⭐ a des pointes, le cœur ❤️ a deux bosses.",
    "💡 Compte les côtés et les coins pour ne pas te tromper."] },
  sizes: { emoji: '📏', title: 'Les tailles', lines: [
    "Comparer les tailles, c'est voir ce qui est grand ou petit.",
    "Le plus GRAND prend le plus de place.",
    "Le plus PETIT prend le moins de place.",
    "Exemple : l'éléphant 🐘 est énorme, la fourmi 🐜 est minuscule.",
    "💡 Range les images dans ta tête, du plus petit au plus grand."] },
  animals: { emoji: '🐱', title: 'Les animaux', lines: [
    "Chaque animal a un nom et une apparence bien à lui.",
    "Le chat 🐱 a des moustaches, le chien 🐶 aboie.",
    "La vache 🐮 a des taches, le cochon 🐷 est rose.",
    "La poule 🐔 pond des œufs, le cheval 🐴 galope.",
    "💡 Écoute bien le nom demandé, puis retrouve le bon animal."] },
  animalsound: { emoji: '🔊', title: 'Les cris des animaux', lines: [
    "Chaque animal a son cri, un son bien à lui.",
    "Le chat fait « Miaou », le chien « Ouaf ».",
    "La vache fait « Meuh », le mouton « Bêê ».",
    "Le coq fait « Cocorico », le canard « Coin coin ».",
    "💡 Écoute bien le cri, puis choisis l'animal qui le fait."] },
  foods: { emoji: '🍎', title: 'Les aliments', lines: [
    "Les aliments, c'est tout ce qu'on mange.",
    "Les fruits : pomme 🍎, banane 🍌, fraise 🍓.",
    "Les légumes : carotte 🥕, tomate 🍅, brocoli 🥦.",
    "Et aussi : le pain 🍞, le fromage 🧀, le poisson 🐟.",
    "💡 Pour être en forme, il faut manger de tout, surtout des fruits et légumes !"] },
  body: { emoji: '🧍', title: 'Le corps', lines: [
    "Le corps a plein de parties différentes.",
    "Sur la tête : les yeux 👁️, le nez 👃, la bouche 👄, les oreilles 👂.",
    "Pour attraper : les mains ✋ et les doigts.",
    "Pour marcher : les jambes 🦵 et les pieds 🦶.",
    "💡 Montre la partie sur toi pour bien la retenir."] },
  emotions: { emoji: '😊', title: 'Les émotions', lines: [
    "Une émotion, c'est ce que l'on ressent à l'intérieur.",
    "Content 😊 : on sourit, on est heureux.",
    "Triste 😢 : on a de la peine.",
    "Fâché 😠 : on est en colère. Surpris 😮 : on ne s'y attendait pas.",
    "💡 Regarde bien les yeux et la bouche du visage."] },
  opposites: { emoji: '↔️', title: 'Les contraires', lines: [
    "Deux contraires, c'est deux choses tout à fait opposées.",
    "Chaud 🔥 ↔ froid ❄️.",
    "Jour ☀️ ↔ nuit 🌙.",
    "Grand ↔ petit, haut ↔ bas, content ↔ triste.",
    "💡 Cherche l'image qui veut dire le CONTRAIRE de celle montrée."] },

  // --- Logique ---
  intrus: { emoji: '🔍', title: "Trouver l'intrus", lines: [
    "L'intrus, c'est celui qui ne va pas avec les autres.",
    "Plusieurs objets se ressemblent (même famille).",
    "Un seul est différent : c'est lui, l'intrus !",
    "Exemple : 🍎 🍌 🍓 🐶 → l'intrus est le chien (les autres sont des fruits).",
    "💡 Trouve d'abord ce que les autres ont en commun."] },
  pareil: { emoji: '👯', title: 'Le même', lines: [
    "Ici, il faut trouver deux dessins identiques.",
    "Regarde bien le modèle affiché en haut.",
    "Choisis la réponse EXACTEMENT pareille au modèle.",
    "💡 Compare les détails un par un."] },
  suite: { emoji: '🧩', title: 'Les suites logiques', lines: [
    "Une suite logique, c'est un motif qui se répète toujours pareil.",
    "Exemple : 🔴🔵🔴🔵🔴… ça continue par 🔵.",
    "Étape 1 : trouve le motif qui revient.",
    "Étape 2 : devine ce qui vient JUSTE APRÈS.",
    "💡 Répète le motif à voix basse pour le sentir."] },
  assoc: { emoji: '🔗', title: 'Associer', lines: [
    "Associer, c'est relier deux choses qui vont ensemble.",
    "Le chien 🐶 va avec l'os 🦴.",
    "Le parapluie ☔ va avec la pluie 🌧️.",
    "La clé 🔑 va avec le cadenas 🔒.",
    "💡 Demande-toi : qu'est-ce qui va naturellement avec l'image ?"] },
  compare: { emoji: '⚖️', title: 'Le plus / le moins', lines: [
    "Comparer les quantités, c'est voir où il y en a le plus ou le moins.",
    "Compte les objets de chaque groupe.",
    "Le groupe avec le PLUS grand nombre → « le plus ».",
    "Le groupe avec le plus petit nombre → « le moins ».",
    "💡 Compte bien chaque groupe avant de choisir."] },
  categorie: { emoji: '🗂️', title: 'Les catégories', lines: [
    "Une catégorie, c'est une famille d'objets qui vont ensemble.",
    "Les fruits, les animaux, les véhicules, les fleurs…",
    "On te demande un objet d'une famille précise.",
    "Exemple : « Trouve un fruit » → choisis la pomme 🍎.",
    "💡 Élimine ce qui n'est pas de la bonne famille."] },
  rang: { emoji: '🥇', title: 'Premier / dernier', lines: [
    "Le rang, c'est la place dans une rangée.",
    "On lit toujours de GAUCHE à DROITE.",
    "Le PREMIER est tout au début (à gauche).",
    "Le DERNIER est tout à la fin (à droite).",
    "💡 Pose ton doigt au début pour trouver le premier."] },

  // --- Lettres / lecture ---
  letters: { emoji: '🔤', title: 'Les lettres', lines: [
    "L'alphabet, ce sont les 26 lettres : A, B, C, D… jusqu'à Z.",
    "Chaque lettre a une forme et un son.",
    "On te montre une lettre : retrouve-la parmi les réponses.",
    "💡 Récite l'alphabet dans ta tête pour t'aider."] },
  letters_sound: { emoji: '🔊', title: 'Écouter les lettres', lines: [
    "Ici, la lettre est DITE à voix haute, mais pas écrite.",
    "Écoute bien le son : « A », « Bé », « Cé »…",
    "Puis retrouve la bonne lettre parmi les réponses.",
    "💡 Tu peux réécouter avec le bouton 🔊."] },
  readword: { emoji: '📖', title: 'Lire un mot', lines: [
    "Lire, c'est assembler les lettres pour former un mot.",
    "Lis le mot lettre par lettre, doucement.",
    "Assemble les sons : c-h-a-t → « chat ».",
    "Puis choisis l'image qui correspond.",
    "💡 Prononce le mot à voix basse pour t'aider."] },
  spell: { emoji: '✍️', title: "L'orthographe", lines: [
    "L'orthographe, c'est écrire les mots correctement.",
    "Un seul des mots proposés est BIEN écrit.",
    "Les autres ont une faute (lettre en trop, en moins, ou changée).",
    "Exemple : « maison » est correct, « mèzon » est faux.",
    "💡 Lis chaque mot dans ta tête et repère l'erreur."] },

  // --- Nombres ---
  count3: { emoji: '🔢', title: 'Compter', lines: [
    "Compter, c'est dire les nombres dans l'ordre en pointant chaque objet.",
    "Un objet = un nombre : un… deux… trois…",
    "Ne compte pas deux fois le même !",
    "Le dernier nombre dit, c'est le total.",
    "💡 Pointe chaque objet avec ton doigt."] },
  count: { emoji: '🔢', title: 'Compter', lines: [
    "Compter, c'est dire les nombres dans l'ordre, un par objet.",
    "Un objet = un nombre : un, deux, trois, quatre…",
    "Ne saute personne et ne compte pas deux fois.",
    "Le dernier nombre dit, c'est le total.",
    "💡 Regroupe par 5 ou par 10 pour aller plus vite."] },
  digits: { emoji: '🔟', title: 'Les chiffres', lines: [
    "Les chiffres servent à écrire les nombres.",
    "Il y en a dix : 0, 1, 2, 3, 4, 5, 6, 7, 8, 9.",
    "Chaque chiffre a sa forme.",
    "Retrouve le chiffre (ou le nombre) demandé.",
    "💡 Ne confonds pas 6 et 9, ni 2 et 5."] },
  nextnum: { emoji: '🔢', title: 'Avant / Après', lines: [
    "Les nombres se suivent toujours dans le même ordre.",
    "APRÈS un nombre = celui juste PLUS GRAND (+1). Après 4, c'est 5.",
    "AVANT un nombre = celui juste PLUS PETIT (−1). Avant 4, c'est 3.",
    "💡 Compte sur tes doigts si tu hésites."] },
  numbig: { emoji: '🔢', title: 'Le plus grand nombre', lines: [
    "Comparer, c'est trouver le plus grand ou le plus petit nombre.",
    "Plus un nombre est loin dans le comptage, plus il est grand.",
    "9 est plus grand que 3.",
    "Pour les grands nombres, compte d'abord les chiffres : 25 > 9.",
    "💡 Plus il y a de chiffres, plus le nombre est grand."] },
  suitenum: { emoji: '🔢', title: 'Le nombre manquant', lines: [
    "Les nombres se suivent : 1, 2, 3, 4, 5…",
    "Un nombre est caché par le ❓.",
    "Regarde les nombres autour pour deviner celui qui manque.",
    "Exemple : 3, ❓, 5 → il manque 4.",
    "💡 Compte à voix basse pour retrouver le trou."] },
  evenodd: { emoji: '🔢', title: 'Pair ou impair', lines: [
    "Un nombre est PAIR ou IMPAIR.",
    "PAIR : on peut le partager en 2 parts égales.",
    "Il se termine par 0, 2, 4, 6 ou 8.",
    "IMPAIR : il en reste toujours un tout seul (1, 3, 5, 7, 9).",
    "💡 Regarde seulement le DERNIER chiffre !"] },
  complement: { emoji: '🎯', title: 'Compléter', lines: [
    "Compléter, c'est trouver combien il manque pour un nombre rond.",
    "Pour aller à 10 : 7 + ? = 10, il manque 3.",
    "Pour aller à 100 : 70 + ? = 100, il manque 30.",
    "💡 Apprends les compléments à 10 : 1+9, 2+8, 3+7, 4+6, 5+5."] },

  // --- Calcul ---
  addition: { emoji: '➕', title: "L'addition", lines: [
    "Additionner, c'est mettre ensemble et compter le total.",
    "Le signe est + (« plus »).",
    "Exemple : 2 + 3, on réunit 2 et 3, ça fait 5.",
    "Tu peux continuer à compter : 2… puis 3, 4, 5.",
    "💡 L'ordre ne change rien : 2 + 3 = 3 + 2."] },
  subtraction: { emoji: '➖', title: 'La soustraction', lines: [
    "Soustraire, c'est ENLEVER, retirer une quantité.",
    "Le signe est − (« moins »).",
    "Exemple : 5 − 2, on enlève 2 à 5, il reste 3.",
    "On part du grand nombre et on recule : 5 → 4, 3.",
    "💡 « moins » → le résultat devient plus petit."] },
  addsub: { emoji: '➕➖', title: 'Additions et soustractions', lines: [
    "Deux opérations à ne pas confondre.",
    "+ (plus) : on AJOUTE, on met ensemble.",
    "− (moins) : on ENLÈVE, on retire.",
    "Regarde bien le signe AVANT de calculer !",
    "💡 « plus » → ça grandit. « moins » → ça diminue."] },
  double: { emoji: '✖️2', title: 'Les doubles', lines: [
    "Le double d'un nombre, c'est ce nombre pris DEUX fois.",
    "Doubler = additionner le nombre avec lui-même.",
    "Le double de 4 = 4 + 4 = 8.",
    "Le double de 5 = 10, le double de 10 = 20.",
    "💡 Doubler, c'est comme multiplier par 2."] },
  half: { emoji: '✂️', title: 'La moitié', lines: [
    "La moitié, c'est partager en DEUX parts égales.",
    "On coupe le nombre en deux.",
    "La moitié de 8 = 4 (car 4 + 4 = 8).",
    "La moitié de 10 = 5.",
    "💡 La moitié, c'est le contraire du double."] },
  triple: { emoji: '✖️3', title: 'Le triple', lines: [
    "Le triple d'un nombre, c'est ce nombre pris TROIS fois.",
    "Le triple de 2 = 2 + 2 + 2 = 6.",
    "Le triple de 4 = 12.",
    "💡 Tripler, c'est comme multiplier par 3."] },
  muldiv: { emoji: '✖️', title: 'Multiplier et diviser', lines: [
    "Multiplier (×), c'est additionner plusieurs fois le même nombre.",
    "3 × 4 = 4 + 4 + 4 = 12 (3 paquets de 4).",
    "Diviser (÷), c'est partager en parts égales.",
    "12 ÷ 4 = 3 (12 partagé en 4 groupes de 3).",
    "💡 × et ÷ sont contraires, comme + et −."] },
  longmult: { emoji: '✖️', title: 'Multiplications à 2 chiffres', lines: [
    "On multiplie des nombres plus grands.",
    "Astuce : décompose le grand nombre.",
    "14 × 3 = (10 × 3) + (4 × 3) = 30 + 12 = 42.",
    "💡 Sépare les dizaines et les unités."] },
  division: { emoji: '➗', title: 'Les divisions', lines: [
    "Diviser, c'est partager en parts égales.",
    "20 ÷ 4 : on partage 20 en 4 groupes → 5 dans chaque.",
    "C'est le contraire de la multiplication : 4 × 5 = 20.",
    "💡 Demande-toi : « combien de fois 4 dans 20 ? »"] },
  problem: { emoji: '📝', title: 'Les problèmes', lines: [
    "Un problème raconte une petite histoire avec des nombres.",
    "1) Lis bien et comprends l'histoire.",
    "2) Trouve l'opération (+, −, × ?).",
    "3) Calcule, puis réponds.",
    "💡 « il en perd » → moins. « on lui en donne » → plus."] },
  square: { emoji: '²', title: 'Les carrés', lines: [
    "Le carré d'un nombre, c'est ce nombre multiplié par lui-même.",
    "On l'écrit avec un petit 2 : 5².",
    "5² = 5 × 5 = 25.",
    "3² = 9, 4² = 16, 10² = 100.",
    "💡 « au carré » veut dire « fois lui-même »."] },
  priorities: { emoji: '✖️➕', title: 'Les priorités', lines: [
    "Dans un calcul, il y a un ORDRE à respecter.",
    "On calcule d'abord les × et les ÷.",
    "Ensuite seulement les + et les −.",
    "Exemple : 3 + 4 × 2. D'abord 4 × 2 = 8. Puis 3 + 8 = 11.",
    "⚠️ Piège : ce n'est PAS 7 × 2 = 14 !"] },
  perimeter: { emoji: '📐', title: 'Le périmètre', lines: [
    "Le périmètre, c'est la longueur du TOUR d'une figure.",
    "On additionne tous les côtés.",
    "Carré (4 côtés égaux) : 4 × côté.",
    "Rectangle : 2 × (Longueur + largeur).",
    "💡 Imagine que tu marches tout autour de la figure."] },
  aire: { emoji: '🟦', title: "L'aire", lines: [
    "L'aire, c'est la SURFACE à l'intérieur d'une figure.",
    "C'est la place occupée, comme du carrelage.",
    "Carré : côté × côté.",
    "Rectangle : Longueur × largeur.",
    "💡 Le périmètre = le tour ; l'aire = l'intérieur."] },
  relative: { emoji: '🌡️', title: 'Les nombres relatifs', lines: [
    "Les nombres relatifs ont un signe + ou −.",
    "Les négatifs sont plus petits que zéro : −3, −2, −1.",
    "Puis viennent 0, 1, 2, 3…",
    "Exemple : il fait −3°, il monte de 5° → −3, −2, −1, 0, 1, 2 : il fait 2°.",
    "💡 Sur une ligne : à gauche du 0 c'est négatif, à droite positif."] },
  fraction: { emoji: '½', title: 'Les fractions', lines: [
    "Une fraction, c'est une part d'un tout partagé en parts égales.",
    "La moitié = en 2. Le tiers = en 3. Le quart = en 4.",
    "Le tiers de 6 : on partage 6 en 3 groupes → 2 dans chaque.",
    "Regarde les groupes encadrés : une part = un groupe.",
    "💡 Plus on partage, plus les parts sont petites."] },
  decimals: { emoji: '🔟', title: 'Les nombres décimaux', lines: [
    "Un nombre décimal a une virgule : 0,3 ; 1,5 ; 2,7.",
    "Après la virgule, ce sont les dixièmes (des parts de 10).",
    "La barre est partagée en 10. Chaque part verte = 0,1.",
    "3 parts = 0,3. Les 10 parts = 1 entier.",
    "💡 0,5 c'est la moitié (5 parts sur 10)."] },

  // --- À l'ancienne ---
  roman: { emoji: 'Ⅻ', title: 'Les chiffres romains', lines: [
    "Les Romains écrivaient les nombres avec des lettres.",
    "I = 1, V = 5, X = 10, L = 50, C = 100, M = 1000.",
    "On additionne de gauche à droite : VII = 5 + 1 + 1 = 7.",
    "Un petit signe AVANT un plus grand, on l'enlève : IV = 5 − 1 = 4.",
    "💡 IX = 10 − 1 = 9 ; XL = 50 − 10 = 40."] },
  measure: { emoji: '📏', title: 'Les mesures', lines: [
    "Pour mesurer, on utilise des unités.",
    "Longueur : 1 mètre = 100 cm ; 1 km = 1000 m.",
    "Masse : 1 kilo = 1000 grammes.",
    "Temps : 1 heure = 60 min ; 1 minute = 60 secondes.",
    "Argent : 1 euro = 100 centimes. Et 1 semaine = 7 jours."] },
  time: { emoji: '🕐', title: "Lire l'heure", lines: [
    "Une horloge a deux aiguilles.",
    "La PETITE aiguille montre l'heure.",
    "La GRANDE aiguille montre les minutes.",
    "Quand la grande est sur le 12, c'est l'heure pile.",
    "💡 🕒 : la petite est sur le 3 → il est 3 heures."] },
  money: { emoji: '🪙', title: 'La monnaie', lines: [
    "La monnaie, ce sont les pièces et les billets.",
    "Pour le total, on additionne la valeur des pièces.",
    "3 pièces de 2 € = 2 + 2 + 2 = 6 €.",
    "💡 Compte de 2 en 2, ou de 5 en 5, pour aller vite."] },

  // --- Collège ---
  percent: { emoji: '%', title: 'Les pourcentages', lines: [
    "Un pourcentage, c'est une part sur 100.",
    "50 % = la moitié. 25 % = le quart. 10 % = ÷ 10.",
    "x % d'un nombre = (nombre × x) ÷ 100.",
    "💡 50 % de 20 = 10."] },
  airetri: { emoji: '🔺', title: "L'aire du triangle", lines: [
    "Aire = (base × hauteur) ÷ 2.",
    "Exemple : base 6, hauteur 4 → (6 × 4) ÷ 2 = 12.",
    "💡 Le triangle, c'est la moitié d'un rectangle."] },
  priorpar: { emoji: '()', title: 'Priorités et parenthèses', lines: [
    "On calcule d'abord les PARENTHÈSES.",
    "Puis les × et ÷, enfin les + et −.",
    "(3 + 2) × 4 = 5 × 4 = 20.",
    "💡 Les parenthèses passent avant tout."] },
  relatadd: { emoji: '±', title: 'Additionner les relatifs', lines: [
    "Même signe : on ajoute. (−3) + (−4) = −7.",
    "Signes différents : on soustrait. (−3) + 5 = 2.",
    "💡 Pense au thermomètre : + monte, − descend."] },
  power: { emoji: '^', title: 'Les puissances', lines: [
    "2³ = 2 × 2 × 2 = 8.",
    "L'exposant dit combien de fois on multiplie.",
    "10² = 100, 10³ = 1000.",
    "💡 10 puissance n = 1 suivi de n zéros."] },
  relatmul: { emoji: '±', title: 'Multiplier les relatifs', lines: [
    "Règle des signes :",
    "(−) × (−) = (+).  (+) × (−) = (−).",
    "(−3) × 4 = −12 ; (−3) × (−4) = +12.",
    "💡 Signes pareils → +, signes différents → −."] },
  expand: { emoji: 'x', title: 'Calcul littéral', lines: [
    "Développer : a(x + b) = ax + ab.",
    "On multiplie a par chaque terme.",
    "3(x + 4) = 3x + 12.",
    "💡 N'oublie pas le 2e terme !"] },
  sqrt: { emoji: '√', title: 'Les racines carrées', lines: [
    "√ est l'inverse du carré.",
    "√49 = 7, car 7 × 7 = 49.",
    "√1=1, √4=2, √9=3, √16=4, √25=5…",
    "💡 Apprends les carrés par cœur."] },
  equation: { emoji: '=', title: 'Les équations', lines: [
    "Trouver x dans une égalité.",
    "x + 5 = 12 → x = 12 − 5 = 7.",
    "2x = 10 → x = 10 ÷ 2 = 5.",
    "💡 On fait l'opération inverse."] },
  pythagore: { emoji: '📐', title: 'Théorème de Pythagore', lines: [
    "Dans un triangle RECTANGLE :",
    "hypoténuse² = côté1² + côté2².",
    "Côtés 3 et 4 → 9 + 16 = 25 → hypoténuse = 5.",
    "💡 Le 3-4-5 est le plus connu."] },

  comparedec: { emoji: '🔟', title: 'Comparer les décimaux', lines: [
    "0,7 est plus grand que 0,45 !",
    "0,7 = 0,70, et 70 > 45.",
    "💡 Ajoute des zéros pour comparer facilement."] },
  probpercent: { emoji: '📝', title: 'Problème de pourcentage', lines: [
    "Part = (total × pourcentage) ÷ 100.",
    "20 % de 30 élèves = 6 élèves.",
    "💡 Repère le total et le pourcentage."] },
  probpropor: { emoji: '📝', title: 'Problème de proportion', lines: [
    "1) Trouve la valeur pour 1.",
    "2) Multiplie par la quantité.",
    "3 croissants = 6 € → 1 = 2 € → 5 = 10 €.",
    "💡 Passe par la valeur d'un seul."] },
  thales: { emoji: '📐', title: 'Théorème de Thalès', lines: [
    "Les longueurs sont proportionnelles : AB/AC = AD/AE.",
    "AB=2, AC=4 → rapport 2. AD=3 → AE = 3 × 2 = 6.",
    "💡 Trouve d'abord le rapport."] },
  diveucl: { emoji: '➗', title: 'Division (reste)', lines: [
    "17 ÷ 5 : 5 × 3 = 15, il reste 2.",
    "17 = 5 × 3 + 2.",
    "💡 Le reste est toujours plus petit que le diviseur."] },
  multiple: { emoji: '🔢', title: 'Les multiples', lines: [
    "Les multiples de 4 : 4, 8, 12, 16…",
    "Le 3ᵉ multiple de 4 = 4 × 3 = 12.",
    "💡 C'est la table de multiplication."] },
  roundten: { emoji: '≈', title: 'Arrondir', lines: [
    "On regarde le chiffre des unités.",
    "0-4 → on descend. 5-9 → on monte.",
    "47 → 50 ; 42 → 40.",
    "💡 Regarde seulement les unités."] },
  proportion: { emoji: '⚖️', title: 'La proportionnalité', lines: [
    "1 objet = 3 € → 5 objets = 3 × 5 = 15 €.",
    "On multiplie par le même nombre.",
    "💡 2 fois plus d'objets = 2 fois plus cher."] },
  relatsub: { emoji: '±', title: 'Soustraire les relatifs', lines: [
    "Soustraire un négatif = additionner !",
    "5 − (−3) = 5 + 3 = 8.",
    "💡 − (−) devient +."] },
  volumecube: { emoji: '🧊', title: 'Volume du cube', lines: [
    "Volume = arête × arête × arête.",
    "Cube d'arête 3 → 3 × 3 × 3 = 27.",
    "💡 C'est l'arête au cube."] },
  powerten: { emoji: '🔟', title: 'Puissances de 10', lines: [
    "10ⁿ = 1 suivi de n zéros.",
    "10² = 100, 10³ = 1000.",
    "💡 L'exposant = le nombre de zéros."] },
  factorise: { emoji: 'x', title: 'Factoriser', lines: [
    "L'inverse de développer.",
    "3x + 12 = 3(x + 4).",
    "💡 Mets le facteur commun devant."] },
  equation2: { emoji: '=', title: 'Équations (2 étapes)', lines: [
    "2x + 3 = 11.",
    "On enlève 3 : 2x = 8. On divise par 2 : x = 4.",
    "💡 D'abord −, ensuite ÷."] },
  function: { emoji: 'ƒ', title: 'Les fonctions', lines: [
    "f(x) = 2x + 1 : on remplace x.",
    "f(3) = 2 × 3 + 1 = 7.",
    "💡 f(3) = remplace x par 3."] },

  // --- Lycée ---
  identremar: { emoji: '(a+b)²', title: 'Identités remarquables', lines: [
    "(a + b)² = a² + 2ab + b².",
    "(x + 3)² = x² + 6x + 9.",
    "💡 N'oublie pas le double produit 2ab !"] },
  milieu: { emoji: '•', title: "Milieu d'un segment", lines: [
    "Le milieu = la moyenne des positions.",
    "Milieu de A(2) et B(8) = (2 + 8) ÷ 2 = 5.",
    "💡 On fait la moyenne."] },
  antecedent: { emoji: 'ƒ', title: 'Antécédent', lines: [
    "On connaît f(x), on cherche x.",
    "2x + 1 = 7 → x = 3.",
    "💡 C'est résoudre une équation."] },
  evolution: { emoji: '%', title: 'Pourcentage d\'évolution', lines: [
    "50 € + 20 % : 20 % de 50 = 10 → 60 €.",
    "50 € − 20 % → 40 €.",
    "💡 On calcule le %, puis on ajoute ou retire."] },
  moyenne: { emoji: '📊', title: 'La moyenne', lines: [
    "Somme ÷ nombre de valeurs.",
    "Moyenne de 4, 6, 8 = 18 ÷ 3 = 6.",
    "💡 Toujours entre le plus petit et le plus grand."] },
  vecteur: { emoji: '📏', title: 'Distance', lines: [
    "Distance AB = écart entre les positions.",
    "A(3) et B(8) → 8 − 3 = 5.",
    "💡 Toujours positive."] },
};

export function initLearn({ getPetId, onReward }) {
  ctx = { getPetId, onReward };

  // Onglets d'âge : n'afficher qu'un seul panneau d'âge à la fois
  document.querySelectorAll('#learn-modal .age-tab').forEach((tab) => {
    tab.addEventListener('click', () => selectAge(tab.dataset.age));
  });

  // Choix du thème
  document.querySelectorAll('#learn-modal .lvl').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (btn.classList.contains('locked')) return;   // thème verrouillé
      pickIn('#learn-modal .lvl', btn);
      sel.topic = btn.dataset.topic;
      refreshStart();
    });
  });
  // Choix du palier
  document.querySelectorAll('#learn-modal .cnt').forEach((btn) => {
    btn.addEventListener('click', () => {
      pickIn('#learn-modal .cnt', btn);
      sel.count = Number(btn.dataset.count);
      refreshStart();
    });
  });

  $('learn-start').addEventListener('click', startQuiz);
  $('learn-again').addEventListener('click', resetLearn);
  $('learn-next').addEventListener('click', advance);   // passer à l'exercice suivant
  // Fin de la leçon : soit on retourne à l'exercice en cours, soit on démarre.
  $('lesson-go').addEventListener('click', () => {
    if (lessonReturnToPlay) {
      lessonReturnToPlay = false;
      show('learn-play');                          // retour SANS perdre la progression
    } else {
      beginQuestions();
    }
  });

  // Boutons « 📖 Cours » pendant l'exercice → ouvrent la page de cours complète.
  $('learn-lesson-btn').addEventListener('click', openCurrentLesson);
  $('learn-lesson-inline').addEventListener('click', openCurrentLesson);
  // Bouton « cours complet » sur l'écran de leçon.
  $('lesson-full').addEventListener('click', () => {
    const t = sel.topic || currentTopic || '';
    if (t) window.open('cours.html?topic=' + encodeURIComponent(t), '_blank');
  });

  // Déverrouille la voix au tout premier clic de l'utilisateur dans l'écran.
  document.getElementById('learn-modal').addEventListener('click', warmUp, { once: true });

  // Voix : réécouter la consigne (et la lettre au son si présente)
  $('learn-say').addEventListener('click', sayInstruction);
  // Voix : couper / remettre le son
  $('learn-mute').addEventListener('click', () => {
    const on = toggleVoice();
    $('learn-mute').textContent = on ? '🔊' : '🔇';
    if (on) sayInstruction();
  });
}

/** Remet l'écran de configuration (appelé à l'ouverture de la modale). */
export function resetLearn() {
  sel.topic = null;
  sel.count = 5;                                 // palier 5 sélectionné par défaut
  asked = 0;
  correct = 0;
  locked = false;
  document.querySelectorAll('#learn-modal .lvl, #learn-modal .cnt').forEach((b) => b.classList.remove('active'));
  const def = document.querySelector('#learn-modal .cnt[data-count="5"]');
  if (def) def.classList.add('active');
  selectAge('age3');                             // affiche le 1er âge par défaut
  refreshStart();                                // "Commencer" s'activera dès qu'un thème est choisi
  show('learn-setup');
  renderProgress();                              // met à jour cadenas / maîtrise
}

/** Récupère la progression et grise les thèmes verrouillés (façon Duolingo). */
async function renderProgress() {
  let data;
  try {
    data = await api.progress(ctx.getPetId());
  } catch {
    return;                                       // en cas d'échec, on laisse tout ouvert
  }

  data.groups.forEach((g) => {
    const groupEl = document.querySelector(`.age-group[data-group="${g.id}"]`);
    if (groupEl) groupEl.classList.toggle('locked', !g.unlocked);

    // Bouton "au hasard" du groupe
    const rndBtn = document.querySelector(`.lvl.rnd[data-topic="${g.id}"]`);
    if (rndBtn) setLock(rndBtn, !g.unlocked);

    // Thèmes du groupe
    g.topics.forEach((t) => {
      const btn = document.querySelector(`.lvl[data-topic="${t.topic}"]`);
      if (!btn) return;
      setLock(btn, !g.unlocked);
      btn.classList.toggle('mastered', t.mastered);
      // petite jauge de maîtrise (ex : 2/3) tant que non maîtrisé
      btn.dataset.badge = t.mastered ? '✓' : (t.correct > 0 ? `${t.correct}/${t.needed}` : '');
    });
  });

  // Bouton surprise = débloqué seulement si tout est maîtrisé
  const allBtn = document.querySelector('.rnd-all');
  if (allBtn) setLock(allBtn, !data.all_unlocked);
}

function setLock(btn, isLocked) {
  btn.classList.toggle('locked', isLocked);
  btn.disabled = isLocked;
}

function pickIn(selector, btn) {
  document.querySelectorAll(selector).forEach((b) => b.classList.remove('active'));
  btn.classList.add('active');
}

/** Affiche le panneau d'un âge et surligne son onglet. */
function selectAge(ageId) {
  document.querySelectorAll('#learn-modal .age-tab').forEach((t) => {
    t.classList.toggle('active', t.dataset.age === ageId);
  });
  document.querySelectorAll('#learn-modal .age-group').forEach((p) => {
    p.classList.toggle('panel-active', p.dataset.group === ageId);
  });
}

function refreshStart() {
  $('learn-start').disabled = !(sel.topic && sel.count);
}

/** Affiche un seul des écrans (setup / leçon / play / result). */
function show(which) {
  $('learn-setup').hidden  = which !== 'learn-setup';
  $('learn-lesson').hidden = which !== 'learn-lesson';
  $('learn-play').hidden   = which !== 'learn-play';
  $('learn-result').hidden = which !== 'learn-result';
}

function startQuiz() {
  asked = 0;
  correct = 0;
  locked = false;
  recentPrompts = [];                              // on repart sans historique
  $('learn-score').textContent = '0';

  // Chaque exercice a son cours, montré une seule fois par session.
  const base = sel.topic.replace(/[4-9a]$/, '');
  if (LESSONS[base] && !shownLessons.has(base)) {
    shownLessons.add(base);
    showLesson(LESSONS[base]);
  } else {
    beginQuestions();
  }
}

/** Affiche la leçon (texte + voix) avant de commencer les questions. */
function showLesson(lesson) {
  show('learn-lesson');
  $('lesson-emoji').textContent = lesson.emoji;
  $('lesson-title').textContent = lesson.title;
  $('lesson-go').textContent = lessonReturnToPlay
    ? '⬅️ Revenir à l\'exercice'
    : "J'ai compris, on commence ! ▶";
  const ul = $('lesson-lines');
  ul.innerHTML = '';
  lesson.lines.forEach((l) => {
    const li = document.createElement('li');
    li.textContent = l;
    ul.appendChild(li);
  });
  speak(`${lesson.title}. ${lesson.lines.join('. ')}`, 0.75);
}

function beginQuestions() {
  show('learn-play');
  loadQuestion();
}

/** Lance directement un exercice précis (depuis la page de cours). */
export function playTopic(topic) {
  sel.topic = topic;
  sel.count = sel.count || 5;
  asked = 0;
  correct = 0;
  locked = false;
  recentPrompts = [];
  $('learn-score').textContent = '0';
  beginQuestions();                              // on saute la leçon (on vient du cours)
}

/** Ouvre la PAGE DE COURS COMPLÈTE du thème en cours (nouvel onglet). */
function openCurrentLesson() {
  const t = currentTopic || sel.topic || '';
  if (t) window.open('cours.html?topic=' + encodeURIComponent(t), '_blank');
}

/** Passe à l'exercice suivant (ou à l'écran de résultat). */
function advance() {
  $('learn-next').hidden = true;
  if (asked >= sel.count) finishQuiz();
  else loadQuestion();
}

/** Affiche le visuel : barre de cases, groupes en pointillés, ou simple texte. */
function renderVisual(q) {
  const vis = $('learn-visual');

  // Barre de dixièmes : cases vertes (coloriées) vs cases vides (gris clair)
  if (q.bar) {
    vis.innerHTML = '';
    const bar = document.createElement('div');
    bar.className = 'tenths-bar';
    for (let i = 0; i < q.bar.total; i++) {
      const cell = document.createElement('span');
      cell.className = 'bar-cell' + (i < q.bar.filled ? ' on' : '');
      bar.appendChild(cell);
    }
    vis.appendChild(bar);
    return;
  }

  // Groupes en pointillés (fractions)
  if (q.groups && q.groups.length) {
    vis.innerHTML = '';
    q.groups.forEach((g) => {
      const box = document.createElement('span');
      box.className = 'visual-group';
      box.textContent = g;
      vis.appendChild(box);
    });
    return;
  }

  vis.textContent = q.visual || '';
}

async function loadQuestion() {
  locked = false;
  $('learn-feedback').textContent = '';
  $('learn-anim').hidden = true;                  // masque l'animation précédente
  $('learn-anim').innerHTML = '';
  $('learn-next').hidden = true;                  // masque le bouton Suivant
  $('learn-count').textContent = `${asked + 1}/${sel.count}`;
  try {
    // On évite de reposer une question identique aux dernières (pas de répétition).
    let q;
    for (let i = 0; i < 8; i++) {
      q = await api.question(sel.topic, ctx.getPetId());
      if (!recentPrompts.includes(q.prompt)) break;
    }
    recentPrompts.push(q.prompt);
    if (recentPrompts.length > 5) recentPrompts.shift();   // on retient les 5 dernières

    currentQ = q;
    currentToken = q.token;
    currentTopic = q.topic;                       // thème concret (résolu si "au hasard")
    currentPrompt = q.prompt;
    currentSay = q.say || '';                     // lettre à dire sans l'afficher
    renderVisual(q);
    $('learn-prompt').textContent = q.prompt;
    sayInstruction();                             // 🔊 lit la consigne (et la lettre au son)

    const box = $('learn-choices');
    box.innerHTML = '';
    q.choices.forEach((c) => {
      const b = document.createElement('button');
      b.className = 'choice';
      b.textContent = c.label;
      b.dataset.value = c.value;                   // pour surligner la bonne réponse
      b.addEventListener('click', () => submit(c.value, b));
      box.appendChild(b);
    });
  } catch (e) {
    $('learn-prompt').textContent = '⚠️ ' + e.message;
  }
}

async function submit(value, btn) {
  if (locked) return;
  locked = true;

  try {
    const res = await api.answer(ctx.getPetId(), currentToken, value, currentTopic);
    asked++;

    if (res.correct) {
      correct++;
      btn.classList.add('good');
      $('learn-score').textContent = String(correct);
      $('learn-feedback').textContent = `🎉 +${res.points_awarded} 💰`;
      speak('Bravo !');
      ctx.onReward(res.pet);
      setTimeout(advance, DELAY_OK);                 // bonne réponse → on enchaîne vite
    } else {
      btn.classList.add('bad');
      $('learn-feedback').textContent = `Réponse : ${res.correct_answer}`;
      guideWrong(currentQ, res.correct_answer);       // explication (pour tous les exercices)
      $('learn-next').hidden = false;                 // l'enfant clique quand il a fini d'écouter
    }
  } catch (e) {
    $('learn-feedback').textContent = '⚠️ ' + e.message;
    locked = false;
  }
}

/* ---------- Guidage universel sur une mauvaise réponse ---------- */

const NUM_WORDS = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix'];

/**
 * Explique la bonne réponse — pour TOUS les exercices et TOUS les âges.
 * 1) surligne le bon bouton en vert  2) explique avec la voix (ou une animation).
 * Retourne le délai à attendre avant la question suivante.
 */
function guideWrong(q, answer) {
  // 1) Surligne la bonne réponse en vert (fonctionne partout)
  document.querySelectorAll('#learn-choices .choice').forEach((b) => {
    if (b.dataset.value === String(answer)) b.classList.add('reveal');
  });

  const type = q.type;
  const p = q.prompt || '';

  // 2) Cas avec animation dédiée (la durée revient synchronisée avec la voix)
  if (type === 'math' && (q.op === '+' || q.op === '−')) {
    return showMathAnimation(q.a, q.b, q.op, Number(answer));
  }
  if (type === 'count' || type === 'count3') {
    return countAloud(q.visual, Number(answer));
  }

  // 3) Une explication PROPRE À CHAQUE exercice (« c'est : X » évite les pièges le/la)
  let text;
  switch (type) {
    case 'color':
      text = `Il fallait trouver la même couleur que le rond. La bonne couleur, c'est : ${answer}.`;
      break;
    case 'shape':
      text = `Regarde bien la forme. La bonne réponse, c'est : ${answer}.`;
      break;
    case 'animal':
      text = `Il fallait trouver le bon animal. C'est : ${answer}.`;
      break;
    case 'food':
      text = `Il fallait trouver le bon aliment. C'est : ${answer}.`;
      break;
    case 'emotion':
      text = `Regarde bien le visage. C'est le visage : ${answer}.`;
      break;
    case 'letter':
    case 'letter_sound':
      text = `Il fallait trouver la lettre ${answer}.`;
      break;
    case 'digit':
      text = `Il fallait trouver le chiffre ${answer}.`;
      break;
    case 'body':
      text = `Il fallait trouver la partie du corps. C'est : ${answer}.`;
      break;
    case 'animalsound':
      text = `Il fallait trouver l'animal qui fait ce cri. C'est celui qui brille en vert.`;
      break;
    case 'nextnum':
      text = p.includes('APRÈS')
        ? `Après, c'est le nombre juste plus grand : ${answer}.`
        : `Avant, c'est le nombre juste plus petit : ${answer}.`;
      break;
    case 'numbig':
      text = p.includes('GRAND')
        ? `Le plus grand nombre, c'est ${answer}.`
        : `Le plus petit nombre, c'est ${answer}.`;
      break;
    case 'opposite':
      text = `Il fallait trouver le contraire. C'est celui qui brille en vert.`;
      break;
    case 'readword':
      text = `${p} Il fallait choisir la bonne image, celle qui brille en vert.`;
      break;
    case 'roman':
      text = `Ce chiffre romain vaut ${answer}.`;
      break;
    case 'measure':
      text = `${p} La réponse est ${answer}.`;
      break;
    case 'time':
      text = `Sur l'horloge, il est ${answer} heures.`;
      break;
    case 'spell':
      text = `Le mot bien écrit, c'est : ${answer}.`;
      break;
    case 'money':
      text = `${p} Ça fait ${answer}.`;
      break;
    case 'decimal':
      text = `La partie coloriée vaut ${answer}. Chaque part colorée, c'est un dixième, donc zéro virgule un.`;
      break;
    case 'evenodd':
      text = answer === 'pair'
        ? `Ce nombre est pair : il se termine par 0, 2, 4, 6 ou 8.`
        : `Ce nombre est impair : il se termine par 1, 3, 5, 7 ou 9.`;
      break;
    case 'categorie':
      text = `${p} La bonne réponse est celle qui brille en vert.`;
      break;
    case 'rang':
      text = p.includes('PREMIER')
        ? `Le premier, c'est celui tout au début de la rangée. Il brille en vert.`
        : `Le dernier, c'est celui tout à la fin de la rangée. Il brille en vert.`;
      break;
    case 'suitenum':
      text = `Dans une suite, les nombres se suivent. Il manquait le nombre ${answer}.`;
      break;
    case 'math': // multiplication / division
      text = `Compte bien. La bonne réponse, c'est le nombre ${answer}.`;
      break;
    case 'pareil':
      text = `Il fallait trouver le dessin exactement pareil au modèle. C'est celui qui brille en vert.`;
      break;
    case 'intrus':
      text = `L'intrus, c'est celui qui n'est pas comme les autres. Regarde celui qui brille en vert : il est différent.`;
      break;
    case 'suite':
      text = `Dans une suite, le même motif se répète toujours. Ce qui vient après, c'est l'image qui brille en vert.`;
      break;
    case 'assoc':
      text = `Il fallait trouver ce qui va ensemble avec l'image. La bonne réponse brille en vert.`;
      break;
    case 'size':
      text = p.includes('GRAND')
        ? `Il fallait trouver le plus GRAND. C'est celui qui brille en vert.`
        : `Il fallait trouver le plus PETIT. C'est celui qui brille en vert.`;
      break;
    case 'compare':
      text = p.includes('plus')
        ? `Il fallait le groupe où il y en a le PLUS. C'est celui qui brille en vert.`
        : `Il fallait le groupe où il y en a le MOINS. C'est celui qui brille en vert.`;
      break;
    default:
      text = `Regarde bien : la bonne réponse est celle qui brille en vert.`;
  }
  speak(text, 0.6);
  return 4800;
}

/**
 * Joue une narration synchronisée : chaque étape { text, action } exécute son
 * effet visuel PILE au moment où le texte est prononcé. Repli minuteur si la
 * voix est coupée. Retourne la durée approximative (ms) de la séquence.
 */
function narrate(steps) {
  const spoke = speakSequence(steps.map((s) => ({ text: s.text, onStart: s.action })), 0.6);
  if (!spoke) {
    // Pas de voix → on joue les effets au rythme d'un minuteur.
    let i = 0;
    const iv = setInterval(() => {
      if (i >= steps.length) { clearInterval(iv); return; }
      if (steps[i].action) steps[i].action();
      i++;
    }, 850);
  }
  return 1500 + steps.length * 900;   // marge avant la question suivante
}

/** Compte les objets un par un, chaque nombre synchronisé avec son jeton. */
function countAloud(visualStr, n) {
  const box = $('learn-anim');
  box.hidden = false;
  const items = [...visualStr];
  box.innerHTML = '<div class="anim-line" id="anim-count"></div><div class="anim-text" id="anim-text"></div>';
  const line = $('anim-count');
  items.forEach((ch) => {
    const s = document.createElement('span');
    s.className = 'tok';
    s.textContent = ch;
    line.appendChild(s);
  });
  const toks = line.querySelectorAll('.tok');

  const steps = [{ text: 'On compte ensemble.' }];
  for (let i = 0; i < n; i++) {
    steps.push({
      text: NUM_WORDS[i + 1] || String(i + 1),
      action: () => { toks[i].classList.add('counted'); $('anim-text').textContent = String(i + 1); },
    });
  }
  steps.push({
    text: `Il y en a ${NUM_WORDS[n] || n} !`,
    action: () => { $('anim-text').textContent = `Il y en a ${n} !`; },
  });
  return narrate(steps);
}

/* ---------- Animation pédagogique du calcul ---------- */

const TOKEN = '🔵';

function tok() {
  const s = document.createElement('span');
  s.className = 'tok';
  s.textContent = TOKEN;
  return s;
}
function fillGroup(el, n) {
  el.innerHTML = '';
  for (let i = 0; i < n; i++) el.appendChild(tok());
}

/** Montre en images ce qui se passe : on rassemble (+) ou on enlève (−), en synchro avec la voix. */
function showMathAnimation(a, b, op, result) {
  const box = $('learn-anim');
  box.hidden = false;

  if (op === '+') {
    box.innerHTML =
      '<div class="anim-line">' +
        '<span class="anim-grp" id="anim-a"></span>' +
        '<span class="anim-op">+</span>' +
        '<span class="anim-grp" id="anim-b"></span>' +
        '<span class="anim-op">=</span>' +
        '<span class="anim-grp anim-res" id="anim-r"></span>' +
      '</div><div class="anim-text" id="anim-text"></div>';
    fillGroup($('anim-a'), a);
    fillGroup($('anim-b'), b);
    const r = $('anim-r');
    r.innerHTML = '';

    const steps = [{ text: `Regarde bien. ${a}, plus ${b}.` }];
    if (result <= 10) {
      steps.push({ text: 'On met tout ensemble, et on compte.', action: () => { $('anim-text').textContent = 'On compte…'; } });
      // Chaque jeton du résultat apparaît pile quand on prononce son nombre.
      for (let i = 0; i < result; i++) {
        steps.push({
          text: NUM_WORDS[i + 1] || String(i + 1),
          action: () => { r.appendChild(tok()); $('anim-text').textContent = String(i + 1); },
        });
      }
      steps.push({ text: `Ça fait ${result} !`, action: () => { $('anim-text').textContent = `Ça fait ${result} !`; } });
    } else {
      steps.push({
        text: `On met tout ensemble. Ça fait ${result} !`,
        action: () => { fillGroup(r, result); $('anim-text').textContent = `Ça fait ${result} !`; },
      });
    }
    return narrate(steps);

  } else {
    box.innerHTML =
      '<div class="anim-line"><span class="anim-grp" id="anim-a"></span></div>' +
      '<div class="anim-text" id="anim-text"></div>';
    fillGroup($('anim-a'), a);
    const toks = $('anim-a').querySelectorAll('.tok');

    const steps = [{ text: `Regarde bien. On a ${a}.` }];
    if (b <= 8) {
      steps.push({ text: `On soustrait. On enlève ${b}.`, action: () => { $('anim-text').textContent = `On enlève ${b}…`; } });
      // Chaque jeton enlevé disparaît pile quand on le compte.
      for (let i = 0; i < b; i++) {
        steps.push({
          text: NUM_WORDS[i + 1] || String(i + 1),
          action: () => { const t = toks[a - 1 - i]; if (t) t.classList.add('gone'); },
        });
      }
    } else {
      steps.push({
        text: `On enlève ${b}.`,
        action: () => { for (let i = 0; i < b; i++) { const t = toks[a - 1 - i]; if (t) t.classList.add('gone'); } $('anim-text').textContent = `On enlève ${b}…`; },
      });
    }
    steps.push({ text: `Il en reste ${result} !`, action: () => { $('anim-text').textContent = `Il en reste ${result} !`; } });
    return narrate(steps);
  }
}

async function finishQuiz() {
  show('learn-result');
  try {
    const res = await api.bonus(ctx.getPetId(), sel.count, correct);
    ctx.onReward(res.pet);

    let txt = `Tu as réussi ${correct}/${sel.count} !\n`;
    txt += `Bonus de fin : +${res.bonus} 💰`;
    if (res.perfect) txt += `\n🌟 SANS FAUTE ! Bonus ×1.5 !`;
    $('result-text').textContent = txt;

    speak(res.perfect
      ? `Incroyable ! Tu as tout bon ! Tu gagnes ${res.bonus} points bonus !`
      : `Bien joué ! Tu as réussi ${correct} sur ${sel.count}. Bonus ${res.bonus} points.`);
  } catch (e) {
    $('result-text').textContent = '⚠️ ' + e.message;
  }
}
