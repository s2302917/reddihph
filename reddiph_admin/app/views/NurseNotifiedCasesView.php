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

    <main class="content prep-page">
        <header class="prep-header">
            <div class="prep-heading">
                <p class="prep-hospital-name"><?= htmlspecialchars($nurse['hospital_name']) ?></p>
                <h1>Notified Cases</h1>
            </div>
            <div class="prep-header-actions">
                <form class="duty-form" id="nurse-duty-form"
                    action="<?= htmlspecialchars(assetUrl('app/controllers/NurseNotifiedCasesController.php?action=update_availability')) ?>"
                    method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['nurse_cases_csrf_token']) ?>">
                    <input type="hidden" name="availability" value="<?= $nurse['is_available'] ? 'off' : 'on' ?>" id="nurse-duty-value">
                    <span class="duty-feedback" id="nurse-duty-feedback" role="status" aria-live="polite"></span>
                    <button type="submit" class="duty-pill <?= $nurse['is_available'] ? 'is-on-duty' : 'is-off-duty' ?>"
                        id="nurse-duty-toggle" aria-pressed="<?= $nurse['is_available'] ? 'true' : 'false' ?>">
                        <?= $nurse['is_available'] ? 'On Duty' : 'Off Duty' ?>
                    </button>
                </form>
                <div class="case-filters" role="tablist" aria-label="Filter notified cases">
                    <?php
                    $statusCounts = ['all' => count($cases), 'Critical' => 0, 'Urgent' => 0, 'Stable' => 0];
                    foreach ($cases as $case) {
                        $statusCounts[$case['severity']] = ($statusCounts[$case['severity']] ?? 0) + 1;
                    }
                    ?>
                    <button class="active" data-filter="all" type="button" role="tab" aria-selected="true">All <b><?= (int) $statusCounts['all'] ?></b></button>
                    <button data-filter="Critical" type="button" role="tab" aria-selected="false">Critical <b><?= (int) $statusCounts['Critical'] ?></b></button>
                    <button data-filter="Urgent" type="button" role="tab" aria-selected="false">Urgent <b><?= (int) $statusCounts['Urgent'] ?></b></button>
                    <button data-filter="Stable" type="button" role="tab" aria-selected="false">Stable <b><?= (int) $statusCounts['Stable'] ?></b></button>
                </div>
            </div>
        </header>

        <section class="prep-summary" aria-label="Incoming case summary">
            <div><span>Incoming</span><strong id="nurse-summary-incoming"><?= (int) $summary['incoming'] ?><small> cases</small></strong></div>
            <div><span>Prep complete</span><strong id="nurse-summary-prepared"><?= (int) $summary['prepCompleted'] ?><small> of <?= (int) $summary['incoming'] ?></small></strong></div>
            <div class="next-arrival-summary">
                <span>Next arrival</span>
                <strong id="nurse-summary-arrival"><?= $nextArrival ? htmlspecialchars($nextArrival['arrival']) : 'No en-route cases' ?></strong>
                <small id="nurse-summary-arrival-case"><?= $nextArrival ? htmlspecialchars($nextArrival['incident']) : 'Waiting for transport update' ?></small>
            </div>
            <div class="free-beds-summary">
                <span>Free beds</span>
                <div class="bed-counts">
                    <div><strong id="nurse-summary-er"><?= (int) $freeBeds['er'] ?></strong><small>ER</small></div>
                    <div><strong id="nurse-summary-pediatric"><?= (int) $freeBeds['pediatric'] ?></strong><small>Pediatric</small></div>
                </div>
            </div>
        </section>
        <p class="prep-refresh-feedback" id="nurse-refresh-feedback" role="status" aria-live="polite"></p>

        <section class="prep-layout">
            <div class="case-list" id="nurse-case-list" aria-label="Incoming cases">
                <?php foreach (array_slice($cases, 0, 3) as $case): ?>
                    <article class="prep-case" data-severity="<?= htmlspecialchars($case['severity']) ?>"
                        data-case-id="<?= (int) $case['id'] ?>">
                        <button class="case-select" type="button" data-case-id="<?= (int) $case['id'] ?>"
                            aria-pressed="false" aria-label="View <?= htmlspecialchars($case['title'], ENT_QUOTES) ?>">
                            <span class="severity-line <?= strtolower($case['severity']) ?>"></span>
                            <div class="case-main">
                                <div class="case-meta">
                                    <span class="severity-label <?= strtolower($case['severity']) ?>"><i></i><?= htmlspecialchars($case['severity']) ?></span>
                                    <span><?= htmlspecialchars($case['unit']) ?></span>
                                </div>
                                <h2><?= htmlspecialchars($case['title']) ?></h2>
                                <p><?= htmlspecialchars($case['patient']) ?></p>
                                <strong class="arriving-text"><?= htmlspecialchars($case['arrivalStatus']) ?></strong>
                                <small class="prep-state"><?= $case['prepCompleted'] ? 'Prep complete' : 'Preparation pending' ?></small>
                            </div>
                        </button>
                    </article>
                <?php endforeach; ?>
                <p class="nurse-cases-empty" id="nurse-cases-empty" <?= $cases ? 'hidden' : '' ?>>No active cases have been notified to your account.</p>
                <nav class="nurse-case-pagination" id="nurse-case-pagination" aria-label="Notified case pages" <?= count($cases) <= 3 ? 'hidden' : '' ?>>
                    <button type="button" id="nurse-case-previous" disabled>Previous</button>
                    <span id="nurse-case-page-status" aria-live="polite">Page 1 of <?= max(1, (int) ceil(count($cases) / 3)) ?></span>
                    <button type="button" id="nurse-case-next" <?= count($cases) <= 3 ? 'disabled' : '' ?>>Next</button>
                </nav>
            </div>

            <aside class="case-detail empty" id="caseDetail" aria-live="polite">
                <p class="detail-empty-message" id="detailEmptyMessage">Select an incoming case to review its details and prepare the patient.</p>
                <div class="detail-content" id="detailContent" hidden>
                    <div class="detail-top">
                        <span id="detailSeverity" class="severity-label"><i></i></span>
                        <div>
                            <strong id="detailArrivalStatus">Awaiting transport</strong>
                            <span>Expected at <b id="detailArrival">Not available</b></span>
                        </div>
                    </div>
                    <h2 id="detailTitle"></h2>
                    <p id="detailPatient"></p>
                    <p class="detail-location" id="detailLocation"></p>
                    <hr>
                    <div class="handover-head">
                        <strong>Responder handover</strong>
                        <span id="handoverSource"></span>
                    </div>
                    <div class="vitals-row">
                        <div><span>Heart rate</span><strong id="detailHr">—<small>bpm</small></strong></div>
                        <div><span>Blood pressure</span><strong id="detailBp">—<small>mmHg</small></strong></div>
                        <div><span>SpO₂</span><strong id="detailSpo2">—<small>%</small></strong></div>
                        <div><span>Consciousness</span><strong id="detailConscious">—<small> GCS</small></strong></div>
                    </div>
                    <p class="detail-note" id="detailNote"></p>
                    <div class="nurse-note-block">
                        <strong>Nurse notes</strong>
                        <p id="detailNurseNote">No nurse notes recorded.</p>
                    </div>
                    <div class="detail-actions">
                        <button type="button" class="prep-edit-button" id="editPatientPrep">Record vitals &amp; notes</button>
                        <button type="button" class="prep-complete-button" id="togglePrepComplete">Mark prep complete</button>
                    </div>
                    <p class="prep-feedback" id="nurse-prep-feedback" role="status" aria-live="polite"></p>
                </div>
            </aside>
        </section>
    </main>
