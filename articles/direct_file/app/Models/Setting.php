<?php
/**
 * Modèle Setting : réglages clé/valeur + thème de couleurs.
 */
class Setting
{
    /** Couleurs par défaut (correspondent au design d'origine). */
    public const THEME_DEFAULTS = [
        'bg'      => '#0f172a', // Fond de page
        'surface' => '#1e293b', // Cartes, en-têtes, bulles
        'accent'  => '#6366f1', // Boutons, éléments actifs
        'text'    => '#e2e8f0', // Texte principal
        'mine'    => '#4338ca', // Mes bulles de message
    ];

    /** Libellés lisibles pour le sélecteur de couleurs (page admin). */
    public const THEME_LABELS = [
        'bg'      => 'Fond de page',
        'surface' => 'Cartes & en-têtes',
        'accent'  => 'Couleur principale',
        'text'    => 'Texte',
        'mine'    => 'Mes messages',
    ];

    /** Crée la table des réglages si besoin. */
    public static function ensureTable(): void
    {
        static $done = false;
        if ($done) return;
        db()->exec(
            'CREATE TABLE IF NOT EXISTS df_settings (
                k VARCHAR(64) PRIMARY KEY,
                v VARCHAR(255) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $done = true;
    }

    /** Lit un réglage générique (ou null). */
    public static function get(string $k): ?string
    {
        self::ensureTable();
        $st = db()->prepare('SELECT v FROM df_settings WHERE k = ?');
        $st->execute([$k]);
        $r = $st->fetch();
        return $r ? $r['v'] : null;
    }

    /** Écrit un réglage générique. */
    public static function set(string $k, string $v): void
    {
        self::ensureTable();
        db()->prepare(
            'INSERT INTO df_settings (k, v) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE v = VALUES(v)'
        )->execute([$k, $v]);
    }

    /** Thème courant : valeurs par défaut écrasées par celles en base. */
    public static function theme(): array
    {
        self::ensureTable();
        $theme = self::THEME_DEFAULTS;
        $rows = db()->query("SELECT k, v FROM df_settings WHERE k LIKE 'theme_%'")->fetchAll();
        foreach ($rows as $r) {
            $key = substr($r['k'], 6); // retire le préfixe "theme_"
            if (array_key_exists($key, $theme) && preg_match('/^#[0-9a-fA-F]{6}$/', $r['v'])) {
                $theme[$key] = strtolower($r['v']);
            }
        }
        return $theme;
    }

    /** Enregistre les couleurs valides du thème. */
    public static function saveTheme(array $vals): void
    {
        self::ensureTable();
        $up = db()->prepare(
            'INSERT INTO df_settings (k, v) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE v = VALUES(v)'
        );
        foreach (self::THEME_DEFAULTS as $key => $_) {
            $val = trim($vals[$key] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $val)) {
                $up->execute(['theme_' . $key, strtolower($val)]);
            }
        }
    }

    /** Réinitialise les couleurs (supprime les réglages theme_*). */
    public static function resetTheme(): void
    {
        self::ensureTable();
        db()->exec("DELETE FROM df_settings WHERE k LIKE 'theme_%'");
    }

    /**
     * Bloc <style> à injecter dans le <head> : surcharge les variables CSS.
     * À placer APRÈS le lien vers style.css.
     */
    public static function styleBlock(): string
    {
        $vars = '';
        foreach (self::theme() as $k => $v) {
            $vars .= "--{$k}:{$v};";
        }
        return "<style>:root{{$vars}}</style>";
    }
}
