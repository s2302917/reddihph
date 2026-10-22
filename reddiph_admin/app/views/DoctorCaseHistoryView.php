<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/DoctorCaseHistory.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/DoctorCaseHistory.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <span>DOCTOR</span>
        </div>

        <nav class="main-nav" aria-label="Doctor navigation">
            <a class="<?= $currentPage === 'notified-cases' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorNotifiedCasesController.php')) ?>">
                <span class="nav-icon">⌂</span>
                <span>Notified Cases</span>
            </a>
            <a class="<?= $currentPage === 'case-history' ? 'active' : '' ?>"
                href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorCaseHistoryController.php')) ?>">
                <span class="nav-icon">◇</span>
                <span>Case History</span>
            </a>
            <a class="<?= $currentPage === 'schedule' ? 'active' : '' ?>" href="DoctorScheduleController.php">
                <span class="nav-icon">▣</span>
                <span>Schedule</span>
            </a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="DoctorSettingsController.php">
                <span class="nav-icon">⚙</span>
                <span>Settings</span>
            </a>
        </nav>

        <div class="doctor-profile">
            <span class="doctor-profile-avatar" aria-hidden="true">
                <?php if (!empty($sidebarDoctor['profile_image_url'])): ?>
                    <img src="<?= htmlspecialchars($sidebarDoctor['profile_image_url']) ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars($sidebarDoctor['initials']) ?>
                <?php endif; ?>
            </span>
            <span class="doctor-profile-copy">
                <strong><?= htmlspecialchars($sidebarDoctor['full_name']) ?></strong>
                <span><?= htmlspecialchars($sidebarDoctor['coordinator_role']) ?></span>
            </span>
        </div>
    </aside>

    <main class="content history-page">
        <div class="history-top">
            <div class="history-heading">
                <p class="history-hospital-name"><?= htmlspecialchars($sidebarDoctor['hospital_name']) ?></p>
                <h1>Case History</h1>
            </div>
            <form class="search-box" id="doctor-history-search" role="search">
                <label class="visually-hidden" for="doctor-history-query">Search incidents</label>
                <input id="doctor-history-query" type="search" placeholder="Search incidents or patients..." autocomplete="off">
                <button type="submit" aria-label="Search cases">⌕</button>
            </form>
        </div>

        <section class="history-stats">
            <div><span><?= htmlspecialchars($filter === 'all' ? 'Cases received' : ($filter === 'week' ? 'Cases received this week' : 'Cases received this month')) ?></span><strong><?= (int) $stats['received'] ?></strong></div>
            <div><span>Total notified cases</span><strong><?= (int) $stats['totalNotified'] ?></strong></div>
            <div>
                <span>Cases resolved</span>
                <strong class="green-text"><?= (int) $stats['resolved'] ?></strong>
                <small><?= htmlspecialchars($stats['resolvedPercent']) ?> of cases in this date range</small>
            </div>
        </section>

        <div class="filter-tabs">
            <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorCaseHistoryController.php?filter=all')) ?>">All</a>
            <a class="<?= $filter === 'week' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorCaseHistoryController.php?filter=week')) ?>">This week</a>
            <a class="<?= $filter === 'month' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorCaseHistoryController.php?filter=month')) ?>">This month</a>
        </div>

        <p class="history-status-feedback" id="history-status-feedback" role="status" aria-live="polite"></p>
        <div class="history-table-wrap">
            <table class="history-table">
                <thead><tr><th>DATE RECEIVED</th><th>INCIDENT</th><th>PATIENT</th><th>STATUS</th><th>OUTCOME</th></tr></thead>
                <tbody>
                <?php foreach ($visibleCases as $index => $case): ?>
                    <?php $statusLabel = ucfirst($case['status']); ?>
                    <tr data-incident-id="<?= (int) $case['id'] ?>" <?= $index >= 6 ? 'hidden' : '' ?>>
                        <td><?= htmlspecialchars($case['date']) ?></td>
                        <td><?= htmlspecialchars($case['case']) ?></td>
                        <td><?= htmlspecialchars($case['patient']) ?></td>
                        <td>
                            <select class="history-status-select status-<?= htmlspecialchars($case['status']) ?>" data-incident-id="<?= (int) $case['id'] ?>"
                                aria-label="Change status for <?= htmlspecialchars($case['case'], ENT_QUOTES) ?>">
                                <?php if (in_array($case['status'], ['dispatched', 'urgent'], true)): ?>
                                    <option value="<?= htmlspecialchars($case['status']) ?>" selected disabled>
                                        <?= htmlspecialchars(ucfirst($case['status'])) ?> (current)
                                    </option>
                                <?php endif; ?>
                                <?php foreach (['critical' => 'Critical', 'stable' => 'Stable', 'resolved' => 'Resolved'] as $statusValue => $statusText): ?>
                                    <option value="<?= $statusValue ?>" <?= $case['status'] === $statusValue ? 'selected' : '' ?>><?= $statusText ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><span class="history-outcome status-<?= htmlspecialchars($case['status']) ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="history-empty-state" id="doctor-history-empty" <?= $visibleCases ? 'hidden' : '' ?>>
                No cases were sent to your account for this period.
            </p>
        </div>
        <nav class="history-pagination" id="doctor-history-pagination" aria-label="Case history pages" <?= !$visibleCases ? 'hidden' : '' ?>>
            <button type="button" id="doctor-history-previous" disabled>Previous</button>
            <span id="doctor-history-page-status" aria-live="polite">Page 1 of <?= max(1, (int) ceil(count($visibleCases) / 6)) ?></span>
            <button type="button" id="doctor-history-next" <?= count($visibleCases) <= 6 ? 'disabled' : '' ?>>Next</button>
        </nav>
    </main>
</div>
<?php $staffAlertRole = 'doctor'; require __DIR__ . '/partials/staffIncomingAlertView.php'; ?>
<script>
    window.doctorHistoryEndpoint = <?= json_encode(assetUrl('app/controllers/DoctorCaseHistoryController.php?action=update_status')) ?>;
    window.doctorHistoryRefreshEndpoint = <?= json_encode(assetUrl('app/controllers/DoctorCaseHistoryController.php?action=refresh&filter=' . rawurlencode($filter))) ?>;
    window.doctorHistoryCsrfToken = <?= json_encode($_SESSION['doctor_history_csrf_token']) ?>;
</script>
<script src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
</body>
</html>
