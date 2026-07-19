<?php
/**
 * Réinitialisation du mot de passe par e-mail.
 * - Table password_resets créée à la demande (migration douce).
 * - Jeton aléatoire ; seule son empreinte SHA-256 est stockée en base.
 * - Lien valable 1 heure, à usage unique.
 * - Aucune information révélée sur l'existence d'un compte (anti-énumération) :
 *   l'appelant affiche toujours un message générique.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/account.php';

const PASSWORD_RESET_TTL = 3600; // durée de validité du lien, en secondes (1 h)

/** Garantit l'existence de la table des jetons de réinitialisation. */
function ensure_password_reset_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     INT UNSIGNED NOT NULL,
        token_hash  CHAR(64)     NOT NULL,
        expires_at  DATETIME     NOT NULL,
        used_at     DATETIME     NULL,
        created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_token (token_hash),
        KEY idx_user (user_id),
        CONSTRAINT fk_pwreset_user FOREIGN KEY (user_id)
            REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

/**
 * Crée un jeton de réinitialisation pour le compte et envoie l'e-mail.
 * Renvoie true si un e-mail a (tenté d')être envoyé, false si le compte n'a
 * pas d'adresse e-mail enregistrée. (Le résultat ne doit PAS être affiché tel
 * quel à l'utilisateur, afin de ne pas révéler l'existence d'un compte.)
 */
function send_password_reset(array $user): bool
{
    ensure_password_reset_schema();

    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '') {
        return false; // aucun e-mail : impossible d'envoyer un lien
    }

    // Invalide les jetons encore actifs de ce compte (un seul lien valable à la fois).
    $stmt = db()->prepare("UPDATE password_resets SET used_at = NOW()
                           WHERE user_id = ? AND used_at IS NULL");
    $stmt->execute([(int) $user['id']]);

    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $exp   = date('Y-m-d H:i:s', time() + PASSWORD_RESET_TTL);

    $stmt = db()->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at)
                           VALUES (?, ?, ?)");
    $stmt->execute([(int) $user['id'], $hash, $exp]);

    $link = app_base_url() . '/reset_password.php?token=' . $token;
    send_reset_email($email, (string) ($user['username'] ?? ''), $link);
    return true;
}

/** Envoie l'e-mail de réinitialisation (texte simple, encodage UTF-8). */
function send_reset_email(string $to, string $username, string $link): bool
{
    $host   = $_SERVER['HTTP_HOST'] ?? 'luvumbu.com';
    $domain = preg_replace('/:\d+$/', '', $host); // retire un éventuel port
    $from   = 'noreply@' . $domain;

    $subject = "Réinitialisation de votre mot de passe — CV Luvumbu";
    $body =
        "Bonjour" . ($username !== '' ? ' ' . $username : '') . ",\n\n" .
        "Vous avez demandé à réinitialiser le mot de passe de votre compte CV Luvumbu.\n\n" .
        "Cliquez sur le lien ci-dessous (valable 1 heure) :\n" .
        $link . "\n\n" .
        "Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail : " .
        "votre mot de passe reste inchangé.\n\n" .
        "— CV Luvumbu";

    $headers  = "From: CV Luvumbu <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // mail() ne gère pas les accents dans l'objet sans encodage MIME.
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return @mail($to, $encodedSubject, $body, $headers);
}

/**
 * Recherche un jeton valide (non expiré, non utilisé).
 * Renvoie la ligne password_resets (avec user_id) ou null.
 */
function find_valid_reset(string $token): ?array
{
    if ($token === '') {
        return null;
    }
    ensure_password_reset_schema();
    $hash = hash('sha256', $token);
    $stmt = db()->prepare("SELECT * FROM password_resets
                           WHERE token_hash = ? AND used_at IS NULL
                             AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$hash]);
    return $stmt->fetch() ?: null;
}

/** Consomme le jeton (usage unique) et applique le nouveau mot de passe. */
function consume_reset(array $reset, string $newPassword): void
{
    db()->beginTransaction();
    try {
        // Marque le jeton comme utilisé ; rowCount garantit qu'il ne l'était pas déjà.
        $stmt = db()->prepare("UPDATE password_resets SET used_at = NOW()
                               WHERE id = ? AND used_at IS NULL");
        $stmt->execute([(int) $reset['id']]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException("Ce lien a déjà été utilisé.");
        }

        $stmt = db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            (int) $reset['user_id'],
        ]);

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}
