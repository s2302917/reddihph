<?php
namespace App\Controllers;

require_once __DIR__ . '/DashboardController.php';

class SessionActivityController
{
    public function index(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            http_response_code(405);
            exit;
        }

        if (!DashboardController::csrfValid()) {
            http_response_code(403);
            exit;
        }

        if (!DashboardController::refreshAuthenticatedSession()) {
            http_response_code(401);
            exit;
        }

        http_response_code(204);
        exit;
    }
}
