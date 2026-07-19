<?php
namespace App\Core;

/**
 * Aide à renvoyer des réponses JSON normalisées.
 * Format constant : { success, data, error }
 */
class Response
{
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $data, 'error' => null]);
        exit;
    }

    public static function error(string $message, int $status = 400): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'data' => null, 'error' => $message]);
        exit;
    }
}
