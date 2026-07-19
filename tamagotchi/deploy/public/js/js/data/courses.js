/**
 * Contenu des COURS COMPLETS (page cours.html).
 * Chaque cours : { emoji, title, intro, sections:[{h, p:[], ex:[], table:[]}], tips:[], remember }
 * Beaucoup plus détaillé que les petites leçons du quiz.
 */
export const COURSES = {

  // ================= ÉVEIL =================
  colors: { emoji: '🎨', title: 'Les couleurs', intro:
    "Les couleurs sont partout autour de nous : dans la nature, les vêtements, les objets. Apprendre à les reconnaître aide à décrire le monde.",
    sections: [
      { h: 'Les 3 couleurs primaires', p: ["Ce sont les couleurs de base. On ne peut pas les fabriquer en mélangeant d'autres couleurs."], ex: ['🔴 rouge', '🔵 bleu', '🟡 jaune'] },
      { h: 'Les couleurs mélangées', p: ["En mélangeant deux primaires, on obtient de nouvelles couleurs :"], ex: ['🔴 + 🟡 = 🟠 orange', '🔵 + 🟡 = 🟢 vert', '🔴 + 🔵 = 🟣 violet'] },
      { h: 'Dans l\'exercice', p: ["On te montre un rond coloré. Tu dois trouver la case qui a EXACTEMENT la même couleur."] },
    ],
    tips: ["Ne confonds pas le bleu et le violet : le violet a un peu de rouge.", "Le rose est un rouge très clair."],
    remember: "Rouge, bleu, jaune sont les couleurs de base. Les autres se fabriquent en les mélangeant." },

  shapes: { emoji: '🔷', title: 'Les formes', intro:
    "Une forme géométrique se reconnaît à ses côtés et à ses coins. Les formes sont partout : une fenêtre est un carré, une roue est un rond.",
    sections: [
      { h: 'Reconnaître chaque forme', p: ["Regarde le contour et compte les coins :"], ex: ['⚪ Rond : aucun coin, tout arrondi', '🔺 Triangle : 3 côtés, 3 coins', '🟩 Carré : 4 côtés égaux, 4 coins', '⭐ Étoile : des pointes', '❤️ Cœur : deux bosses en haut'] },
      { h: 'Astuce pour ne pas se tromper', p: ["Compte les côtés : 3 côtés = triangle, 4 côtés = carré. Un rond n'a aucun côté droit."] },
    ],
    tips: ["Un rond roule, un carré ne roule pas (à cause des coins)."],
    remember: "On reconnaît une forme en comptant ses côtés et ses coins." },

  sizes: { emoji: '📏', title: 'Les tailles', intro:
    "Comparer les tailles, c'est dire ce qui est grand et ce qui est petit. C'est le début des mathématiques : comparer.",
    sections: [
      { h: 'Grand et petit', p: ["Le plus GRAND prend le plus de place. Le plus PETIT prend le moins de place."], ex: ['🐘 éléphant : très grand', '🐶 chien : moyen', '🐜 fourmi : très petit'] },
      { h: 'Ranger par taille', p: ["On peut ranger du plus petit au plus grand : 🐜 → 🐶 → 🐘."] },
    ],
    tips: ["Regarde bien TOUTES les images avant de choisir la plus grande ou la plus petite."],
    remember: "Comparer, c'est trouver le plus grand ou le plus petit." },

  animals: { emoji: '🐱', title: 'Les animaux', intro:
    "Il existe des milliers d'animaux, chacun avec son nom, son apparence et son cri.",
    sections: [
      { h: 'Les animaux de la ferme', p: [], ex: ['🐮 la vache', '🐷 le cochon', '🐔 la poule', '🐴 le cheval'] },
      { h: 'Les animaux familiers', p: [], ex: ['🐱 le chat', '🐶 le chien', '🐰 le lapin', '🐟 le poisson'] },
    ],
    tips: ["Écoute bien le nom demandé, puis cherche l'image qui correspond."],
    remember: "Chaque animal a un nom précis. Apprends à les reconnaître." },

  animalsound: { emoji: '🔊', title: 'Les cris des animaux', intro:
    "Chaque animal a un cri, un son bien à lui. C'est comme sa façon de « parler ».",
    sections: [
      { h: 'Les cris à connaître', p: [], ex: ['Le chat 🐱 fait « Miaou »', 'Le chien 🐶 fait « Ouaf »', 'La vache 🐮 fait « Meuh »', 'Le coq 🐓 fait « Cocorico »', 'Le canard 🦆 fait « Coin coin »', 'Le mouton 🐑 fait « Bêê »'] },
    ],
    tips: ["Écoute bien le cri, imagine l'animal, puis choisis."],
    remember: "Le cri de l'animal aide à le reconnaître." },

  foods: { emoji: '🍎', title: 'Les aliments', intro:
    "Les aliments, c'est tout ce que l'on mange. Il y a plusieurs familles d'aliments, et il faut manger de tout pour être en forme.",
    sections: [
      { h: 'Les familles d\'aliments', p: [], ex: ['Fruits : 🍎 🍌 🍓 🍊', 'Légumes : 🥕 🍅 🥦', 'Autres : 🍞 pain, 🧀 fromage, 🐟 poisson'] },
      { h: 'Bien manger', p: ["Pour être fort et en bonne santé, il faut manger beaucoup de fruits et de légumes, et pas trop de sucreries."] },
    ],
    tips: ["Les fruits et légumes donnent de l'énergie et gardent en bonne santé."],
    remember: "On mange de tout, surtout des fruits et des légumes." },

  body: { emoji: '🧍', title: 'Le corps humain', intro:
    "Le corps humain est composé de nombreuses parties, chacune avec un rôle.",
    sections: [
      { h: 'La tête', p: ["Sur le visage on trouve :"], ex: ['👁️ les yeux (pour voir)', '👃 le nez (pour sentir)', '👄 la bouche (pour manger et parler)', '👂 les oreilles (pour entendre)'] },
      { h: 'Le reste du corps', p: [], ex: ['✋ les mains et les doigts (pour attraper)', '🦵 les jambes et 🦶 les pieds (pour marcher)'] },
    ],
    tips: ["Montre chaque partie sur toi-même pour bien la retenir."],
    remember: "Chaque partie du corps a un rôle : voir, entendre, marcher, attraper…" },

  emotions: { emoji: '😊', title: 'Les émotions', intro:
    "Une émotion, c'est ce que l'on ressent à l'intérieur de soi. On peut la lire sur le visage.",
    sections: [
      { h: 'Les émotions de base', p: [], ex: ['😊 content : on sourit', '😢 triste : on a de la peine', '😠 fâché : on est en colère', '😮 surpris : on ne s\'y attendait pas', '😴 fatigué : on a sommeil'] },
      { h: 'Lire un visage', p: ["Regarde surtout les YEUX et la BOUCHE : une bouche vers le haut = content, vers le bas = triste."] },
    ],
    tips: ["Parler de ses émotions aide à se sentir mieux."],
    remember: "On reconnaît une émotion en regardant le visage." },

  opposites: { emoji: '↔️', title: 'Les contraires', intro:
    "Deux contraires, ce sont deux choses complètement opposées. Connaître les contraires aide à mieux comprendre les mots.",
    sections: [
      { h: 'Des exemples de contraires', p: [], ex: ['Chaud 🔥 ↔ froid ❄️', 'Jour ☀️ ↔ nuit 🌙', 'Grand ↔ petit', 'Haut ↔ bas', 'Content 😊 ↔ triste 😢'] },
    ],
    tips: ["Pour trouver le contraire, demande-toi : « l'inverse, c'est quoi ? »"],
    remember: "Le contraire, c'est l'opposé complet." },

  // ================= LOGIQUE =================
  intrus: { emoji: '🔍', title: "Trouver l'intrus", intro:
    "L'intrus est celui qui ne va pas avec les autres. Cet exercice apprend à classer et à repérer les différences.",
    sections: [
      { h: 'La méthode', p: ["1) Regarde tous les objets.", "2) Trouve ce que la PLUPART ont en commun (leur famille).", "3) Celui qui n'est pas de cette famille est l'intrus."] },
      { h: 'Exemple', p: ["🍎 🍌 🍓 🐶 : trois sont des fruits, le chien n'en est pas un. L'intrus est 🐶."] },
    ],
    tips: ["Cherche d'abord le point commun des autres, l'intrus apparaît tout seul."],
    remember: "L'intrus est le seul qui n'est pas comme les autres." },

  pareil: { emoji: '👯', title: 'Trouver le même', intro:
    "Il faut trouver deux dessins exactement identiques. C'est un exercice d'observation.",
    sections: [
      { h: 'La méthode', p: ["Regarde bien le modèle affiché. Puis compare chaque réponse au modèle, détail par détail."] },
    ],
    tips: ["Prends ton temps : compare les détails un par un."],
    remember: "« Le même » = exactement identique au modèle." },

  suite: { emoji: '🧩', title: 'Les suites logiques', intro:
    "Une suite logique est un motif qui se répète toujours de la même façon. C'est la base du raisonnement (les algorithmes).",
    sections: [
      { h: 'Repérer le motif', p: ["Le motif est le petit groupe qui revient."], ex: ['🔴🔵🔴🔵🔴 → le motif est 🔴🔵', '🔺🟡❤️🔺🟡 → le motif est 🔺🟡❤️'] },
      { h: 'Trouver la suite', p: ["Une fois le motif trouvé, devine l'élément qui vient JUSTE APRÈS.", "🔴🔵🔴🔵🔴 ❓ → après 🔴 vient 🔵."] },
    ],
    tips: ["Dis le motif à voix basse en boucle pour sentir ce qui suit."],
    remember: "Trouve le motif qui se répète, puis continue-le." },

  assoc: { emoji: '🔗', title: 'Associer', intro:
    "Associer, c'est relier deux choses qui vont naturellement ensemble.",
    sections: [
      { h: 'Des associations', p: [], ex: ['🐶 le chien va avec 🦴 l\'os', '☔ le parapluie va avec 🌧️ la pluie', '🔑 la clé va avec 🔒 le cadenas', '🐦 l\'oiseau va avec 🥚 l\'œuf'] },
    ],
    tips: ["Demande-toi : « qu'est-ce qui va logiquement avec cette image ? »"],
    remember: "Associer = relier deux choses liées entre elles." },

  compare: { emoji: '⚖️', title: 'Comparer les quantités', intro:
    "Comparer les quantités, c'est trouver où il y a le plus ou le moins d'objets.",
    sections: [
      { h: 'La méthode', p: ["1) Compte chaque groupe.", "2) Le groupe avec le plus grand nombre a « le plus ».", "3) Le groupe avec le plus petit nombre a « le moins »."] },
      { h: 'Exemple', p: ["🍎🍎 (2) et 🍎🍎🍎🍎 (4) : il y a le PLUS dans le groupe de 4."] },
    ],
    tips: ["Compte bien chaque groupe avant de répondre."],
    remember: "« Le plus » = la plus grande quantité. « Le moins » = la plus petite." },

  categorie: { emoji: '🗂️', title: 'Les catégories', intro:
    "Une catégorie est une famille d'objets qui vont ensemble. Classer, c'est ranger par familles.",
    sections: [
      { h: 'Des familles', p: [], ex: ['Les fruits : 🍎 🍌 🍓', 'Les animaux : 🐶 🐱 🐮', 'Les véhicules : 🚗 🚌 🚓', 'Les fleurs : 🌸 🌻 🌷'] },
      { h: 'La méthode', p: ["On te demande un objet d'une famille précise. Élimine ceux qui ne sont pas de cette famille."] },
    ],
    tips: ["« Trouve un fruit » → écarte les animaux et les voitures."],
    remember: "Ranger par catégories, c'est regrouper ce qui se ressemble." },

  rang: { emoji: '🥇', title: 'Premier et dernier', intro:
    "Le rang, c'est la place dans une rangée. On apprend l'ordre et le sens de lecture.",
    sections: [
      { h: 'Le sens de lecture', p: ["On lit toujours de GAUCHE à DROITE, comme un livre."] },
      { h: 'Premier et dernier', p: ["Le PREMIER est tout au début (à gauche). Le DERNIER est tout à la fin (à droite)."], ex: ['🐶 🐱 🐰 🐸 → premier = 🐶, dernier = 🐸'] },
    ],
    tips: ["Pose ton doigt au début de la rangée pour repérer le premier."],
    remember: "On compte de gauche à droite : premier = début, dernier = fin." },

  // ================= LETTRES / LECTURE =================
  letters: { emoji: '🔤', title: "L'alphabet", intro:
    "L'alphabet, ce sont les 26 lettres qui servent à écrire tous les mots.",
    sections: [
      { h: 'Les 26 lettres', p: ["A B C D E F G H I J K L M N O P Q R S T U V W X Y Z."] },
      { h: 'Voyelles et consonnes', p: ["Il y a 6 voyelles : A, E, I, O, U, Y. Les autres lettres sont des consonnes."] },
    ],
    tips: ["Récite l'alphabet dans ta tête pour retrouver une lettre."],
    remember: "L'alphabet a 26 lettres, dont 6 voyelles." },

  letters_sound: { emoji: '🔊', title: 'Écouter les lettres', intro:
    "Chaque lettre a un nom que l'on entend. Ici, la lettre est DITE mais pas montrée.",
    sections: [
      { h: 'Le nom des lettres', p: ["A se dit « a », B se dit « bé », C se dit « cé », D « dé »…"] },
      { h: 'La méthode', p: ["Écoute bien le son de la lettre, puis retrouve-la parmi les réponses écrites."] },
    ],
    tips: ["Tu peux réécouter la lettre avec le bouton 🔊."],
    remember: "Écoute le nom de la lettre, puis retrouve sa forme écrite." },

  readword: { emoji: '📖', title: 'Lire un mot', intro:
    "Lire, c'est assembler les lettres et les sons pour former un mot.",
    sections: [
      { h: 'La méthode', p: ["1) Regarde le mot.", "2) Lis-le lettre par lettre, doucement.", "3) Assemble les sons : c-h-a-t → « chat ».", "4) Choisis l'image qui correspond."] },
    ],
    tips: ["Prononce le mot à voix basse : ça aide à le reconnaître."],
    remember: "Lire = assembler les lettres pour dire le mot, puis trouver l'image." },

  spell: { emoji: '✍️', title: "L'orthographe", intro:
    "L'orthographe, c'est écrire les mots correctement, sans faute.",
    sections: [
      { h: 'La méthode', p: ["Un seul mot proposé est BIEN écrit. Les autres ont une erreur : une lettre en trop, en moins, ou changée."] },
      { h: 'Exemple', p: ["« maison » est correct. « mèzon » est faux (le son est le même mais les lettres sont fausses)."] },
    ],
    tips: ["Lis chaque mot dans ta tête et repère l'erreur."],
    remember: "Un mot bien orthographié s'écrit avec les bonnes lettres." },

  // ================= NOMBRES =================
  count3: { emoji: '🔢', title: 'Compter', intro:
    "Compter, c'est dire les nombres dans l'ordre en associant un nombre à chaque objet.",
    sections: [
      { h: 'La méthode', p: ["1) Pointe le premier objet et dis « un ».", "2) Pointe le suivant et dis « deux », etc.", "3) Ne compte jamais deux fois le même.", "4) Le dernier nombre dit est le total."] },
    ],
    tips: ["Pointe chaque objet avec ton doigt pour n'en oublier aucun."],
    remember: "Un objet = un nombre. Le dernier nombre dit = le total." },

  count: { emoji: '🔢', title: 'Compter (plus loin)', intro:
    "On compte des collections plus grandes. Le principe reste le même.",
    sections: [
      { h: 'La méthode', p: ["Un objet = un nombre, dans l'ordre, sans en oublier ni en compter deux fois."] },
      { h: 'Aller plus vite', p: ["Pour de grandes quantités, regroupe par paquets de 5 ou de 10 puis compte les paquets."] },
    ],
    tips: ["5 + 5 + 3 se compte vite : 5, 10, puis 11, 12, 13."],
    remember: "Regrouper par 5 ou 10 aide à compter vite." },

  digits: { emoji: '🔟', title: 'Les chiffres', intro:
    "Les chiffres sont les symboles qui servent à écrire les nombres.",
    sections: [
      { h: 'Les 10 chiffres', p: ["Il y en a dix : 0, 1, 2, 3, 4, 5, 6, 7, 8, 9. Avec eux, on écrit tous les nombres."] },
      { h: 'Ne pas confondre', p: ["6 et 9 se ressemblent (retournés). 2 et 5 aussi. Regarde bien le sens."] },
    ],
    tips: ["Un nombre à deux chiffres (comme 12) est fait de deux chiffres."],
    remember: "Avec 10 chiffres (0 à 9), on écrit tous les nombres." },

  nextnum: { emoji: '🔢', title: 'Avant / Après', intro:
    "Les nombres se suivent toujours dans le même ordre. On apprend le nombre juste avant et juste après.",
    sections: [
      { h: 'Après = +1', p: ["Le nombre APRÈS est le suivant, juste plus grand.", "Après 4, c'est 5. Après 9, c'est 10."] },
      { h: 'Avant = −1', p: ["Le nombre AVANT est le précédent, juste plus petit.", "Avant 4, c'est 3. Avant 10, c'est 9."] },
    ],
    tips: ["Compte sur tes doigts pour vérifier."],
    remember: "Après = +1 (plus grand). Avant = −1 (plus petit)." },

  numbig: { emoji: '🔢', title: 'Comparer les nombres', intro:
    "Comparer, c'est dire quel nombre est le plus grand ou le plus petit.",
    sections: [
      { h: 'Petits nombres', p: ["Plus un nombre est loin dans le comptage, plus il est grand. 9 > 3."] },
      { h: 'Grands nombres', p: ["Compte d'abord les chiffres : plus il y a de chiffres, plus le nombre est grand.", "25 > 9 (25 a deux chiffres). Si même nombre de chiffres, compare le premier chiffre : 52 > 25."] },
    ],
    tips: ["Un nombre à 3 chiffres est toujours plus grand qu'un nombre à 2 chiffres."],
    remember: "Plus il y a de chiffres, plus le nombre est grand." },

  suitenum: { emoji: '🔢', title: 'Le nombre manquant', intro:
    "Dans une suite de nombres qui se suivent, un nombre est caché. Il faut le retrouver.",
    sections: [
      { h: 'La méthode', p: ["Les nombres se suivent : 1, 2, 3, 4… Regarde les nombres autour du ❓ pour trouver le trou.", "Exemple : 3, ❓, 5 → il manque 4."] },
    ],
    tips: ["Compte à voix basse en repartant du début de la suite."],
    remember: "Le nombre manquant continue la suite : chaque nombre est +1 par rapport au précédent." },

  evenodd: { emoji: '🔢', title: 'Pair ou impair', intro:
    "Tous les nombres sont soit pairs, soit impairs. C'est une notion importante en mathématiques.",
    sections: [
      { h: 'Les nombres pairs', p: ["Un nombre PAIR peut se partager en 2 parts égales, sans reste.", "Ils se terminent par 0, 2, 4, 6 ou 8.", "Exemples : 2, 4, 10, 28, 100."] },
      { h: 'Les nombres impairs', p: ["Un nombre IMPAIR laisse toujours un tout seul quand on partage en 2.", "Ils se terminent par 1, 3, 5, 7 ou 9.", "Exemples : 1, 3, 7, 25, 99."] },
    ],
    tips: ["Regarde SEULEMENT le dernier chiffre pour décider !"],
    remember: "Le dernier chiffre décide : pair (0,2,4,6,8) ou impair (1,3,5,7,9)." },

  complement: { emoji: '🎯', title: 'Compléter à 10 ou 100', intro:
    "Compléter, c'est trouver combien il manque pour atteindre un nombre rond (10 ou 100).",
    sections: [
      { h: 'Compléter à 10', p: ["7 + ? = 10 → il manque 3.", "Les compléments à 10 à connaître par cœur : 1+9, 2+8, 3+7, 4+6, 5+5."] },
      { h: 'Compléter à 100', p: ["70 + ? = 100 → il manque 30.", "On peut aussi compléter les dizaines : 30 + 70 = 100."] },
    ],
    tips: ["Connaître les compléments à 10 par cœur rend le calcul très rapide."],
    remember: "Compléter = trouver ce qui manque pour aller à 10 ou à 100." },

  // ================= CALCUL =================
  addition: { emoji: '➕', title: "L'addition", intro:
    "Additionner, c'est réunir deux quantités pour en faire une seule plus grande. Le signe est + (« plus »).",
    sections: [
      { h: 'Le principe', p: ["On met les deux nombres ensemble et on compte le total.", "2 + 3 = 5 : on réunit 2 objets et 3 objets, ça fait 5 objets."] },
      { h: 'Compter en avançant', p: ["Pour 2 + 3, pars de 2 et avance de 3 : 3, 4, 5. Résultat : 5."] },
    ],
    tips: ["L'ordre ne change rien : 2 + 3 = 3 + 2. Commence par le plus grand nombre pour aller plus vite."],
    remember: "Additionner (+) = mettre ensemble. Le résultat est plus grand." },

  subtraction: { emoji: '➖', title: 'La soustraction', intro:
    "Soustraire, c'est enlever une quantité à une autre. Le signe est − (« moins »).",
    sections: [
      { h: 'Le principe', p: ["On part d'un nombre et on en enlève une partie.", "5 − 2 = 3 : on avait 5, on enlève 2, il reste 3."] },
      { h: 'Compter en reculant', p: ["Pour 5 − 2, pars de 5 et recule de 2 : 4, 3. Résultat : 3."] },
    ],
    tips: ["Attention : dans la soustraction, l'ordre compte ! 5 − 2 n'est pas pareil que 2 − 5."],
    remember: "Soustraire (−) = enlever. Le résultat est plus petit." },

  addsub: { emoji: '➕➖', title: 'Additions et soustractions', intro:
    "Il faut savoir reconnaître le signe et choisir la bonne opération.",
    sections: [
      { h: 'Deux opérations opposées', p: ["+ (plus) : on ajoute, on met ensemble → le résultat grandit.", "− (moins) : on enlève, on retire → le résultat diminue."] },
      { h: 'La règle d\'or', p: ["Regarde toujours le SIGNE avant de calculer."] },
    ],
    tips: ["+ fait grandir, − fait diminuer."],
    remember: "Le signe indique l'opération : + on ajoute, − on enlève." },

  double: { emoji: '✖️2', title: 'Les doubles', intro:
    "Le double d'un nombre, c'est ce nombre pris deux fois. C'est très utile pour calculer vite.",
    sections: [
      { h: 'Calculer un double', p: ["Doubler = additionner le nombre avec lui-même, ou multiplier par 2.", "Double de 4 = 4 + 4 = 8.", "Double de 6 = 12, double de 10 = 20."] },
      { h: 'Les doubles à connaître', p: ["1→2, 2→4, 3→6, 4→8, 5→10, 6→12, 7→14, 8→16, 9→18, 10→20."] },
    ],
    tips: ["Connaître les doubles par cœur aide pour toutes les additions."],
    remember: "Le double = le nombre + lui-même (× 2)." },

  half: { emoji: '✂️', title: 'La moitié', intro:
    "La moitié, c'est partager en deux parts égales. C'est le contraire du double.",
    sections: [
      { h: 'Calculer une moitié', p: ["On coupe le nombre en deux parts identiques.", "Moitié de 8 = 4 (car 4 + 4 = 8).", "Moitié de 10 = 5, moitié de 20 = 10."] },
      { h: 'Lien avec le double', p: ["Si le double de 4 est 8, alors la moitié de 8 est 4."] },
    ],
    tips: ["On ne peut prendre la moitié entière que d'un nombre pair."],
    remember: "La moitié = partager en 2 (le contraire du double)." },

  triple: { emoji: '✖️3', title: 'Le triple', intro:
    "Le triple d'un nombre, c'est ce nombre pris trois fois.",
    sections: [
      { h: 'Calculer un triple', p: ["Tripler = additionner trois fois, ou multiplier par 3.", "Triple de 2 = 2 + 2 + 2 = 6.", "Triple de 4 = 12, triple de 5 = 15."] },
    ],
    tips: ["Tripler, c'est comme la table de 3."],
    remember: "Le triple = le nombre × 3." },

  muldiv: { emoji: '✖️', title: 'Multiplier et diviser', intro:
    "La multiplication et la division sont deux opérations opposées, très utiles au quotidien.",
    sections: [
      { h: 'La multiplication (×)', p: ["Multiplier, c'est additionner plusieurs fois le même nombre.", "3 × 4 = 4 + 4 + 4 = 12 (3 paquets de 4)."] },
      { h: 'La division (÷)', p: ["Diviser, c'est partager en parts égales.", "12 ÷ 4 = 3 (12 partagés en 4 groupes de 3)."] },
      { h: 'Les tables', p: ["Apprends les tables de multiplication (× 2, × 3, … × 9) par cœur : c'est la clé du calcul."] },
    ],
    tips: ["× et ÷ sont contraires : si 3 × 4 = 12, alors 12 ÷ 4 = 3."],
    remember: "Multiplier = additions répétées. Diviser = partager en parts égales." },

  longmult: { emoji: '✖️', title: 'Multiplications à deux chiffres', intro:
    "On multiplie des nombres plus grands en décomposant le calcul.",
    sections: [
      { h: 'La méthode par décomposition', p: ["Sépare le grand nombre en dizaines + unités.", "14 × 3 = (10 × 3) + (4 × 3) = 30 + 12 = 42."] },
    ],
    tips: ["Décomposer rend les grandes multiplications faciles."],
    remember: "On sépare dizaines et unités, on multiplie chaque partie, puis on additionne." },

  division: { emoji: '➗', title: 'Les divisions', intro:
    "Diviser, c'est partager équitablement en parts égales.",
    sections: [
      { h: 'Le principe', p: ["20 ÷ 4 : on partage 20 objets en 4 groupes égaux → 5 dans chaque groupe."] },
      { h: 'Lien avec la multiplication', p: ["Pour trouver 20 ÷ 4, cherche : « 4 × combien = 20 ? » → 4 × 5 = 20, donc 20 ÷ 4 = 5."] },
    ],
    tips: ["Utilise les tables de multiplication à l'envers."],
    remember: "Diviser = partager en parts égales. C'est l'inverse de multiplier." },

  problem: { emoji: '📝', title: 'Les problèmes', intro:
    "Un problème raconte une petite histoire avec des nombres. Il faut comprendre puis calculer.",
    sections: [
      { h: 'Les 3 étapes', p: ["1) Lis bien et comprends l'histoire.", "2) Trouve l'opération : addition, soustraction ou multiplication ?", "3) Calcule, puis écris la réponse."] },
      { h: 'Les mots-clés', p: ["« il en perd », « il en donne », « il en mange » → soustraction (−).", "« on lui en donne », « il en gagne » → addition (+).", "« paquets de », « fois » → multiplication (×)."] },
    ],
    tips: ["Relis l'histoire si tu hésites sur l'opération."],
    remember: "Comprendre l'histoire → choisir l'opération → calculer." },

  square: { emoji: '²', title: 'Les carrés (puissances)', intro:
    "Le carré d'un nombre, c'est ce nombre multiplié par lui-même. On l'écrit avec un petit 2.",
    sections: [
      { h: 'Notation', p: ["5² se lit « 5 au carré » et veut dire 5 × 5.", "5² = 5 × 5 = 25."] },
      { h: 'Les carrés à connaître', p: ["2²=4, 3²=9, 4²=16, 5²=25, 6²=36, 7²=49, 8²=64, 9²=81, 10²=100."] },
    ],
    tips: ["« Au carré » veut toujours dire « fois lui-même »."],
    remember: "n² = n × n." },

  priorities: { emoji: '✖️➕', title: 'Les priorités opératoires', intro:
    "Quand un calcul mélange plusieurs opérations, il y a un ORDRE à respecter, sinon on se trompe.",
    sections: [
      { h: 'La règle', p: ["1) On calcule d'abord les MULTIPLICATIONS et DIVISIONS (× et ÷).", "2) Ensuite seulement les ADDITIONS et SOUSTRACTIONS (+ et −)."] },
      { h: 'Exemple détaillé', p: ["3 + 4 × 2 :", "→ d'abord 4 × 2 = 8", "→ puis 3 + 8 = 11.", "La bonne réponse est 11."] },
      { h: 'Le piège classique', p: ["Beaucoup calculent de gauche à droite : 3 + 4 = 7, puis 7 × 2 = 14. C'est FAUX !"] },
    ],
    tips: ["Souligne les × et ÷ et fais-les en premier."],
    remember: "× et ÷ d'abord, + et − ensuite." },

  perimeter: { emoji: '📐', title: 'Le périmètre', intro:
    "Le périmètre, c'est la longueur du contour d'une figure, le chemin tout autour.",
    sections: [
      { h: 'Le principe', p: ["On fait le tour de la figure en additionnant tous les côtés."] },
      { h: 'Les formules', p: ["Carré (4 côtés égaux) : Périmètre = 4 × côté.", "Rectangle : Périmètre = 2 × (Longueur + largeur)."], ex: ['Carré de côté 5 : 4 × 5 = 20', 'Rectangle 6 × 4 : 2 × (6+4) = 20'] },
    ],
    tips: ["Imagine que tu marches tout autour de la figure : la distance parcourue, c'est le périmètre."],
    remember: "Périmètre = le TOUR de la figure (on additionne les côtés)." },

  aire: { emoji: '🟦', title: "L'aire", intro:
    "L'aire, c'est la mesure de la surface à l'intérieur d'une figure, la place qu'elle occupe.",
    sections: [
      { h: 'Le principe', p: ["On compte combien de petits carrés remplissent l'intérieur, comme du carrelage."] },
      { h: 'Les formules', p: ["Carré : Aire = côté × côté.", "Rectangle : Aire = Longueur × largeur."], ex: ['Carré de côté 4 : 4 × 4 = 16', 'Rectangle 6 × 3 : 6 × 3 = 18'] },
    ],
    tips: ["Ne confonds pas : le PÉRIMÈTRE c'est le tour, l'AIRE c'est l'intérieur."],
    remember: "Aire = la SURFACE intérieure (Longueur × largeur)." },

  relative: { emoji: '🌡️', title: 'Les nombres relatifs', intro:
    "Les nombres relatifs comprennent les nombres positifs ET les nombres négatifs (plus petits que zéro).",
    sections: [
      { h: 'La droite des nombres', p: ["… −3, −2, −1, 0, 1, 2, 3 …", "À gauche du 0 : les négatifs. À droite du 0 : les positifs."] },
      { h: 'Exemple avec la température', p: ["Il fait −3°. La température monte de 5°.", "On avance sur la droite : −3 → −2 → −1 → 0 → 1 → 2.", "Il fait maintenant 2°."] },
    ],
    tips: ["Monter = avancer vers la droite. Descendre = reculer vers la gauche."],
    remember: "Les négatifs sont sous zéro. On se déplace sur une droite graduée." },

  fraction: { emoji: '½', title: 'Les fractions', intro:
    "Une fraction, c'est une part d'un tout que l'on a partagé en parts égales.",
    sections: [
      { h: 'Le vocabulaire', p: ["La moitié = partagé en 2. Le tiers = en 3. Le quart = en 4. Le cinquième = en 5."] },
      { h: 'Calculer une fraction d\'un nombre', p: ["Le tiers de 6 : on partage 6 en 3 groupes égaux → 2 dans chaque groupe. Donc le tiers de 6 = 2.", "Dans l'exercice, chaque groupe est encadré : une part = un groupe."] },
    ],
    tips: ["Plus on partage en parts (dénominateur grand), plus chaque part est petite."],
    remember: "Une fraction = une part d'un tout partagé en parts égales." },

  decimals: { emoji: '🔟', title: 'Les nombres décimaux', intro:
    "Un nombre décimal a une virgule. Ce qui est après la virgule, ce sont des parties d'un entier.",
    sections: [
      { h: 'Les dixièmes', p: ["Si on partage 1 entier en 10 parts égales, chaque part vaut 0,1 (un dixième).", "La barre de l'exercice est partagée en 10. Chaque case verte = 0,1."] },
      { h: 'Lire la barre', p: ["3 cases vertes = 0,3.", "5 cases = 0,5 (la moitié).", "10 cases = 1 entier."] },
    ],
    tips: ["0,5 c'est la moitié ; 0,25 c'est le quart."],
    remember: "Après la virgule, ce sont les dixièmes. 10 dixièmes = 1 entier." },

  // ================= À L'ANCIENNE =================
  roman: { emoji: 'Ⅻ', title: 'Les chiffres romains', intro:
    "Il y a très longtemps, les Romains écrivaient les nombres avec des lettres. On les voit encore aujourd'hui (sur les horloges, les siècles…).",
    sections: [
      { h: 'Les 7 symboles', p: ["Chaque symbole a une valeur :"], ex: ['I = 1', 'V = 5', 'X = 10', 'L = 50', 'C = 100', 'D = 500', 'M = 1000'] },
      { h: 'La règle d\'addition', p: ["Quand on écrit des symboles du plus grand au plus petit, on les ADDITIONNE.", "VII = 5 + 1 + 1 = 7.", "XV = 10 + 5 = 15."] },
      { h: 'La règle de soustraction', p: ["Quand un petit symbole est placé AVANT un plus grand, on le SOUSTRAIT.", "IV = 5 − 1 = 4.", "IX = 10 − 1 = 9.", "XL = 50 − 10 = 40.", "XC = 100 − 10 = 90."] },
    ],
    tips: ["On ne répète jamais un symbole plus de 3 fois : 4 s'écrit IV, pas IIII."],
    remember: "Du grand au petit : on additionne. Un petit avant un grand : on soustrait." },

  measure: { emoji: '📏', title: 'Les mesures (système métrique)', intro:
    "Pour mesurer des longueurs, des masses, du temps ou de l'argent, on utilise des unités qu'il faut connaître.",
    sections: [
      { h: 'Les longueurs', p: ["1 mètre (m) = 100 centimètres (cm).", "1 kilomètre (km) = 1000 mètres."] },
      { h: 'Les masses', p: ["1 kilogramme (kg) = 1000 grammes (g)."] },
      { h: 'Le temps', p: ["1 heure = 60 minutes.", "1 minute = 60 secondes.", "1 semaine = 7 jours."] },
      { h: 'L\'argent', p: ["1 euro = 100 centimes."] },
    ],
    tips: ["« kilo » veut toujours dire 1000 (kilomètre = 1000 m, kilogramme = 1000 g)."],
    remember: "Retiens les conversions : 100 cm dans 1 m, 1000 m dans 1 km, 60 min dans 1 h." },

  time: { emoji: '🕐', title: "Lire l'heure", intro:
    "Une horloge sert à lire l'heure grâce à deux aiguilles qui tournent.",
    sections: [
      { h: 'Les deux aiguilles', p: ["La PETITE aiguille indique les HEURES.", "La GRANDE aiguille indique les MINUTES."] },
      { h: 'L\'heure pile', p: ["Quand la grande aiguille est sur le 12, c'est une heure pile.", "🕒 : la petite aiguille est sur le 3 → il est 3 heures."] },
    ],
    tips: ["Regarde d'abord où pointe la petite aiguille pour connaître l'heure."],
    remember: "Petite aiguille = heures, grande aiguille = minutes." },

  money: { emoji: '🪙', title: 'La monnaie', intro:
    "La monnaie sert à payer. Elle est faite de pièces et de billets de différentes valeurs.",
    sections: [
      { h: 'Compter des pièces', p: ["Pour connaître la somme totale, on additionne la valeur de chaque pièce.", "3 pièces de 2 € = 2 + 2 + 2 = 6 €."] },
      { h: 'Aller plus vite', p: ["Avec des pièces identiques, on peut compter par bonds : 2, 4, 6, 8… ou 5, 10, 15…"] },
    ],
    tips: ["Compte de 2 en 2 ou de 5 en 5 selon les pièces."],
    remember: "Le total = la somme des valeurs de toutes les pièces." },

  // ================= COLLÈGE 5ème =================
  percent: { emoji: '%', title: 'Les pourcentages', intro:
    "Un pourcentage, c'est une part sur 100. « 50 % » veut dire 50 sur 100, c'est-à-dire la moitié.",
    sections: [
      { h: 'Les pourcentages à connaître', p: ["50 % = la moitié (÷ 2).", "25 % = le quart (÷ 4).", "10 % = un dixième (÷ 10).", "100 % = le tout."] },
      { h: 'Calculer un pourcentage d\'un nombre', p: ["Méthode : (nombre × pourcentage) ÷ 100.", "50 % de 20 = (20 × 50) ÷ 100 = 10.", "10 % de 50 = (50 × 10) ÷ 100 = 5."] },
    ],
    tips: ["Pour 10 %, il suffit de diviser par 10. Pour 50 %, divise par 2."],
    remember: "x % d'un nombre = (nombre × x) ÷ 100." },

  airetri: { emoji: '🔺', title: "L'aire du triangle", intro:
    "L'aire d'un triangle se calcule à partir de sa base et de sa hauteur.",
    sections: [
      { h: 'La formule', p: ["Aire = (base × hauteur) ÷ 2.", "On multiplie la base par la hauteur, puis on divise par 2."] },
      { h: 'Exemple', p: ["Base = 6, hauteur = 4 : Aire = (6 × 4) ÷ 2 = 24 ÷ 2 = 12."] },
    ],
    tips: ["Un triangle, c'est la moitié d'un rectangle : d'où le « ÷ 2 »."],
    remember: "Aire du triangle = (base × hauteur) ÷ 2." },

  priorpar: { emoji: '()', title: 'Les priorités et les parenthèses', intro:
    "Dans un calcul, il y a un ordre. Les parenthèses changent cet ordre : on les calcule EN PREMIER.",
    sections: [
      { h: 'La règle complète', p: ["1) D'abord ce qui est entre PARENTHÈSES.", "2) Puis les × et les ÷.", "3) Enfin les + et les −."] },
      { h: 'Exemple', p: ["(3 + 2) × 4 :", "→ d'abord la parenthèse : 3 + 2 = 5", "→ puis 5 × 4 = 20."] },
    ],
    tips: ["Sans les parenthèses, 3 + 2 × 4 = 3 + 8 = 11. Avec, (3+2) × 4 = 20 !"],
    remember: "Les parenthèses se calculent toujours en premier." },

  relatadd: { emoji: '±', title: 'Additionner les nombres relatifs', intro:
    "On additionne des nombres qui peuvent être positifs (+) ou négatifs (−).",
    sections: [
      { h: 'Deux nombres de même signe', p: ["On additionne et on garde le signe.", "(−3) + (−4) = −7 (on va plus loin dans les négatifs)."] },
      { h: 'Deux nombres de signes différents', p: ["On fait la différence et on garde le signe du plus grand.", "(−3) + 5 = +2.", "3 + (−5) = −2."] },
    ],
    tips: ["Imagine un thermomètre : + fait monter, − fait descendre."],
    remember: "Même signe : on ajoute. Signes différents : on soustrait." },

  // ================= COLLÈGE 4ème =================
  power: { emoji: '^', title: 'Les puissances', intro:
    "Une puissance, c'est une multiplication d'un nombre par lui-même, plusieurs fois.",
    sections: [
      { h: 'Notation', p: ["2³ se lit « 2 puissance 3 » et veut dire 2 × 2 × 2 = 8.", "Le petit nombre (l'exposant) dit combien de fois on multiplie."] },
      { h: 'Exemples', p: ["2⁴ = 2 × 2 × 2 × 2 = 16.", "10² = 10 × 10 = 100.", "10³ = 1000.", "5² = 25."] },
    ],
    tips: ["10 puissance n = 1 suivi de n zéros. 10³ = 1000."],
    remember: "aⁿ = a multiplié n fois par lui-même." },

  relatmul: { emoji: '±', title: 'Multiplier les nombres relatifs', intro:
    "Pour multiplier des relatifs, il faut connaître la règle des signes.",
    sections: [
      { h: 'La règle des signes', p: ["(+) × (+) = (+)", "(−) × (−) = (+)  → deux négatifs donnent un positif !", "(+) × (−) = (−)", "(−) × (+) = (−)"] },
      { h: 'Exemples', p: ["(−3) × 4 = −12.", "(−3) × (−4) = +12.", "5 × (−2) = −10."] },
    ],
    tips: ["Deux signes PAREILS → résultat positif. Deux signes DIFFÉRENTS → résultat négatif."],
    remember: "Même signe = +, signes différents = −." },

  expand: { emoji: 'x', title: 'Le calcul littéral (développer)', intro:
    "En algèbre, on utilise la lettre x pour un nombre inconnu. Développer, c'est enlever les parenthèses.",
    sections: [
      { h: 'La distributivité', p: ["a × (x + b) = a × x + a × b.", "On multiplie a par CHAQUE terme de la parenthèse."] },
      { h: 'Exemple', p: ["3 × (x + 4) = 3x + 12.", "(car 3 × x = 3x et 3 × 4 = 12)"] },
    ],
    tips: ["N'oublie jamais de multiplier par le DEUXIÈME terme aussi !"],
    remember: "a(x + b) = ax + ab." },

  // ================= COLLÈGE 3ème =================
  sqrt: { emoji: '√', title: 'Les racines carrées', intro:
    "La racine carrée est l'opération inverse du carré. √9 = 3, car 3² = 9.",
    sections: [
      { h: 'Le principe', p: ["√ d'un nombre = le nombre qui, mis au carré, donne ce nombre.", "√49 = 7, car 7 × 7 = 49."] },
      { h: 'Les racines à connaître', p: ["√1=1, √4=2, √9=3, √16=4, √25=5, √36=6, √49=7, √64=8, √81=9, √100=10."] },
    ],
    tips: ["Apprends les carrés par cœur : ils donnent directement les racines."],
    remember: "√n = le nombre dont le carré vaut n." },

  equation: { emoji: '=', title: 'Les équations', intro:
    "Une équation, c'est une égalité avec un nombre inconnu (x). Résoudre, c'est trouver x.",
    sections: [
      { h: 'Équation avec une addition', p: ["x + 5 = 12.", "Pour isoler x, on enlève 5 des deux côtés : x = 12 − 5 = 7."] },
      { h: 'Équation avec une multiplication', p: ["2x = 10.", "Pour isoler x, on divise par 2 des deux côtés : x = 10 ÷ 2 = 5."] },
    ],
    tips: ["Ce qu'on fait d'un côté du =, on le fait de l'autre côté aussi."],
    remember: "Résoudre = isoler x en faisant l'opération inverse." },

  pythagore: { emoji: '📐', title: 'Le théorème de Pythagore', intro:
    "Dans un triangle RECTANGLE, ce théorème relie les longueurs des trois côtés.",
    sections: [
      { h: 'Le théorème', p: ["Le plus grand côté (l'hypoténuse) est en face de l'angle droit.", "Formule : hypoténuse² = côté1² + côté2²."] },
      { h: 'Exemple célèbre (3, 4, 5)', p: ["Côtés 3 et 4 : 3² + 4² = 9 + 16 = 25.", "L'hypoténuse² = 25, donc l'hypoténuse = √25 = 5."] },
      { h: 'Autres triangles à connaître', p: ["(6, 8, 10), (5, 12, 13), (8, 15, 17)."] },
    ],
    tips: ["Le 3-4-5 est le triangle rectangle le plus connu."],
    remember: "Dans un triangle rectangle : hypoténuse² = côté1² + côté2²." },

  comparedec: { emoji: '🔟', title: 'Comparer les nombres décimaux', intro:
    "Comparer des décimaux (avec une virgule) demande d'être attentif : ce n'est pas comme les entiers !",
    sections: [
      { h: 'La méthode', p: ["On compare d'abord la partie entière (avant la virgule).", "Si elle est égale (0 = 0), on compare les dixièmes, puis les centièmes."] },
      { h: 'Le piège', p: ["0,7 est plus grand que 0,45 !", "Car 7 dixièmes (0,70) > 4 dixièmes (0,45).", "Ne te fie pas au nombre de chiffres après la virgule."] },
    ],
    tips: ["Astuce : ajoute des zéros pour avoir le même nombre de décimales. 0,7 = 0,70, et 0,70 > 0,45."],
    remember: "On compare rang par rang après la virgule, pas la longueur du nombre." },

  probpercent: { emoji: '📝', title: 'Problèmes de pourcentage', intro:
    "Beaucoup de problèmes de la vie utilisent des pourcentages : réductions, statistiques, parts…",
    sections: [
      { h: 'La méthode', p: ["1) Repère le pourcentage et le nombre total.", "2) Calcule : (total × pourcentage) ÷ 100."] },
      { h: 'Exemple', p: ["Dans une classe de 30 élèves, 20 % font du judo.", "20 % de 30 = (30 × 20) ÷ 100 = 6 élèves."] },
    ],
    tips: ["Une réduction de 25 %, c'est enlever le quart du prix."],
    remember: "Part = (total × pourcentage) ÷ 100." },

  probpropor: { emoji: '📝', title: 'Problèmes de proportionnalité', intro:
    "Quand des quantités sont proportionnelles (prix, recettes…), on utilise la règle de proportionnalité.",
    sections: [
      { h: 'La méthode', p: ["1) Trouve la valeur pour 1 (le prix unitaire).", "2) Multiplie par la quantité cherchée."] },
      { h: 'Exemple', p: ["3 croissants coûtent 6 €.", "Prix d'un croissant : 6 ÷ 3 = 2 €.", "5 croissants : 2 × 5 = 10 €."] },
    ],
    tips: ["Passe toujours par la valeur d'un seul objet."],
    remember: "Valeur pour 1, puis on multiplie par la quantité." },

  thales: { emoji: '📐', title: 'Le théorème de Thalès', intro:
    "Le théorème de Thalès sert à calculer des longueurs quand on a deux droites coupées par des parallèles : les longueurs sont proportionnelles.",
    sections: [
      { h: 'L\'idée', p: ["Quand deux triangles sont « emboîtés » avec des côtés parallèles, leurs côtés sont proportionnels.", "On a l'égalité de rapports : AB/AC = AD/AE."] },
      { h: 'Calculer une longueur', p: ["Si AB = 2, AC = 4, AD = 3, on cherche AE.", "Le rapport est AC/AB = 4/2 = 2.", "Donc AE = AD × 2 = 3 × 2 = 6."] },
    ],
    tips: ["Trouve d'abord le rapport (coefficient) entre les deux longueurs connues."],
    remember: "Avec Thalès, les longueurs sont proportionnelles : AB/AC = AD/AE." },

  diveucl: { emoji: '➗', title: 'La division euclidienne', intro:
    "Diviser ne tombe pas toujours juste. Ce qui reste après le partage s'appelle le RESTE.",
    sections: [
      { h: 'Le principe', p: ["17 ÷ 5 : 5 entre 3 fois dans 17 (5 × 3 = 15), et il reste 2.", "On écrit : 17 = 5 × 3 + 2. Le quotient est 3, le reste est 2."] },
      { h: 'La règle du reste', p: ["Le reste est toujours plus PETIT que le diviseur.", "Pour ÷ 5, le reste est entre 0 et 4."] },
    ],
    tips: ["Cherche le plus grand multiple du diviseur en dessous du nombre."],
    remember: "nombre = diviseur × quotient + reste (reste < diviseur)." },

  multiple: { emoji: '🔢', title: 'Les multiples', intro:
    "Les multiples d'un nombre sont obtenus en le multipliant par 1, 2, 3, 4…",
    sections: [
      { h: 'Trouver les multiples', p: ["Les multiples de 4 : 4, 8, 12, 16, 20, 24…", "Le 3ᵉ multiple de 4 = 4 × 3 = 12."] },
      { h: 'Reconnaître un multiple', p: ["Un multiple de 5 se termine par 0 ou 5.", "Un multiple de 2 est un nombre pair."] },
    ],
    tips: ["Les multiples d'un nombre, c'est sa table de multiplication."],
    remember: "Le nᵉ multiple d'un nombre = ce nombre × n." },

  roundten: { emoji: '≈', title: 'Arrondir un nombre', intro:
    "Arrondir, c'est remplacer un nombre par le nombre rond le plus proche (une dizaine, une centaine…).",
    sections: [
      { h: 'Arrondir à la dizaine', p: ["On regarde le chiffre des unités.", "S'il est 0,1,2,3,4 → on arrondit vers le BAS.", "S'il est 5,6,7,8,9 → on arrondit vers le HAUT."] },
      { h: 'Exemples', p: ["47 → 50 (le 7 arrondit vers le haut).", "42 → 40 (le 2 arrondit vers le bas).", "45 → 50 (à partir de 5, on monte)."] },
    ],
    tips: ["Regarde uniquement le chiffre des unités pour décider."],
    remember: "Unités de 0 à 4 : on descend. De 5 à 9 : on monte." },

  proportion: { emoji: '⚖️', title: 'La proportionnalité', intro:
    "Deux grandeurs sont proportionnelles quand elles augmentent en même temps, de la même façon.",
    sections: [
      { h: 'Le principe', p: ["Si 1 objet coûte 3 €, alors 2 objets coûtent 6 €, 3 objets coûtent 9 €…", "On multiplie par le même nombre."] },
      { h: 'Méthode', p: ["Prix de n objets = prix d'un objet × n.", "5 objets à 3 € : 3 × 5 = 15 €."] },
    ],
    tips: ["« 2 fois plus d'objets » → « 2 fois plus cher »."],
    remember: "Proportionnel = on multiplie toujours par le même nombre." },

  relatsub: { emoji: '±', title: 'Soustraire les nombres relatifs', intro:
    "Soustraire un nombre relatif suit une règle importante, surtout avec les nombres négatifs.",
    sections: [
      { h: 'La règle clé', p: ["Soustraire un nombre négatif, c'est AJOUTER.", "5 − (−3) = 5 + 3 = 8.", "Moins et moins se transforment en plus."] },
      { h: 'Autres cas', p: ["(−3) − 4 = −7 (on descend encore).", "(−3) − (−5) = −3 + 5 = 2."] },
    ],
    tips: ["Deux « − » qui se suivent deviennent un « + »."],
    remember: "Soustraire un négatif = additionner. − (−) = +." },

  volumecube: { emoji: '🧊', title: 'Le volume du cube', intro:
    "Le volume mesure la place occupée dans l'espace. Pour un cube, c'est facile.",
    sections: [
      { h: 'La formule', p: ["Un cube a toutes ses arêtes égales.", "Volume = arête × arête × arête = arête³."] },
      { h: 'Exemple', p: ["Cube d'arête 3 : 3 × 3 × 3 = 27.", "Cube d'arête 5 : 5³ = 125."] },
    ],
    tips: ["Volume du cube = l'arête au cube (puissance 3)."],
    remember: "Volume d'un cube = arête × arête × arête." },

  powerten: { emoji: '🔟', title: 'Les puissances de 10', intro:
    "Les puissances de 10 sont très utiles : elles donnent 1 suivi de zéros.",
    sections: [
      { h: 'La règle', p: ["10ⁿ = 1 suivi de n zéros.", "10² = 100 (2 zéros).", "10³ = 1000 (3 zéros).", "10⁶ = 1 000 000."] },
    ],
    tips: ["L'exposant = le nombre de zéros."],
    remember: "10 puissance n = 1 suivi de n zéros." },

  factorise: { emoji: 'x', title: 'Factoriser', intro:
    "Factoriser, c'est l'inverse de développer : on met en facteur ce qui est commun.",
    sections: [
      { h: 'Le principe', p: ["Dans 3x + 12, on cherche ce qui est commun : 3.", "3x + 12 = 3 × x + 3 × 4 = 3(x + 4)."] },
      { h: 'Méthode', p: ["Trouve le nombre qui divise les deux termes, mets-le devant la parenthèse."] },
    ],
    tips: ["Vérifie en redéveloppant : 3(x + 4) = 3x + 12. ✓"],
    remember: "Factoriser = mettre le facteur commun devant la parenthèse." },

  equation2: { emoji: '=', title: 'Équations à deux étapes', intro:
    "Ces équations ont une multiplication ET une addition. On les résout en deux temps.",
    sections: [
      { h: 'La méthode', p: ["Exemple : 2x + 3 = 11.", "Étape 1 : on enlève 3 des deux côtés → 2x = 8.", "Étape 2 : on divise par 2 → x = 4."] },
      { h: 'L\'ordre', p: ["On enlève d'abord ce qui est additionné, puis on divise par le nombre devant x."] },
    ],
    tips: ["Fais l'inverse des opérations : d'abord − , ensuite ÷."],
    remember: "ax + b = c : on enlève b, puis on divise par a." },

  function: { emoji: 'ƒ', title: 'Les fonctions', intro:
    "Une fonction transforme un nombre en un autre selon une règle. f(x) est la règle.",
    sections: [
      { h: 'Calculer une image', p: ["Si f(x) = 2x + 1, on remplace x par le nombre demandé.", "f(3) = 2 × 3 + 1 = 7.", "f(5) = 2 × 5 + 1 = 11."] },
    ],
    tips: ["« f(3) » veut dire : remplace x par 3 dans la formule."],
    remember: "Pour f(x) = ax + b, on remplace x par le nombre et on calcule." },

  // ================= LYCÉE Seconde =================
  identremar: { emoji: '(a+b)²', title: 'Les identités remarquables', intro:
    "Une identité remarquable est une formule de développement à connaître par cœur. La plus utilisée est le carré d'une somme.",
    sections: [
      { h: 'La formule', p: ["(a + b)² = a² + 2ab + b².", "Attention : ce n'est PAS a² + b² ! Il y a un terme au milieu : le double produit 2ab."] },
      { h: 'Exemple', p: ["(x + 3)² = x² + 2 × 3 × x + 3² = x² + 6x + 9.", "Le coefficient du milieu est 2 × 3 = 6."] },
      { h: 'Le carré d\'une différence', p: ["(a − b)² = a² − 2ab + b² (le terme du milieu est négatif)."] },
    ],
    tips: ["N'oublie jamais le double produit 2ab au milieu."],
    remember: "(a + b)² = a² + 2ab + b²." },

  milieu: { emoji: '•', title: "Le milieu d'un segment", intro:
    "Le milieu d'un segment est le point situé exactement au centre, à égale distance des deux extrémités.",
    sections: [
      { h: 'Sur une droite', p: ["La position du milieu = la MOYENNE des deux positions.", "Milieu de A(2) et B(8) = (2 + 8) ÷ 2 = 5."] },
      { h: 'Avec des coordonnées', p: ["Milieu de A(xA ; yA) et B(xB ; yB) = ( (xA+xB)/2 ; (yA+yB)/2 )."] },
    ],
    tips: ["Le milieu, c'est la moyenne des coordonnées."],
    remember: "Milieu = (position de A + position de B) ÷ 2." },

  antecedent: { emoji: 'ƒ', title: "L'antécédent d'une fonction", intro:
    "L'image, c'est le résultat f(x). L'antécédent, c'est l'inverse : on connaît le résultat et on cherche le x de départ.",
    sections: [
      { h: 'Image vs antécédent', p: ["Image : on donne x, on cherche f(x). f(3) = ?", "Antécédent : on donne f(x), on cherche x. f(x) = 7, quel x ?"] },
      { h: 'Méthode', p: ["Pour f(x) = 2x + 1 = 7, on résout l'équation :", "2x + 1 = 7 → 2x = 6 → x = 3. L'antécédent de 7 est 3."] },
    ],
    tips: ["Chercher un antécédent = résoudre une équation."],
    remember: "Antécédent : on connaît f(x), on résout pour trouver x." },

  evolution: { emoji: '%', title: "Les pourcentages d'évolution", intro:
    "Quand un prix augmente ou baisse d'un pourcentage, on calcule la variation puis on l'ajoute ou on la retire.",
    sections: [
      { h: 'Une augmentation', p: ["50 € augmentent de 20 % :", "20 % de 50 = 10. Nouveau prix = 50 + 10 = 60 €."] },
      { h: 'Une baisse', p: ["50 € baissent de 20 % :", "20 % de 50 = 10. Nouveau prix = 50 − 10 = 40 €."] },
    ],
    tips: ["Augmenter de 100 %, c'est doubler. Baisser de 50 %, c'est prendre la moitié."],
    remember: "On calcule le pourcentage, puis on l'ajoute (hausse) ou on le retire (baisse)." },

  moyenne: { emoji: '📊', title: 'La moyenne', intro:
    "La moyenne d'une série de nombres donne une valeur « centrale » qui les résume.",
    sections: [
      { h: 'La méthode', p: ["1) On additionne tous les nombres.", "2) On divise par combien il y en a.", "Moyenne de 4, 6, 8 = (4 + 6 + 8) ÷ 3 = 18 ÷ 3 = 6."] },
    ],
    tips: ["La moyenne est toujours comprise entre le plus petit et le plus grand nombre."],
    remember: "Moyenne = (somme des valeurs) ÷ (nombre de valeurs)." },

  vecteur: { emoji: '📏', title: 'La distance entre deux points', intro:
    "Sur une droite graduée, la distance entre deux points est l'écart entre leurs positions.",
    sections: [
      { h: 'Sur une droite', p: ["Distance AB = |position de B − position de A| (toujours positive).", "A(3) et B(8) : distance = 8 − 3 = 5."] },
      { h: 'Remarque', p: ["On prend toujours la valeur positive : une distance ne peut pas être négative."] },
    ],
    tips: ["On soustrait la petite position à la grande."],
    remember: "Distance AB = écart (positif) entre les deux positions." },
};
