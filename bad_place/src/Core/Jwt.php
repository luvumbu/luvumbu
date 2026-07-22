<?php

namespace App\Core;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * Génération et vérification des jetons JWT (access & refresh).
 */
final class Jwt
{
    public static function issueAccessToken(int $userId, string $role, array $extra = []): string
    {
        $cfg = config('jwt');
        $now = time();
        $payload = array_merge([
            'iss'  => $cfg['issuer'],
            'sub'  => $userId,
            'role' => $role,
            'type' => 'access',
            'iat'  => $now,
            'exp'  => $now + $cfg['access_ttl'],
        ], $extra);

        return FirebaseJWT::encode($payload, $cfg['secret'], $cfg['algo']);
    }

    /**
     * Émet un refresh token. On y place un identifiant opaque (jti) pour permettre
     * la révocation côté base (table refresh_tokens).
     */
    public static function issueRefreshToken(int $userId, string $jti): string
    {
        $cfg = config('jwt');
        $now = time();
        $payload = [
            'iss'  => $cfg['issuer'],
            'sub'  => $userId,
            'type' => 'refresh',
            'jti'  => $jti,
            'iat'  => $now,
            'exp'  => $now + $cfg['refresh_ttl'],
        ];
        return FirebaseJWT::encode($payload, $cfg['secret'], $cfg['algo']);
    }

    /**
     * Décode et vérifie un jeton. Retourne le payload en tableau.
     * @throws HttpException 401 si invalide/expiré.
     */
    public static function decode(string $token): array
    {
        $cfg = config('jwt');
        try {
            $decoded = FirebaseJWT::decode($token, new Key($cfg['secret'], $cfg['algo']));
            return (array) $decoded;
        } catch (ExpiredException) {
            throw HttpException::unauthorized('Jeton expiré');
        } catch (SignatureInvalidException) {
            throw HttpException::unauthorized('Signature de jeton invalide');
        } catch (\Throwable) {
            throw HttpException::unauthorized('Jeton invalide');
        }
    }
}
