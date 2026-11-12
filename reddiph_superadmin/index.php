<?php

use App\Controllers\AuditLogsController;
use App\Controllers\DashboardController;
use App\Controllers\FacilitiesController;
use App\Controllers\LoginController;
use App\Controllers\LogoutController;
use App\Controllers\OverviewController;
use App\Controllers\SessionActivityController;
use App\Controllers\UsersController;

require_once __DIR__ . '/app/controller/DashboardController.php';

DashboardController::initialize();

$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($requestMethod, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($requestPath)) {
    http_response_code(400);
    exit('Invalid request path.');
}

$baseUrl = APP_BASE_URL;
if ($baseUrl !== '' && ($requestPath === $baseUrl || str_starts_with($requestPath, $baseUrl . '/'))) {
    $requestPath = substr($requestPath, strlen($baseUrl)) ?: '/';
}
$requestPath = '/' . trim($requestPath, '/');
if ($requestPath === '//') {
    $requestPath = '/';
}

$routes = [
    '/' => [LoginController::class, 'showLogin'],
    '/login' => [LoginController::class, $requestMethod === 'POST' ? 'login' : 'showLogin'],
    '/logout' => [LogoutController::class, 'index'],
    '/overview' => [OverviewController::class, 'index'],
    '/users' => [UsersController::class, 'index'],
    '/facilities' => [FacilitiesController::class, 'index'],
    '/audit-logs' => [AuditLogsController::class, 'index'],
    '/session-activity' => [SessionActivityController::class, 'index'],
];

if (!isset($routes[$requestPath])) {
    http_response_code(404);
    exit('Page not found.');
}

[$controllerClass, $action] = $routes[$requestPath];
(new $controllerClass())->$action();
