<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($requestPath) &&
    preg_match('#^/assets/(css|js)/([A-Za-z0-9_-]+\.(?:css|js))$#D', $requestPath, $matches) === 1 &&
    pathinfo($matches[2], PATHINFO_EXTENSION) === $matches[1]) {
    $assetPath = dirname(__DIR__) . '/reddiph_superadmin/public/assets/' . $matches[1] . '/' . $matches[2];
    if (!is_file($assetPath)) {
        http_response_code(404);
        exit('Asset not found.');
    }

    header('Content-Type: ' . ($matches[1] === 'css' ? 'text/css' : 'application/javascript') . '; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: public, max-age=3600');
    readfile($assetPath);
    exit;
}

$applicationEntryPoint = dirname(__DIR__) . '/reddiph_superadmin/index.php';

if (!is_file($applicationEntryPoint)) {
    http_response_code(500);
    error_log('Super Admin application entry point is missing: ' . $applicationEntryPoint);
    exit('The Super Admin application is not installed correctly.');
}

require $applicationEntryPoint;
