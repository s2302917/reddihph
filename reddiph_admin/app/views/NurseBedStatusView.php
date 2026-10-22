<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/NurseBedStatus.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/NurseBedStatus.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar nurse-sidebar">
        <?php require __DIR__ . '/partials/nurseSidebarView.php'; ?>
    </aside>

    <main class="content bed-page" id="nurse-bed-status-page">
        <header class="bed-heading">
            <div class="bed-heading-copy">
                <p class="bed-hospital-name"><?= htmlspecialchars($hospitalName) ?></p>
                <h1>Bed Status</h1>
            </div>
        </header>

        <section class="bed-stats" role="group" aria-label="Filter beds by status">
            <?php foreach ($summary as $stat): ?>
                <button type="button" class="bed-stat <?= htmlspecialchars($stat['class']) ?>"
                    data-bed-filter="<?= htmlspecialchars($stat['class']) ?>" aria-pressed="false">
                    <span><?= htmlspecialchars($stat['label']) ?></span>
                    <strong><?= (int) $stat['count'] ?></strong>
                </button>
            <?php endforeach; ?>
        </section>
        <p class="bed-status-feedback" id="bedStatusFeedback" role="status" aria-live="polite"></p>

        <section class="bed-section">
            <h2>ER Beds</h2>
            <div class="bed-grid" id="erBedGrid">
                <?php foreach ($erBeds as $bed): ?>
                    <button type="button" class="bed-card <?= htmlspecialchars($bed['status']) ?>"
                        data-bed-id="<?= (int) $bed['id'] ?>" data-bed-type="<?= htmlspecialchars($bed['type']) ?>"
                        data-bed-number="<?= (int) $bed['number'] ?>" data-bed-status="<?= htmlspecialchars($bed['status']) ?>"
                        aria-label="ER Bed <?= (int) $bed['number'] ?>, <?= htmlspecialchars($bed['label']) ?>. Edit status.">
                        <span class="bed-icon" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="M6 24V10M6 20H27V25M9 20V14H27C28.1 14 29 14.9 29 16V20M10 14V10H15C17.2 10 19 11.8 19 14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <strong>Bed <?= (int) $bed['number'] ?></strong>
                        <small><?= htmlspecialchars(strtoupper($bed['label'])) ?></small>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bed-section">
            <h2>Pediatric Bay</h2>
            <div class="bed-grid pediatric" id="pediatricBedGrid">
                <?php foreach ($pediatricBeds as $bed): ?>
                    <button type="button" class="bed-card <?= htmlspecialchars($bed['status']) ?>"
                        data-bed-id="<?= (int) $bed['id'] ?>" data-bed-type="<?= htmlspecialchars($bed['type']) ?>"
                        data-bed-number="<?= (int) $bed['number'] ?>" data-bed-status="<?= htmlspecialchars($bed['status']) ?>"
                        aria-label="Pediatric Bed <?= (int) $bed['number'] ?>, <?= htmlspecialchars($bed['label']) ?>. Edit status.">
                        <span class="bed-icon" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="M6 24V10M6 20H27V25M9 20V14H27C28.1 14 29 14.9 29 16V20M10 14V10H15C17.2 10 19 11.8 19 14" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <strong>Bed <?= (int) $bed['number'] ?></strong>
                        <small><?= htmlspecialchars(strtoupper($bed['label'])) ?></small>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>
<div class="bed-status-modal" id="bedStatusModal" aria-hidden="true" inert>
    <div class="bed-status-backdrop" data-close-bed-modal></div>
    <section class="bed-status-dialog" role="dialog" aria-modal="true" aria-labelledby="bedStatusTitle">
        <button type="button" class="bed-status-close" data-close-bed-modal aria-label="Close">×</button>
        <h2 id="bedStatusTitle">Update bed status</h2>
        <p id="bedStatusBedLabel"></p>
        <form id="bedStatusForm" action="<?= htmlspecialchars(assetUrl('app/controllers/NurseBedStatusController.php?action=update_status')) ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['nurse_bed_status_csrf_token']) ?>">
            <input type="hidden" name="bed_id" id="bedStatusBedId">
            <label for="bedStatusValue">Status</label>
            <select name="status" id="bedStatusValue" required>
                <option value="ready">Ready</option>
                <option value="preparing">Preparing</option>
                <option value="occupied">Occupied</option>
                <option value="cleaning">Cleaning</option>
            </select>
            <div class="bed-incident-assignment" id="bedIncidentAssignment" hidden>
                <label for="bedStatusIncident">Incident from Prep Tasks</label>
                <select name="incident_id" id="bedStatusIncident">
                    <option value="">Choose an incident</option>
                </select>
            </div>
            <p class="bed-status-modal-feedback" id="bedStatusModalFeedback" role="alert" aria-live="polite"></p>
            <div class="bed-status-actions">
                <button type="button" class="bed-status-cancel" data-close-bed-modal>Cancel</button>
                <button type="submit" class="bed-status-save" id="bedStatusSave">Save status</button>
            </div>
        </form>
    </section>
</div>
<?php $staffAlertRole = 'nurse'; require __DIR__ . '/partials/staffIncomingAlertView.php'; ?>
<script>
    window.nurseBedStatus = <?= json_encode($bedStatus, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.nurseBedStatusCsrf = <?= json_encode($_SESSION['nurse_bed_status_csrf_token']) ?>;
</script>
<script src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
</body>
</html>