</div>

<div class="prep-modal" id="patientPrepModal" aria-hidden="true" inert>
    <div class="prep-modal-backdrop" data-close-prep-modal></div>
    <section class="prep-modal-card" role="dialog" aria-modal="true" aria-labelledby="patientPrepTitle">
        <button type="button" class="prep-modal-close" data-close-prep-modal aria-label="Close">×</button>
        <h2 id="patientPrepTitle">Record patient vitals</h2>
        <p id="patientPrepIncident"></p>
        <form id="patientPrepForm" action="<?= htmlspecialchars(assetUrl('app/controllers/NurseNotifiedCasesController.php?action=update_preparation')) ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['nurse_cases_csrf_token']) ?>">
            <input type="hidden" name="incident_id" id="prepIncidentId">
            <div class="prep-vitals-fields">
                <label>Heart rate (bpm)<input type="number" name="heart_rate" id="prepHeartRate" min="1" max="300"></label>
                <label>Blood pressure<input type="text" name="blood_pressure" id="prepBloodPressure" maxlength="7" placeholder="120/80"></label>
                <label>SpO₂ (%)<input type="number" name="spo2" id="prepSpo2" min="0" max="100"></label>
                <label>Consciousness (GCS)<input type="number" name="gcs" id="prepGcs" min="3" max="15"></label>
            </div>
            <label class="prep-bed-label" for="prepBed">Assigned bed
                <select name="bed_id" id="prepBed">
                    <option value="">Choose an available bed</option>
                </select>
            </label>
            <label class="prep-note-label">Nurse notes<textarea name="nurse_note" id="prepNurseNote" maxlength="2000" rows="4" placeholder="Add observations or handoff notes"></textarea></label>
            <label class="prep-complete-check"><input type="checkbox" name="prep_completed" id="prepCompleted" value="1"> Mark patient preparation complete</label>
            <p class="prep-modal-feedback" id="prepModalFeedback" role="alert" aria-live="polite"></p>
            <div class="prep-modal-actions">
                <button type="button" class="prep-cancel-button" data-close-prep-modal>Cancel</button>
                <button type="submit" class="prep-save-button" id="savePatientPrep">Save preparation</button>
            </div>
        </form>
    </section>
</div>

<?php $staffAlertRole = 'nurse'; require __DIR__ . '/partials/staffIncomingAlertView.php'; ?>
<script>
    window.nurseCases = <?= json_encode($cases, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.nurseBeds = <?= json_encode($beds, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.nurseNotifiedCasesEndpoint = <?= json_encode(assetUrl('app/controllers/NurseNotifiedCasesController.php')) ?>;
    window.nurseCasesCsrfToken = <?= json_encode($_SESSION['nurse_cases_csrf_token']) ?>;
</script>
<script src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
</body>
</html>
