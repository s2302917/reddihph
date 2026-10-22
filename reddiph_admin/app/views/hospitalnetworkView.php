<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Network - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/incomingdispatch.css')); ?>?v=20260916-5">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/hospitalnetworkView.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/hospitalnetworkView.css'); ?>">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/emergency_alert.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/emergency_alert.css'); ?>">
</head>

<body class="emergency_alert_body hospitalnetwork-body">
    <div class="emergency_alert_shell hospitalnetwork-shell" id="emergency_alert_shell">
        <aside class="emergency_alert_sidebar" id="emergency_alert_sidebar">
            <div class="emergency_alert_brand" id="emergency_alert_brand"><span class="emergency_alert_brand_tag">ADMIN</span></div>
            <nav class="emergency_alert_nav" id="emergency_alert_nav" aria-label="Primary">
                <a href="dispatchController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_dispatches"><span>Dashboard</span></a>
                <a href="livemappingController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_mapping"><span>Live Mapping</span></a>
                <a href="emergency_alertController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_alerts"><span>Emergency Cases</span></a>
                <a href="staffschedController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_schedule"><span>Staff Schedule</span></a>
                <a href="resourcesController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_resources"><span>Resources</span></a>
                <a href="hospitalnetworkController.php" class="emergency_alert_nav_item emergency_alert_nav_item_active" id="emergency_alert_nav_network"><span>Referral</span></a>
                <a href="analyticsController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_analytics"><span>Analytics</span></a>
                <a href="demographicsController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_logs"><span>Logs &amp; Demographics</span></a>
                <a href="settingsController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_settings"><span>Settings</span></a>
            </nav>
            <div class="emergency_alert_system_status" id="emergency_alert_system_status">
                <span class="emergency_alert_system_status_icon" aria-hidden="true">!</span>
                <div>
                    <p class="emergency_alert_system_status_title">System Status</p>
                    <p class="emergency_alert_system_status_sub">All systems operational.</p>
                </div>
            </div>
        </aside>
        <main class="emergency_alert_main hospitalnetwork-main" id="emergency_alert_main">
            <header class="emergency_alert_header hospitalnetwork-header" id="emergency_alert_header">
                <div class="emergency_alert_header_copy" id="emergency_alert_header_copy">
                    <h1 class="emergency_alert_hospital_name" id="emergency_alert_hospital_name"><?= htmlspecialchars($hospitalName) ?></h1>
                    <h2 class="emergency_alert_title" id="emergency_alert_title">Hospital Network</h2>
                    <p class="emergency_alert_subtitle">Partner hospitals and referral coordination</p>
                </div>
                <div class="emergency_alert_header_controls" id="emergency_alert_header_controls">
                    <label class="emergency_alert_search" id="emergency_alert_search_wrap">
                        <span class="emergency_alert_search_icon" aria-hidden="true">⌕</span>
                        <input type="search" id="hospitalnetwork-search-input" class="emergency_alert_search_input"
                            placeholder="Search hospitals..." aria-label="Search hospitals" autocomplete="off">
                    </label>
                    <div class="emergency_alert_notification_wrap">
                        <button type="button" class="emergency_alert_bell" id="hospitalnetwork-bell-btn"
                            aria-label="Recently added hospitals" aria-expanded="false" aria-controls="hospitalnetwork-notification-panel">
                            <span aria-hidden="true">🔔</span>
                            <span class="emergency_alert_bell_dot" id="hospitalnetwork-bell-dot" <?= $recentHospitals ? '' : 'hidden' ?>></span>
                        </button>
                        <section class="emergency_alert_notification_panel" id="hospitalnetwork-notification-panel" hidden
                            aria-label="Recently added hospitals">
                            <strong class="emergency_alert_notification_title">New hospitals · last 7 days</strong>
                            <div class="emergency_alert_notification_list" id="hospitalnetwork-notification-list">
                                <?php if ($recentHospitals): ?>
                                    <?php foreach ($recentHospitals as $recentHospital): ?>
                                        <button type="button" class="hospitalnetwork-notification-item"
                                            data-hospital-id="<?= (int) $recentHospital['id'] ?>"
                                            data-hospital-name="<?= htmlspecialchars($recentHospital['name']) ?>">
                                            <strong><?= htmlspecialchars($recentHospital['name']) ?></strong>
                                            <small><?= htmlspecialchars(date('M j, g:i A', strtotime($recentHospital['created_at']))) ?></small>
                                        </button>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="emergency_alert_notification_empty">No new hospitals this week</span>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                    <button type="button" class="emergency_alert_user" aria-label="Signed-in user">
                        <span class="emergency_alert_user_text">
                            <strong class="emergency_alert_user_name"><?= htmlspecialchars($coordinatorName) ?></strong>
                            <span class="emergency_alert_user_role"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $coordinatorRole))) ?></span>
                        </span>
                        <span class="emergency_alert_avatar" aria-hidden="true"><?php if (!empty($coordinatorImage)): ?><img src="<?= htmlspecialchars($coordinatorImage) ?>" alt="">
                        <?php else: ?><?= htmlspecialchars($coordinatorInitials) ?><?php endif; ?></span>
                    </button>
                </div>
            </header>
            <section class="hospitalnetwork-grid" id="hospitalnetwork-grid" aria-label="Partner hospitals">
                <?php foreach ($hospitals as $hospital): ?>
                    <?php $hospitalSpecialties = array_filter(array_map('trim', explode(',', (string) ($hospital['specialties'] ?? '')))); ?>
                    <article class="hospitalnetwork-card"
                        data-hospital-id="<?= (int) $hospital['id'] ?>"
                        data-name="<?php echo htmlspecialchars(strtolower($hospital['name'] . ' ' . implode(' ', $hospitalSpecialties))); ?>">
                        <div class="hospitalnetwork-card-head">
                            <h2>
                                <?php echo htmlspecialchars($hospital['name']); ?>
                            </h2><span>
                                <?php echo htmlspecialchars($hospital['address'] ?: 'Address unavailable'); ?>
                            </span>
                        </div>
                        <div class="hospitalnetwork-specialties">
                            <?php foreach ($hospitalSpecialties as $specialty): ?><b>
                                    <?php echo htmlspecialchars($specialty); ?>
                                </b>
                            <?php endforeach; ?>
                        </div><button type="button" class="hospitalnetwork-refer-btn"
                            data-hospital-id="<?php echo (int) $hospital['id']; ?>"
                            data-hospital-name="<?php echo htmlspecialchars($hospital['name']); ?>"
                            data-hospital-address="<?php echo htmlspecialchars($hospital['address'] ?: 'Address unavailable'); ?>"
                            data-hospital-specialties="<?php echo htmlspecialchars(implode(', ', $hospitalSpecialties)); ?>">
                            Refer patient
                        </button>
                    </article>
                <?php endforeach; ?>
            </section>
        </main>
    </div>
    <div class="hospitalnetwork-modal" id="hospitalnetwork-modal" aria-hidden="true" role="dialog" aria-modal="true">
        <div class="hospitalnetwork-modal-card"><button type="button" class="hospitalnetwork-modal-close"
                aria-label="Close">&#10005;</button>
            <header class="hospitalnetwork-modal-header">
                <p>Refer Patient</p>
                <h2 id="hospitalnetwork-modal-hospital"></h2><span id="hospitalnetwork-modal-details"></span>
            </header>
            <form id="hospitalnetwork-referral-form" method="post" action="hospitalnetworkController.php">
                <input type="hidden" name="action" value="refer">
                <label class="hospitalnetwork-modal-label" for="hospitalnetwork-hospital-id">Receiving hospital</label>
                <select class="hospitalnetwork-incident-select hospitalnetwork-hospital-select" id="hospitalnetwork-hospital-id" name="hospital_id" required>
                    <option value="" selected disabled>Select a hospital</option>
                    <?php foreach ($hospitals as $hospital): ?>
                        <option value="<?= (int) $hospital['id'] ?>"
                            data-hospital-name="<?= htmlspecialchars($hospital['name']) ?>"
                            data-hospital-address="<?= htmlspecialchars($hospital['address'] ?: 'Address unavailable') ?>"
                            data-hospital-specialties="<?= htmlspecialchars($hospital['specialties'] ?? '') ?>">
                            <?= htmlspecialchars($hospital['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label class="hospitalnetwork-modal-label" for="hospitalnetwork-incident-search">Search incidents or resident</label>
                <div class="hospitalnetwork-incident-search">
                    <input type="search" id="hospitalnetwork-incident-search" placeholder="Search incident, patient, ID, or resident">
                    <button type="button" id="hospitalnetwork-incident-search-btn">Search</button>
                </div>
                <label class="hospitalnetwork-modal-label" for="hospitalnetwork-incident-select">Patient case</label>
                <select class="hospitalnetwork-incident-select" id="hospitalnetwork-incident-select" name="incident_id" required>
                    <option value="" disabled <?= !$requestedIncidentId ? 'selected' : '' ?>>Select an incident</option>
                    <?php foreach ($incidents as $incident): ?>
                        <?php
                        $incidentSearchText = '#' . $incident['incident_id'] . ' ' . $incident['incident_type'] . ' ' . ($incident['patient_name'] ?? '') . ' ' . ($incident['location'] ?? '') . ' ' . ($incident['resident_name'] ?? '') . ' ' . ($incident['responder_name'] ?? '');
                        $incidentLabel = '#' . $incident['incident_id'] . ' · ' . $incident['incident_type']
                            . (!empty($incident['patient_name']) ? ' · ' . $incident['patient_name'] : '')
                            . (!empty($incident['resident_name']) ? ' · Resident: ' . $incident['resident_name'] : '');
                        ?>
                        <option value="<?= (int) $incident['incident_id'] ?>"
                            data-search="<?= htmlspecialchars(strtolower($incidentSearchText)) ?>"
                            <?= (int) $requestedIncidentId === (int) $incident['incident_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($incidentLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="hospitalnetwork-incident-search-result" id="hospitalnetwork-incident-search-result" aria-live="polite">
                    <?= $incidents ? count($incidents) . ' active incidents' : 'No active incidents available' ?>
                </p>
                <label class="hospitalnetwork-modal-label" for="hospitalnetwork-referral-reason">Reason for referral</label>
                <textarea id="hospitalnetwork-referral-reason" name="reason" required>Requires specialized care unavailable at current facility.</textarea>
                <button type="submit" class="hospitalnetwork-confirm-btn">Confirm Referral</button>
            </form>
            <section class="hospitalnetwork-referral-success" id="hospitalnetwork-referral-success" hidden aria-live="polite">
                <span class="hospitalnetwork-success-mark" aria-hidden="true">&#10003;</span>
                <h2>Referral sent</h2>
                <p id="hospitalnetwork-referral-success-patient"></p>
                <dl>
                    <div><dt>Receiving hospital</dt><dd id="hospitalnetwork-referral-success-destination"></dd></div>
                    <div><dt>Status</dt><dd id="hospitalnetwork-referral-success-status"></dd></div>
                    <div><dt>Reason</dt><dd id="hospitalnetwork-referral-success-reason"></dd></div>
                </dl>
                <a class="hospitalnetwork-map-link" id="hospitalnetwork-referral-map-link" href="livemappingController.php">View patient on Live Map</a>
                <button type="button" class="hospitalnetwork-success-close">Done</button>
            </section>
        </div>
    </div>
    <div class="hospitalnetwork-toast" id="hospitalnetwork-toast" role="status" aria-live="polite"></div>
    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>