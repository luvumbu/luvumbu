<?php

namespace App\Core;

use RuntimeException;

/**
 * Exception portant un code HTTP et, optionnellement, des erreurs de validation.
 * Interceptée par le gestionnaire global pour produire une réponse JSON propre.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $statusCode = 400,
        private array $errors = [],
        private ?string $errorCode = null
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public static function notFound(string $message = 'Ressource introuvable'): self
    {
        return new self($message, 404, [], 'NOT_FOUND');
    }

    public static function unauthorized(string $message = 'Authentification requise'): self
    {
        return new self($message, 401, [], 'UNAUTHORIZED');
    }

    public static function forbidden(string $message = 'Accès refusé'): self
    {
        return new self($message, 403, [], 'FORBIDDEN');
    }

    public static function validation(array $errors, string $message = 'Données invalides'): self
    {
        return new self($message, 422, $errors, 'VALIDATION_ERROR');
    }

    public static function tooManyRequests(string $message = 'Trop de requêtes'): self
    {
        return new self($message, 429, [], 'RATE_LIMITED');
    }
}
