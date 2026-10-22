<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/DoctorNotifiedCases.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/DoctorNotifiedCases.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <span>DOCTOR</span>
        </div>

        <nav class="main-nav" aria-label="Doctor navigation">
            <a class="<?= $currentPage === 'notified-cases' ? 'active' : '' ?>"
                href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorNotifiedCasesController.php')) ?>">
                <span class="nav-icon">⌂</span>
                <span>Notified Cases</span>
            </a>
            <a class="<?= $currentPage === 'case-history' ? 'active' : '' ?>"
                href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorCaseHistoryController.php')) ?>">
                <span class="nav-icon">◇</span>
                <span>Case History</span>
            </a>
            <a class="<?= $currentPage === 'schedule' ? 'active' : '' ?>"
                href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorScheduleController.php')) ?>">
                <span class="nav-icon">▣</span>
                <span>Schedule</span>
            </a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>"
                href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorSettingsController.php')) ?>">
                <span class="nav-icon">⚙</span>
                <span>Settings</span>
            </a>
        </nav>

        <div class="doctor-profile">
            <span class="doctor-profile-avatar" aria-hidden="true">
                <?php if (!empty($doctor['profile_image_url'])): ?>
                    <img src="<?= htmlspecialchars($doctor['profile_image_url']) ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars($doctor['initials']) ?>
                <?php endif; ?>
            </span>
            <span class="doctor-profile-copy">
                <strong><?= htmlspecialchars($doctor['full_name']) ?></strong>
                <span><?= htmlspecialchars($doctor['coordinator_role']) ?></span>
            </span>
        </div>
    </aside>

    <main class="content notified-page">
        <header class="module-header">
            <div class="module-header-copy">
                <p class="hospital-name"><?= htmlspecialchars($doctor['hospital_name']) ?></p>
                <h1>Notified Cases</h1>
            </div>
            <form class="duty-form" id="doctor-duty-form"
                action="<?= htmlspecialchars(assetUrl('app/controllers/DoctorNotifiedCasesController.php')) ?>"
                method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['doctor_duty_csrf_token']) ?>">
                <input type="hidden" name="availability" value="<?= !empty($doctor['is_available']) ? 'off' : 'on' ?>"
                    id="doctor-duty-value">
                <button type="submit" class="duty-pill <?= !empty($doctor['is_available']) ? 'is-on-duty' : 'is-off-duty' ?>"
                    id="doctor-duty-toggle" aria-pressed="<?= !empty($doctor['is_available']) ? 'true' : 'false' ?>">
                    <?= !empty($doctor['is_available']) ? 'On Duty' : 'Off Duty' ?>
                </button>
                <span class="duty-feedback" id="doctor-duty-feedback" role="status" aria-live="polite"></span>
            </form>
        </header>

        <section class="case-summary" aria-label="Notified case summary">
            <?php foreach ([
                'all' => 'All active',
                'critical' => 'Critical',
                'urgent' => 'Urgent',
                'stable' => 'Stable',
            ] as $status => $label): ?>
                <button type="button"
                    class="case-summary-card<?= $status === 'all' ? ' is-active' : '' ?>"
                    data-case-filter="<?= htmlspecialchars($status) ?>"
                    aria-pressed="<?= $status === 'all' ? 'true' : 'false' ?>">
                    <span class="case-summary-label"><?= htmlspecialchars($label) ?></span>
                    <strong class="case-summary-value"><?= (int) ($summary[$status] ?? 0) ?></strong>
                </button>
            <?php endforeach; ?>
        </section>

        <section class="case-grid" id="doctor-case-list" aria-label="Cases notified to you">
            <?php foreach ($cases as $caseIndex => $case): ?>
                <?php
                $status = $case['status'] === 'dispatched' ? 'urgent' : $case['status'];
                ?>
                <article class="case-card" data-case-status="<?= htmlspecialchars($status) ?>" <?= $caseIndex >= 6 ? 'hidden' : '' ?>>
                    <div class="case-card-top">
                        <h2><?= htmlspecialchars($case['title']) ?></h2>
                        <span class="severity <?= htmlspecialchars($status) ?>">
                            <?= htmlspecialchars($case['statusLabel']) ?>
                        </span>
                    </div>
                    <p>
                        <?= htmlspecialchars($case['location']) ?>
                        <?php if ($case['patient'] !== 'Patient details unavailable'): ?>
                            · <?= htmlspecialchars($case['patient']) ?>
                        <?php endif; ?>
                    </p>
                    <small class="case-reported">
                        Reported <?= htmlspecialchars((new DateTimeImmutable($case['reportedAt'], new DateTimeZone('Asia/Manila')))->format('M j, Y g:i A')) ?>
                    </small>
                    <div class="case-card-bottom">
                        <span>
                            <?= $case['eta'] === null ? 'ETA unavailable' : 'ETA ' . (int) $case['eta'] . ' min' ?>
                            · <?= htmlspecialchars($case['hospital']) ?>
                        </span>
                        <button type="button" class="details-btn" data-case-id="<?= (int) $case['id'] ?>">
                            View details
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
            <p class="cases-empty-state" id="doctor-cases-empty" <?= $cases ? 'hidden' : '' ?>>
                No active cases have been sent to your account.
            </p>
        </section>
        <nav class="case-pagination" id="doctor-case-pagination" aria-label="Case pages" <?= !$cases ? 'hidden' : '' ?>>
            <button type="button" id="doctor-cases-previous" disabled>Previous</button>
            <span id="doctor-cases-page-status" aria-live="polite">Page 1 of <?= max(1, (int) ceil(count($cases) / 6)) ?></span>
            <button type="button" id="doctor-cases-next" <?= count($cases) <= 6 ? 'disabled' : '' ?>>Next</button>
        </nav>
    </main>
