<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/controllers/SosController.php';

if (($_GET['action'] ?? '') === 'preview') {
    SosController::preview();
} else {
    SosController::create();
}
