<?php

declare(strict_types=1);

/**
 * Shared JSON request/response helpers for API controllers.
 */
abstract class ApiController
{
    protected static function requireMethod(string $method): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== $method) {
            header('Allow: ' . $method);
            self::respond(405, ['success' => false, 'message' => 'Method not allowed.']);
        }
    }

    protected static function readJsonBody(): array
    {
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data)) {
            self::respond(400, ['success' => false, 'message' => 'Invalid request.']);
        }

        return $data;
    }

    /** Logs the real error server-side and returns a generic message to the app. */
    protected static function fail(string $action, Throwable $e, string $message): void
    {
        error_log("Mobile API {$action} error: " . $e->getMessage());
        self::respond(500, ['success' => false, 'message' => $message]);
    }

    protected static function respond(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
