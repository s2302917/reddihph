<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/controllers/ResponderReportController.php';

if (($_GET['action'] ?? '') === 'preview') {
    ResponderReportController::preview();
} else {
    ResponderReportController::create();
}
