<?php
// Endpoint public : expose les infos d'identite du blog pour la landing page.
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/settings.php';

// Cache 5 min cote client pour eviter d'appeler la BDD a chaque visite.
header('Cache-Control: public, max-age=300');

// Si aucun parametre n'est defini, on renvoie une chaine vide :
// la landing masque alors les blocs concernes.
$hex = function ($v, $fallback) {
    $v = trim((string)$v);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? $v : $fallback;
};

json_response([
    'site_name'  => trim((string)get_setting('site_name', '')),
    'tagline'    => trim((string)get_setting('tagline', '')),
    'about_text' => trim((string)get_setting('about_text', '')),
    'landing' => [
        // Textes
        'eyebrow'      => trim((string)get_setting('landing_eyebrow', '')),
        'title'        => trim((string)get_setting('landing_title', '')),
        'subtitle'     => trim((string)get_setting('landing_subtitle', '')),
        'cta_text'     => trim((string)get_setting('landing_cta_text', '')),
        'cta_url'      => trim((string)get_setting('landing_cta_url', '')) ?: 'blog/',
        'footer_text'  => trim((string)get_setting('landing_footer_text', '')),
        'footer_url'   => trim((string)get_setting('landing_footer_url',  '')) ?: 'blog/',
        // Comportement
        'show_pulse'   => get_setting('landing_show_pulse', '1') === '1',
        // Typographie : clé de police validée contre la liste connue
        'font'         => (function () {
            $allowed = ['default', 'gothic', 'neon', 'serif2', 'modern'];
            $f = trim((string)get_setting('landing_font', 'default'));
            return in_array($f, $allowed, true) ? $f : 'default';
        })(),
        // Couleurs
        'bg_color'     => $hex(get_setting('landing_bg_color',     ''), '#0f172a'),
        'text_color'   => $hex(get_setting('landing_text_color',   ''), '#f1f5f9'),
        'muted_color'  => $hex(get_setting('landing_muted_color',  ''), '#94a3b8'),
        'accent_color' => $hex(get_setting('landing_accent_color', ''), '#16a34a'),
        'accent_dark'  => $hex(get_setting('landing_accent_dark',  ''), '#166534'),
        'blob_1'       => $hex(get_setting('landing_blob_1', ''), '#6366f1'),
        'blob_2'       => $hex(get_setting('landing_blob_2', ''), '#ec4899'),
        'blob_3'       => $hex(get_setting('landing_blob_3', ''), '#22c55e'),
    ],
]);
