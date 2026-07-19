<?php
// Paramètres globaux du site (table settings).

function settings_defaults() {
    return [
        'site_name'       => 'Mon Blog',
        'tagline'         => 'Le blog',
        'header_baseline' => 'Bienvenue sur le blog',
        'about_text'      => 'Un blog ouvert où chaque membre peut publier ses articles et échanger en commentaires.',
        'quiz_effect'     => 'slide',
        'quiz_mode'       => 'one',
        'quiz_reveal'     => 'live',
    ];
}

// Modes d'affichage d'un questionnaire.
function quiz_modes() {
    return [
        'one' => 'Une question à la fois',
        'all' => 'Toutes les questions d\'un coup',
    ];
}

function quiz_mode_default() {
    $val = get_setting('quiz_mode', 'one');
    return isset(quiz_modes()[$val]) ? $val : 'one';
}

// Quand annonce-t-on les bonnes réponses ?
function quiz_reveals() {
    return [
        'live' => 'En direct (correction après chaque question)',
        'end'  => 'À la fin (correction et score une fois le test terminé)',
    ];
}

function quiz_reveal_default() {
    $val = get_setting('quiz_reveal', 'live');
    return isset(quiz_reveals()[$val]) ? $val : 'live';
}

// Effets de transition entre deux questions d'un questionnaire.
// La clé sert de valeur stockée (setting + localStorage) et de nom d'animation CSS.
function quiz_effects() {
    return [
        'none'  => 'Aucun (désactivé)',
        'fade'  => 'Fondu',
        'slide' => 'Glissement latéral',
        'up'    => 'Glissement vers le haut',
        'zoom'  => 'Zoom',
        'flip'  => 'Retournement 3D',
    ];
}

function quiz_effect_default() {
    $val = get_setting('quiz_effect', 'slide');
    return isset(quiz_effects()[$val]) ? $val : 'slide';
}

function get_all_settings() {
    global $pdo;
    static $cache = null;
    if ($cache !== null) return $cache;

    $rows = $pdo->query('SELECT `key`, value FROM settings')->fetchAll();
    $cache = [];
    foreach ($rows as $r) {
        $cache[$r['key']] = $r['value'];
    }
    // Fallback : injecter les valeurs par défaut pour les clés absentes
    foreach (settings_defaults() as $k => $v) {
        if (!isset($cache[$k])) $cache[$k] = $v;
    }
    return $cache;
}

function get_setting($key, $default = '') {
    $all = get_all_settings();
    return $all[$key] ?? $default;
}

function set_setting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE value = VALUES(value)');
    $stmt->execute([$key, $value]);
    // Invalide le cache
    $GLOBALS['__settings_cache_dirty'] = true;
}
