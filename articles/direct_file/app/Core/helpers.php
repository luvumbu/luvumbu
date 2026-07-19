<?php
/**
 * Fonctions utilitaires globales (procédurales) chargées par le bootstrap.
 */

/** Raccourci vers la connexion PDO partagée. */
function db(): PDO
{
    return Database::pdo();
}

/**
 * URL absolue depuis la racine de l'application, robuste au sous-dossier
 * d'installation (ex: /direct_file). base_url('chat') => /direct_file/chat
 */
function base_url(string $path = ''): string
{
    $dir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = rtrim($dir, '/');
    return $base . '/' . ltrim($path, '/');
}

/** Récupère l'adresse IP du client (gère le cas reverse-proxy local). */
function client_ip(): string
{
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Normalise un code saisi (majuscules, sans espaces/tirets). */
function normalize_code(string $raw): string
{
    return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $raw));
}

/** Réponse JSON + arrêt. */
function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Échappement HTML court. */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
