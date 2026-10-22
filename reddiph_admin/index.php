<?php

$localConfig = __DIR__ . '/config/config.php';
$nestedConfig = __DIR__ . '/reddiph_admin/config/config.php';

if (is_file($localConfig)) {
  require_once $localConfig;

  $portalUrl = assetUrl('app/controllers/portalController.php');
} elseif (is_file($nestedConfig)) {
  require_once $nestedConfig;

  $portalUrl = '/reddiph_admin/app/controllers/portalController.php';
} else {
  http_response_code(500);
  exit('Application configuration not found.');
}

header('Location: ' . $portalUrl, true, 302);
exit;