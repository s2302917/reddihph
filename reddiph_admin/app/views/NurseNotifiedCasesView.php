<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Reddi PH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/NurseNotifiedCases.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/NurseNotifiedCases.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar nurse-sidebar">
        <?php $sidebarNurse = $nurse; require __DIR__ . '/partials/nurseSidebarView.php'; ?>
    </aside>

    <main class="content notified-page nurse-notified-page">
        <header class="module-header nurse-module-header">
            <div class="module-header-copy">
                <p class="hospital-name"><?= htmlspecialchars($nurse['hospital_name']) ?></p>
                <h1>Notified Cases</h1>
            </div>
            <form class="duty-form" id="nurse-duty-form"
                action="<?= htmlspecialchars(assetUrl('app/controllers/NurseNotifiedCasesController.php?action=update_availability')) ?>"
                method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['nurse_cases_csrf_token']) ?>">
                <input type="hidden" name="availability" value="<?= $nurse['is_available'] ? 'off' : 'on' ?>" id="nurse-duty-value">
                <button type="submit" class="duty-pill <?= $nurse['is_available'] ? 'is-on-duty' : 'is-off-duty' ?>"
                    id="nurse-duty-toggle" aria-pressed="<?= $nurse['is_available'] ? 'true' : 'false' ?>">
                    <?= $nurse['is_available'] ? 'On Duty' : 'Off Duty' ?>
                </button>
                <span class="duty-feedback" id="nurse-duty-feedback" role="status" aria-live="polite"></span>
            </form>
        </header>

        <section class="case-summary nurse-case-summary" aria-label="Notified case summary">
            <?php foreach ([
                'all' => 'All active',
                'emergent' => 'Emergent',
                'urgent' => 'Urgent',
                'non_urgent' => 'Non-Urgent',
                'expectant' => 'Expectant',
            ] as $category => $label): ?>
                <button type="button"
                    class="case-summary-card<?= $category === 'all' ? ' is-active' : '' ?>"
                    data-category-filter="<?= htmlspecialchars($category) ?>"
                    aria-pressed="<?= $category === 'all' ? 'true' : 'false' ?>">
                    <span class="case-summary-label"><?= htmlspecialchars($label) ?></span>
                    <strong class="case-summary-value" data-summary-count="<?= htmlspecialchars($category) ?>"><?= (int) ($summary[$category] ?? 0) ?></strong>
                </button>
            <?php endforeach; ?>
        </section>

        <p class="case-refresh-feedback" id="nurse-refresh-feedback" role="status" aria-live="polite"></p>

        <section class="case-grid nurse-case-grid" id="nurse-case-list" aria-label="Cases notified to you">
            <?php foreach ($cases as $caseIndex => $case): ?>
                <?php $category = $case['triageCategory'] ?? 'unclassified'; ?>
                <article class="case-card" data-case-category="<?= htmlspecialchars($category) ?>"
                    data-case-id="<?= (int) $case['id'] ?>" <?= $caseIndex >= 6 ? 'hidden' : '' ?>>
                    <div class="case-card-top">
                        <h2><?= htmlspecialchars($case['title']) ?></h2>
                        <span class="severity <?= htmlspecialchars($category) ?>">
                            <?= htmlspecialchars($case['triageCategoryLabel']) ?>
                        </span>
                    </div>
                    <p class="nurse-case-patient">
                        <?= htmlspecialchars($case['location']) ?>
                        <?php if ($case['patient'] !== 'Patient details unavailable'): ?>
                            · <?= htmlspecialchars($case['patient']) ?>
                        <?php endif; ?>
                    </p>
                    <small class="case-reported">
                        Reported <?= htmlspecialchars((new DateTimeImmutable($case['reportedAt'], new DateTimeZone('Asia/Manila')))->format('M j, Y g:i A')) ?>
                    </small>
                    <small class="case-lifecycle">
                        <?= htmlspecialchars($case['arrivalStatus']) ?>
                        · Lifecycle: <?= htmlspecialchars(ucfirst($case['status'])) ?>
                    </small>
                    <div class="case-card-bottom">
                        <span>
                            <?= $case['etaMinutes'] === null ? 'ETA unavailable' : 'ETA ' . (int) $case['etaMinutes'] . ' min' ?>
                            · <?= htmlspecialchars($case['hospital']) ?>
                            · <?= htmlspecialchars($case['unit']) ?>
                        </span>
                        <button type="button" class="details-btn" data-case-id="<?= (int) $case['id'] ?>">
                            View details
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
            <p class="cases-empty-state" id="nurse-cases-empty" <?= $cases ? 'hidden' : '' ?>>
                No active cases have been notified to your account.
            </p>
        </section>
        <nav class="case-pagination nurse-case-pagination" id="nurse-case-pagination" aria-label="Case pages" <?= !$cases ? 'hidden' : '' ?>>
            <button type="button" id="nurse-case-previous" disabled>Previous</button>
            <span id="nurse-case-page-status" aria-live="polite">Page 1 of <?= max(1, (int) ceil(count($cases) / 6)) ?></span>
            <button type="button" id="nurse-case-next" <?= count($cases) <= 6 ? 'disabled' : '' ?>>Next</button>
        </nav>
    </main>
