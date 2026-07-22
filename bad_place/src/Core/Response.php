<?php

namespace App\Core;

/**
 * Réponse HTTP JSON. Immuable côté usage : on construit puis on envoie.
 */
final class Response
{
    private int $status = 200;
    private array $headers = [];
    private mixed $body = null;

    public function __construct(mixed $body = null, int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** Réponse de succès normalisée. */
    public static function success(mixed $data = null, int $status = 200, array $meta = []): self
    {
        $payload = ['success' => true, 'data' => $data];
        if ($meta) {
            $payload['meta'] = $meta;
        }
        return new self($payload, $status);
    }

    /** Réponse d'erreur normalisée. */
    public static function error(string $message, int $status = 400, array $errors = [], ?string $code = null): self
    {
        $payload = ['success' => false, 'message' => $message];
        if ($code) {
            $payload['code'] = $code;
        }
        if ($errors) {
            $payload['errors'] = $errors;
        }
        return new self($payload, $status);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }

        if ($this->body !== null) {
            echo json_encode(
                $this->body,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
            );
        }
    }
}
