<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Reddi PH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/NurseCaseHistory.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/NurseCaseHistory.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar nurse-sidebar">
        <?php require __DIR__ . '/partials/nurseSidebarView.php'; ?>
    </aside>

    <main class="content nurse-history-page">
        <header class="history-top">
            <div class="history-heading">
                <p class="history-hospital-name"><?= htmlspecialchars($sidebarNurse['hospital_name'] ?: 'Hospital') ?></p>
                <h1>Case History</h1>
            </div>
            <form class="search-box" id="nurse-history-search" role="search">
                <label class="visually-hidden" for="nurse-history-query">Search incidents</label>
                <input id="nurse-history-query" type="search" placeholder="Search incidents or patients..." autocomplete="off">
                <button type="submit" aria-label="Search cases">⌕</button>
            </form>
        </header>

        <section class="history-stats nurse-history-stats" aria-label="Case history summary">
            <div>
                <span><?= htmlspecialchars($filter === 'all' ? 'Cases received' : ($filter === 'week' ? 'Cases received this week' : 'Cases received this month')) ?></span>
                <strong><?= (int) $stats['received'] ?></strong>
            </div>
            <div>
                <span>Active Notified Cases</span>
                <strong><?= (int) $stats['totalNotified'] ?></strong>
            </div>
            <div>
                <span>Cases resolved</span>
                <strong class="green-text"><?= (int) $stats['resolved'] ?></strong>
                <small><?= htmlspecialchars($stats['resolvedPercent']) ?> of cases in this date range</small>
            </div>
        </section>

        <nav class="filter-tabs" aria-label="Filter case history">
            <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/NurseCaseHistoryController.php?filter=all')) ?>">All</a>
            <a class="<?= $filter === 'week' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/NurseCaseHistoryController.php?filter=week')) ?>">This week</a>
            <a class="<?= $filter === 'month' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/NurseCaseHistoryController.php?filter=month')) ?>">This month</a>
        </nav>

        <p class="history-feedback" id="nurse-history-feedback" role="status" aria-live="polite" hidden></p>
        <div class="history-table-wrap">
            <table class="history-table">
                <thead>
                <tr>
                    <th scope="col">DATE NOTIFIED</th>
                    <th scope="col">INCIDENT</th>
                    <th scope="col">PATIENT</th>
                    <th scope="col">CATEGORY</th>
                    <th scope="col">INCIDENT LIFECYCLE</th>
                </tr>
                </thead>
                <tbody id="nurse-history-rows">
                <?php foreach ($visibleCases as $index => $case): ?>
                    <tr data-incident-id="<?= (int) $case['id'] ?>" <?= $index >= 6 ? 'hidden' : '' ?>>
                        <td><?= htmlspecialchars($case['date']) ?></td>
                        <td><?= htmlspecialchars($case['case']) ?></td>
                        <td><?= htmlspecialchars($case['patient']) ?></td>
                        <td>
                            <span class="history-category-value category-<?= htmlspecialchars($case['triageCategory'] ?? 'unclassified') ?>">
                                <?= htmlspecialchars($case['triageCategoryLabel']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="history-outcome status-<?= htmlspecialchars($case['status']) ?>"><?= htmlspecialchars(ucfirst($case['status'])) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="history-empty-state" id="nurse-history-empty" <?= $visibleCases ? 'hidden' : '' ?>>
                No cases were notified to your account for this period.
            </p>
        </div>
        <nav class="history-pagination" id="nurse-history-pagination" aria-label="Case history pages" <?= !$visibleCases ? 'hidden' : '' ?>>
            <button type="button" id="nurse-history-previous" disabled>Previous</button>
            <span id="nurse-history-page-status" aria-live="polite">Page 1 of <?= max(1, (int) ceil(count($visibleCases) / 6)) ?></span>
            <button type="button" id="nurse-history-next" <?= count($visibleCases) <= 6 ? 'disabled' : '' ?>>Next</button>
        </nav>
    </main>
</div>
<script>
    window.nurseHistoryRefreshEndpoint = <?= json_encode(assetUrl('app/controllers/NurseCaseHistoryController.php?action=refresh&filter=' . rawurlencode($filter))) ?>;
</script>
<script src="<?= htmlspecialchars(assetUrl('public/js/NurseCaseHistory.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/NurseCaseHistory.js') ?>"></script>
</body>
</html>