</div>

<div class="drawer-backdrop nurse-drawer-backdrop" id="nurse-drawer-backdrop" aria-hidden="true"></div>
<aside class="case-drawer nurse-case-drawer" id="nurse-case-drawer" role="dialog" aria-modal="true"
    aria-labelledby="nurse-drawer-title" aria-hidden="true">
    <button type="button" class="drawer-back" id="nurse-drawer-close">← &nbsp; Back to Notified Cases</button>
    <div class="drawer-title">
        <div>
            <h2 id="nurse-drawer-title"></h2>
            <p id="nurse-drawer-location"></p>
        </div>
        <div class="arrival">
            <span>Emergency category</span>
            <strong id="nurse-drawer-category"></strong>
        </div>
    </div>
    <dl class="incident-detail-list nurse-incident-detail-list">
        <div><dt>Patient</dt><dd id="nurse-drawer-patient"></dd></div>
        <div><dt>Incident lifecycle</dt><dd id="nurse-drawer-lifecycle"></dd></div>
        <div><dt>Transport status</dt><dd id="nurse-drawer-transport-status"></dd></div>
        <div><dt>Hospital</dt><dd id="nurse-drawer-hospital"></dd></div>
        <div><dt>Responder / unit</dt><dd id="nurse-drawer-responder"></dd></div>
        <div><dt>Estimated arrival</dt><dd id="nurse-drawer-eta"></dd></div>
        <div><dt>Reported</dt><dd id="nurse-drawer-reported"></dd></div>
    </dl>
</aside>

<div class="nurse-clinical-modal" id="nurse-clinical-record-modal" aria-hidden="true" inert>
    <div class="nurse-clinical-modal-backdrop" data-close-nurse-clinical-modal></div>
    <section class="nurse-clinical-modal-card" role="dialog" aria-modal="true" aria-labelledby="nurse-clinical-record-title">
        <button type="button" class="nurse-clinical-modal-close" data-close-nurse-clinical-modal aria-label="Close">×</button>
        <h2 id="nurse-clinical-record-title">Record patient vitals</h2>
        <p id="nurse-clinical-record-incident"></p>
        <form id="nurse-clinical-record-form" action="<?= htmlspecialchars(assetUrl('app/controllers/NurseNotifiedCasesController.php?action=update_clinical_record')) ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['nurse_cases_csrf_token']) ?>">
            <input type="hidden" name="incident_id" id="nurse-clinical-incident-id">
            <div class="nurse-clinical-vitals-fields">
                <label>Heart rate (bpm)<input type="number" name="heart_rate" id="nurse-clinical-heart-rate" min="1" max="300"></label>
                <label>Blood pressure<input type="text" name="blood_pressure" id="nurse-clinical-blood-pressure" maxlength="7" placeholder="120/80"></label>
                <label>SpO₂ (%)<input type="number" name="spo2" id="nurse-clinical-spo2" min="0" max="100"></label>
                <label>Consciousness (GCS)<input type="number" name="gcs" id="nurse-clinical-gcs" min="3" max="15"></label>
            </div>
            <label class="nurse-clinical-note-label">Nurse notes<textarea name="nurse_note" id="nurse-clinical-note" maxlength="2000" rows="4" placeholder="Add observations or handoff notes"></textarea></label>
            <p class="nurse-clinical-modal-feedback" id="nurse-clinical-modal-feedback" role="alert" aria-live="polite"></p>
            <div class="nurse-clinical-modal-actions">
                <button type="button" class="nurse-clinical-cancel-button" data-close-nurse-clinical-modal>Cancel</button>
                <button type="submit" class="nurse-clinical-save-button" id="nurse-clinical-save-button">Save clinical record</button>
            </div>
        </form>
    </section>
</div>

<?php $staffAlertRole = 'nurse'; require __DIR__ . '/partials/staffIncomingAlertView.php'; ?>
<script>
    window.nurseCases = <?= json_encode($cases, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.nurseNotifiedCasesEndpoint = <?= json_encode(assetUrl('app/controllers/NurseNotifiedCasesController.php')) ?>;
    window.nurseCasesCsrfToken = <?= json_encode($_SESSION['nurse_cases_csrf_token']) ?>;
</script>
<script src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
</body>
</html>
