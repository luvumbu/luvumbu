<?php
/* ═══════════════════════════════════════════════════════════════
   CONFIGURATION DU PORTFOLIO — 100 % paramétrable.
   Tout le contenu du site est ici. Modifie ce fichier, rien d'autre.
   La présentation (inc/presentation.php) et la carte (inc/carte.php)
   lisent ce tableau.
   ═══════════════════════════════════════════════════════════════ */

return [

  /* ── Identité / SEO ──────────────────────────────────────── */
  'identite' => [
    'nom'      => 'Luvumbu',
    'initiale' => 'L',
    'titre'    => 'Développeur Full-Stack Freelance',
    'seo_desc' => "Développeur full-stack freelance. Je conçois et livre vos applications web sur-mesure : back-end PHP/MySQL, API REST, data-viz, paiements en ligne et app mobile. De l'idée au déploiement.",
    'email'    => 'luvumbu.n@gmail.com',
    'site'     => 'https://bokonzi.com',
    'dispo'    => 'Freelance · disponible pour vos projets',
    // ─── IMAGES (laisse vide pour masquer / repli automatique) ───
    'avatar'   => '',   // ex: 'images/avatar.jpg' — photo ronde dans le hero
    'favicon'  => '',   // ex: 'images/favicon.svg' — icône de l'onglet navigateur
    'og_image' => '',   // ex: 'images/og.png' — image de partage réseaux (1200×630)
  ],

  /* ── HERO ────────────────────────────────────────────────── */
  'hero' => [
    'titre'   => 'Je transforme des idées<br>en <span class="grad">produits web</span> qui tournent.',
    'lead'    => 'Développeur <strong>full-stack freelance</strong>. Je conçois et je livre vos '
               . 'applications web, de A à Z. Ma vitrine : '
               . '<a href="https://bokonzi.com" target="_blank" rel="noopener">BOKONZI</a>, '
               . 'une plateforme data complète — back-end, API, data-viz, paiement en ligne et '
               . 'app mobile. Je maîtrise les fondamentaux et je m\'adapte à votre stack.',
    'cta1'    => ['Voir mes réalisations', '#carte'],
    'cta2'    => ['Démarrer un projet', '#contact'],
    'stats'   => [
      ['n' => 330000, 'suffix' => '+', 'label' => 'enregistrements gérés'],
      ['n' => 3000,   'suffix' => '+', 'label' => 'entités indexées'],
      ['n' => 20,     'suffix' => '+', 'label' => 'endpoints API'],
      ['n' => 100,    'suffix' => '%', 'label' => 'sur-mesure'],
    ],
  ],

  /* ── PROFIL ──────────────────────────────────────────────── */
  'about' => [
    'paragraphes' => [
      'Je suis <strong>Luvumbu</strong>, développeur full-stack freelance. Je ne me contente pas de '
      . '« faire une feature » : je pense produit, données, performance et expérience '
      . 'utilisateur, du back-end jusqu\'au pixel.',
      'Ce que j\'apporte à mes clients ? Des projets réels, en production, avec de <em>vraies</em> '
      . 'contraintes : des centaines de milliers d\'enregistrements, des pipelines de données à '
      . 'grande échelle, du cache, de la sécurité, du SEO, des paiements en ligne. Je livre — '
      . 'pas de dette technique déguisée en « MVP ».',
    ],
    'facts' => [
      ['📍', 'Basé en France · missions en remote'],
      ['⚡', 'PHP · MySQL · JavaScript vanilla'],
      ['🧠', 'Autodidacte, orienté résultat'],
      ['🚀', 'En freelance, de l\'idée au déploiement'],
    ],
    'terminal' => [
      ['~$', 'whoami'],
      ['out', 'luvumbu · full-stack freelance'],
      ['', ''],
      ['~$', 'cat philosophy.txt'],
      ['out', '"Ship real products, not demos."'],
      ['', ''],
      ['~$', 'ls clients/'],
      ['out', 'projets livres/   # prod, pas des maquettes'],
      ['', ''],
      ['~$', 'status'],
      ['ok', '● dispo — prêt pour votre projet'],
    ],
  ],

  /* ── STACK ───────────────────────────────────────────────── */
  'stack' => [
    ['PHP 8',          'Back-end, POO, 53 classes maison'],
    ['MySQL',          'Schéma 23 tables, 30+ FK, requêtes optimisées'],
    ['JavaScript',     'Vanilla, ~100 fonctions, zéro dépendance lourde'],
    ['API REST',       '20+ endpoints JSON, cache fichier, CORS'],
    ['Chart.js',       'Data-viz, courbes, doughnuts, stats live'],
    ['Three.js',       '3D temps réel (podium, scène animée)'],
    ['Pipelines data', 'Traitement parallèle haute perf. (curl_multi)'],
    ['Stripe',         'Paiements en ligne, webhooks, facturation'],
    ['OAuth 2.0',      'Google login, sessions 30j, sécurité CSRF'],
    ['SEO',            'JSON-LD, sitemap dynamique, Open Graph'],
    ['Capacitor',      'App Android à partir de la base web'],
    ['Apache / XAMPP', 'Dév local + prod Hostinger'],
  ],

  /* ── PROJET PHARE (BOKONZI) ──────────────────────────────── */
  'projet' => [
    'nom'   => 'BOKONZI',
    'image' => '',   // ex: 'images/bokonzi.png' — capture d'écran (vide = masquée)
    'sous'  => 'Plateforme data autour de l\'athlétisme. Conçue, développée et déployée de A à Z.',
    'lead' => 'Une plateforme complète : recherche multi-critères, fiches auto-générées, '
            . 'panneaux clubs et épreuves, comparateur partageable, espace pro B2B, et un pipeline '
            . 'de données capable de traiter <strong>des centaines de milliers d\'enregistrements</strong> '
            . 'issus de sources publiques.',
    'metrics' => [
      ['330k+',       'enregistrements · haute volumétrie'],
      ['×5',          'plus rapide grâce au traitement parallèle'],
      ['24 h',        'de cache fichier intelligent'],
      ['Web + Mobile','site + app Android (Capacitor)'],
    ],
    'tags' => ['Recherche avancée','Pipeline de données','API REST','Data-viz','Paiements Stripe',
               'OAuth Google','App mobile Android','Architecture MVC','Admin dashboard','SEO','Cache','Rate limiting'],
    'lien' => ['Visiter bokonzi.com ↗', 'https://bokonzi.com'],
    'features' => [
      ['🔎 Recherche & données', '12 filtres combinables, recherche multi-mots, autocomplétion live, tracking des consultations. Chaque page est cliquable et interconnectée.'],
      ['⚙️ Pipeline haute performance', 'Traitement parallèle (curl_multi) de milliers d\'enregistrements par cycle, parsing, insertion optimisée (cache mémoire → 0 requête répétitive), reprise automatique.'],
      ['📊 Data-viz & stats', 'Panneaux clubs/épreuves à onglets, courbes de niveaux par année, comparateur, cartes de partage générées sur canvas 1200×630.'],
      ['💳 Paiements & comptes', 'Intégration Stripe (paiements en ligne, webhooks fiables, facturation), gestion des comptes et des accès, emails transactionnels brandés.'],
      ['🛡️ Sécurité & fiabilité', 'Rate limiting centralisé, protection anti-abus, confirmation par email, back-office super-admin avec mode « aperçu ».'],
      ['🎯 SEO & performance', 'Sitemap dynamique, JSON-LD Schema.org, Open Graph, cache 24 h par empreinte de paramètres, thème clair/sombre à source de vérité unique.'],
      ['📱 Application mobile', 'Version Android native packagée avec Capacitor, s\'appuyant sur la même API REST que le site — une seule source de données, deux surfaces.'],
      ['🏛️ Architecture MVC', 'Refonte en couches propres (Controllers, Models, Services, Middlewares, Views) pour une base maintenable et prête à grandir.'],
    ],
  ],

  /* ── SAVOIR-FAIRE ────────────────────────────────────────── */
  'skills' => [
    ['🧱', 'Applications web sur-mesure', 'Du back-end à l\'interface, adaptées à votre stack (avec ou sans framework). Des bases de données pensées pour tenir la charge et évoluer.'],
    ['🔌', 'APIs & intégrations', 'Conception d\'API REST propres, intégration Stripe, OAuth, services tiers, webhooks robustes.'],
    ['🤖', 'Traitement de données', 'Pipelines à grande échelle, nettoyage, dédoublonnage, imports fiables et reprenables, haute volumétrie.'],
    ['📈', 'Dashboards & data-viz', 'Tableaux de bord, graphiques interactifs, statistiques en temps réel, exports CSV/PDF.'],
    ['🚀', 'Optimisation & SEO', 'Cache, requêtes optimisées, référencement technique, temps de chargement maîtrisés.'],
    ['🔐', 'Sécurité & authentification', 'Sessions, rôles, rate limiting, protection anti-abus, gestion propre des accès.'],
  ],

  /* ── CONTACT ─────────────────────────────────────────────── */
  'contact' => [
    'titre'   => 'Un projet en tête ? Parlons-en.',
    'texte'   => 'Freelance disponible pour vos applications web, APIs et projets data. '
               . 'Décrivez-moi votre besoin — je vous réponds vite avec une proposition claire.',
    'actions' => [
      ['✉️ Demander un devis', 'mailto:luvumbu.n@gmail.com?subject=Projet%20freelance%20—%20demande%20de%20devis', 'primary'],
      ['🌐 Voir BOKONZI', 'https://bokonzi.com', 'ghost'],
    ],
  ],

  /* ═══════════════════════════════════════════════════════════
     LA CARTE (fin de page) — même logique que LUVUMBU LAND :
     chaque projet = un nœud sur une carte façon Super Mario.
     Ajoute/retire un projet ici, la carte se régénère.
     'etat' : 'ouvert' (jouable) ou 'verrou' (à venir / privé)
     ═══════════════════════════════════════════════════════════ */
  'carte' => [
    'titre' => 'Mes réalisations',
    'sous'  => 'WORLD 1 · Chaque zone est un projet en production. Clique pour explorer.',

    // ─── SOURCE DES ZONES ────────────────────────────────────
    // 'scan'   = détecte automatiquement les vrais sous-projets (dossiers),
    //            MÊME LOGIQUE que LUVUMBU LAND (exclut le moteur + vendor…).
    // 'manuel' = utilise la liste 'projets' ci-dessous, figée.
    'source'   => 'scan',
    'scan_dir' => '.',                                     // racine du portfolio (portefolio/)
    // dossiers ignorés (infra du portfolio + moteur + libs) — le reste = projets
    'exclude'  => ['luvumbu', 'config', 'css', 'js', 'inc', 'images', 'vendor', 'node_modules', '_gestion'],

    // Habillage par sous-projet détecté (icône / nom / image facultatifs).
    // Si un dossier n'est pas listé ici → nom = dossier, description reprise
    // de LUVUMBU LAND (luvumbu/index/config.php → descriptions).
    // Habillage par dossier-projet : icône, nom, image, description, et 'target'
    // (sous-dossier point d'entrée, ex: dropbox -> public_html). Tout est optionnel.
    'meta' => [
      'cv_luvumbu' => [
        'icon' => '📄', 'img' => '', 'nom' => 'CV Luvumbu',
        'desc' => 'Application PHP/MySQL pour créer, mettre en forme, partager et suivre des CV : éditeur visuel (WYSIWYG), plusieurs modèles, rendu imprimable/PDF, liens de partage publics avec QR code, et suivi de candidatures.',
      ],
      'dropbox' => [
        'icon' => '📸', 'img' => '', 'nom' => 'PhotoSync', 'target' => 'public_html',
        'desc' => 'PhotoSync : une app Android qui envoie les médias en arrière-plan et un serveur PHP qui les reçoit, les range et les sert (HTTP / JSON + multipart). Chaque utilisateur a son compte et ne voit que ses propres photos.',
      ],
    ],

    // ─── AUTRES VUES (les mises en scène de LUVUMBU LAND) ───────
    // Bouton dans « Mes réalisations » : mêmes projets, présentés autrement.
    // Chaque vue lance LUVUMBU LAND dans le mode choisi. Ajoute/retire librement.
    'vues' => [
      ['mode' => 'carte',     'icon' => '🏰', 'nom' => 'Carte Mario', 'desc' => 'World map façon Super Mario 3'],
      ['mode' => 'shinobi',   'icon' => '🥷', 'nom' => 'Shinobi',     'desc' => 'Pagode verticale que l\'on grimpe'],
      ['mode' => 'goldenaxe', 'icon' => '🪓', 'nom' => 'Golden Axe',  'desc' => 'Champ de bataille rétro'],
      ['mode' => 'arbre',     'icon' => '🌳', 'nom' => 'Arbre',       'desc' => 'Les projets comme des fruits'],
      ['mode' => 'village',   'icon' => '🍄', 'nom' => 'Schtroumpfs', 'desc' => 'Rue de maisons-champignons'],
      ['mode' => 'descente',  'icon' => '⛰️', 'nom' => 'Descente',    'desc' => 'Zigzag vertical vers le bas'],
      ['mode' => 'mario',     'icon' => '🍄', 'nom' => 'Mario',       'desc' => 'Plateforme classique'],
      ['mode' => 'theatre',   'icon' => '🎭', 'nom' => 'Theater',     'desc' => 'Style BattleBlock'],
      ['mode' => 'kingkong',  'icon' => '🦍', 'nom' => 'King Kong',   'desc' => 'Ascension façon Kong'],
      ['mode' => 'sonic',     'icon' => '🌀', 'nom' => 'Sonic',       'desc' => 'Boucles et vitesse'],
      ['mode' => 'alexkidd',  'icon' => '🧒', 'nom' => 'Alex Kidd',   'desc' => 'Rétro Sega'],
    ],
    // Apparence / univers (s'applique aux modes jeu ci-dessus)
    'apparences' => [
      ['biome' => 'plaine',      'icon' => '🌳', 'nom' => 'Plaine'],
      ['biome' => 'desert',      'icon' => '🏜️', 'nom' => 'Désert'],
      ['biome' => 'neige',       'icon' => '❄️', 'nom' => 'Neige'],
      ['biome' => 'lave',        'icon' => '🌋', 'nom' => 'Lave'],
      ['biome' => 'nuit',        'icon' => '🌙', 'nom' => 'Nuit'],
      ['biome' => 'espace',      'icon' => '🚀', 'nom' => 'Espace'],
      ['biome' => 'ocean',       'icon' => '🌊', 'nom' => 'Océan'],
      ['biome' => 'schtroumpfs', 'icon' => '💙', 'nom' => 'Schtroumpfs'],
    ],
    'luvumbu_url' => 'luvumbu/',            // page LUVUMBU LAND
    'admin_url'   => 'luvumbu/?admin=1',    // espace connexion / paramétrage (ouvre le panneau ⚙)

    // ─── LISTE MANUELLE (repli si 'source' = 'manuel' ou scan vide) ───
    // 'img' = visuel de la zone (vide → l'émoji 'icon' est utilisé)
    'projets' => [
      ['icon' => '🏟️', 'img' => '', 'nom' => 'BOKONZI',        'etat' => 'ouvert', 'url' => 'https://bokonzi.com',               'desc' => 'Plateforme data complète : recherche multi-critères, fiches auto-générées, data-viz et paiement en ligne.'],
      ['icon' => '📱', 'img' => '', 'nom' => 'BOKONZI Mobile', 'etat' => 'ouvert', 'url' => 'https://bokonzi.com',               'desc' => 'App Android (Capacitor) branchée sur la même API REST. Une source de données, deux surfaces.'],
      ['icon' => '🏢', 'img' => '', 'nom' => 'Espace Pro',     'etat' => 'ouvert', 'url' => 'https://bokonzi.com',               'desc' => 'Outil B2B : tableau de bord complet, effectif, indicateurs croisés, export CSV.'],
      ['icon' => '🔌', 'img' => '', 'nom' => 'API REST',       'etat' => 'ouvert', 'url' => 'https://bokonzi.com/api/stats.php', 'desc' => '20+ endpoints JSON, cache fichier 24 h, CORS. Le moteur de données de tout l\'écosystème.'],
      ['icon' => '🎮', 'img' => '', 'nom' => 'LUVUMBU LAND',   'etat' => 'ouvert', 'url' => 'luvumbu/',                           'desc' => 'Le boss de fin : ma page d\'accueil ludique qui transforme mes dossiers-projets en monde de jeu rétro.'],
    ],
  ],

  /* ── Réglages visuels (valeurs par défaut ; l'admin peut les surcharger) ── */
  'theme' => [
    'accent'        => '#8b78ff',   // violet principal
    'accent_dim'    => '#6c5ce7',
    'defaut_sombre' => true,        // thème sombre par défaut
    'particules'    => true,        // fond animé à particules
  ],

  /* ── Espace admin (connexion pour changer l'apparence) ────── */
  'admin' => [
    'password' => 'admin2026',      // ⚠️ CHANGE-MOI : mot de passe de l'espace admin
  ],
];
