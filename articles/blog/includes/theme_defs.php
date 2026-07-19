<?php
// Définitions partagées du thème "Personnalisé" du blog.
// Utilisé par includes/header.php (rendu) et pages/blog_theme.php (édition).

/**
 * Variables CSS personnalisables et leurs valeurs par défaut
 * (qui reproduisent le style clair d'origine).
 * Clé = nom de variable CSS sans le "--". Sauvegardé en réglage "blog_custom_<clé avec _>".
 */
function blog_theme_custom_defaults() {
    return [
        'bg'              => '#fafafa',
        'surface'         => '#ffffff',
        'surface-2'       => '#fbfbfa',
        'text'            => '#2a2a2a',
        'text-soft'       => '#555555',
        'muted'           => '#888888',
        'border'          => '#f0f0f0',
        'accent'          => '#963637',
        'accent-dark'     => '#6f2425',
        'accent-contrast' => '#ffffff',
        'bar-bg'          => '#3a3a3a',
        'bar-text'        => '#ffffff',
        'nav-bg'          => '#fcfcfc',
        'nav-text'        => '#3a3a3a',
        'nav-hover'       => '#f4f5f7',
        'title-color'     => '#3a3a3a',
    ];
}

/** Polices proposées pour le titre : clé => [pile CSS, famille Google Fonts ou null]. */
function blog_theme_font_stacks() {
    return [
        'default' => ["'Anton', sans-serif", null],
        'gothic'  => ["'UnifrakturCook', 'Playfair Display', serif", 'UnifrakturCook:wght@700'],
        'serif'   => ["'Playfair Display', Georgia, serif", 'Playfair+Display:wght@600;800'],
        'modern'  => ["'Helvetica Neue', Arial, sans-serif", null],
    ];
}

/** Clé de réglage correspondant à une variable ('surface-2' => 'blog_custom_surface_2'). */
function blog_theme_setting_key($var) {
    return 'blog_custom_' . str_replace('-', '_', $var);
}

/** Valide une couleur hex #rrggbb ; sinon renvoie le défaut. */
function blog_theme_safe_hex($value, $default) {
    $value = trim((string)$value);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $default;
}
