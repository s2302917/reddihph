<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/NurseHandoffLogModel.php';
require_once __DIR__ . '/../models/NurseNotifiedCasesModel.php';

requireAuthenticated('nurse');

class NurseHandoffLogController
{
    public function index(): void
    {
        $pageTitle = 'Handoff Log';
        $currentPage = 'handoff-log';
        $sidebarNurse = NurseNotifiedCasesModel::profile((int) $_SESSION['user_id']);
        if (!$sidebarNurse) {
            http_response_code(404);
            exit('Nurse account not found.');
        }
        $handoffData = NurseHandoffLogModel::data((int) $_SESSION['user_id']);
        $hospitalName = $handoffData['hospitalName'];
        $summary = $handoffData['summary'];
        $rows = $handoffData['rows'];

        require __DIR__ . '/../views/NurseHandoffLogView.php';
    }
}

// Run this controller when its file is opened directly in the browser.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    (new NurseHandoffLogController())->index();
}