</div>

<?php $initialAlert = $pendingAlerts[0] ?? null; ?>
<div class="incoming-alert-backdrop" id="incoming-alert-backdrop" <?= $initialAlert ? '' : 'hidden' ?>>
    <section class="incoming-alert-card" role="alertdialog" aria-modal="true"
        aria-labelledby="incoming-alert-title" aria-describedby="incoming-alert-description">
        <span class="incoming-alert-kicker">New notification</span>
        <h2 id="incoming-alert-title">Incoming incident</h2>
        <p class="incoming-alert-type" id="incoming-alert-type"><?= htmlspecialchars($initialAlert['title'] ?? '') ?></p>
        <dl class="incoming-alert-details">
            <div><dt>Location</dt><dd id="incoming-alert-location"><?= htmlspecialchars($initialAlert['location'] ?? '') ?></dd></div>
            <div><dt>Status</dt><dd id="incoming-alert-status"><?= htmlspecialchars($initialAlert['statusLabel'] ?? '') ?></dd></div>
            <div><dt>Patient</dt><dd id="incoming-alert-patient"><?= htmlspecialchars($initialAlert['patient'] ?? '') ?></dd></div>
            <div><dt>Hospital</dt><dd id="incoming-alert-hospital"><?= htmlspecialchars($initialAlert['hospital'] ?? '') ?></dd></div>
        </dl>
        <p class="incoming-alert-message" id="incoming-alert-description">Review the incident details, then close this alert to continue.</p>
        <p class="incoming-alert-error" id="incoming-alert-error" role="alert" hidden></p>
        <button type="button" id="incoming-alert-close">Close</button>
    </section>
</div>

<div class="drawer-backdrop" id="drawerBackdrop"></div>
<aside class="case-drawer" id="caseDrawer" role="dialog" aria-modal="true"
    aria-labelledby="drawerCaseTitle" aria-hidden="true">
    <button type="button" class="drawer-back" id="closeDrawer">← &nbsp; Back to Notified Cases</button>
    <div class="drawer-title">
        <div>
            <h2 id="drawerCaseTitle"></h2>
            <p id="drawerCaseLocation"></p>
        </div>
        <div class="arrival">
            <span>Incident status</span>
            <strong id="drawerCaseStatus"></strong>
        </div>
    </div>
    <dl class="incident-detail-list">
        <div><dt>Patient</dt><dd id="drawerCasePatient"></dd></div>
        <div><dt>Hospital</dt><dd id="drawerCaseHospital"></dd></div>
        <div><dt>Responder</dt><dd id="drawerCaseResponder"></dd></div>
        <div><dt>Estimated arrival</dt><dd id="drawerCaseEta"></dd></div>
        <div><dt>Reported</dt><dd id="drawerCaseReported"></dd></div>
        <div class="incident-notes"><dt>Incident notes</dt><dd id="drawerCaseNotes"></dd></div>
    </dl>
</aside>

<script>
    window.doctorCases = <?= json_encode($cases, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.doctorInitialAlerts = <?= json_encode($pendingAlerts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.doctorAlertEndpoint = <?= json_encode(assetUrl('app/controllers/DoctorNotifiedCasesController.php')) ?>;
    window.doctorAlertCsrfToken = <?= json_encode($_SESSION['doctor_duty_csrf_token']) ?>;
</script>
<script src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
</body>
</html>
