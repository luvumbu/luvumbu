<?php

namespace App\Core;

/**
 * Chiffrement symétrique AES-256-GCM des données sensibles (exigence RGPD du CDC).
 * La clé provient de APP_ENCRYPTION_KEY (64 caractères hex = 32 octets).
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    private static function key(): string
    {
        $hex = (string) config('app.enc_key');
        if (strlen($hex) < 64) {
            throw new \RuntimeException('APP_ENCRYPTION_KEY manquante ou trop courte (64 hex requis).');
        }
        return hex2bin(substr($hex, 0, 64));
    }

    /** Chiffre une chaîne, retourne une base64 "iv.tag.cipher". */
    public static function encrypt(string $plain): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Échec du chiffrement.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    /** Déchiffre une chaîne produite par encrypt(). */
    public static function decrypt(string $payload): string
    {
        $data = base64_decode($payload, true);
        if ($data === false || strlen($data) < 28) {
            throw new \RuntimeException('Donnée chiffrée invalide.');
        }
        $iv     = substr($data, 0, 12);
        $tag    = substr($data, 12, 16);
        $cipher = substr($data, 28);
        $plain  = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new \RuntimeException('Échec du déchiffrement.');
        }
        return $plain;
    }

    /** Hash à sens unique pour pseudonymiser (ex: email d'un témoignage anonyme). */
    public static function pseudonymize(string $value): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($value)), self::key());
    }
}
