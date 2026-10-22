<?php
/**
 * emergency_alertView.php
 *
 * Presentation view for Emergency Alerts.
 * Data is supplied by EmergencyAlertController.
 */

// If view is opened directly without controller, bootstrap through controller
if (!isset($alerts) || !isset($summary)) {
    require_once __DIR__ . '/../controllers/emergency_alertController.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Cases - Reddiph</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/emergency_alert.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/emergency_alert.css'); ?>">
</head>

<body class="emergency_alert_body">
    <div class="emergency_alert_shell" id="emergency_alert_shell">

        <aside class="emergency_alert_sidebar" id="emergency_alert_sidebar">
            <div class="emergency_alert_brand" id="emergency_alert_brand">
                <span class="emergency_alert_brand_tag">ADMIN</span>
            </div>

            <nav class="emergency_alert_nav" id="emergency_alert_nav" aria-label="Primary">
                <a href="dispatchController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_dispatches">
                    <span>Dashboard</span>
                </a>

                <a href="livemappingController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_mapping">
                    <span>Live Mapping</span>
                </a>

                <a href="emergency_alertController.php" class="emergency_alert_nav_item emergency_alert_nav_item_active"
                    id="emergency_alert_nav_alerts">
                    <span>Emergency Cases</span>
                </a>

                <a href="staffschedController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_schedule">
                    <span>Staff Schedule</span>
                </a>

                <a href="resourcesController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_resources">
                    <span>Resources</span>
                </a>

                <a href="hospitalnetworkController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_network">
                    <span>Referral</span>
                </a>

                <a href="analyticsController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_analytics">
                    <span>Analytics</span>
                </a>

                <a href="demographicsController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_logs">
                    <span>Logs &amp; Demographics</span>
                </a>

                <a href="settingsController.php" class="emergency_alert_nav_item" id="emergency_alert_nav_settings">
                    <span>Settings</span>
                </a>
            </nav>
            <div class="emergency_alert_system_status" id="emergency_alert_system_status">
                <span class="emergency_alert_system_status_icon" aria-hidden="true">!</span>

                <div>
                    <p class="emergency_alert_system_status_title">System Status</p>
                    <p class="emergency_alert_system_status_sub">All systems operational.</p>
                </div>
            </div>
        </aside>

        <main class="emergency_alert_main" id="emergency_alert_main">
            <header class="emergency_alert_header" id="emergency_alert_header">
                <div class="emergency_alert_header_copy" id="emergency_alert_header_copy">
                    <h1 class="emergency_alert_hospital_name" id="emergency_alert_hospital_name">
                        <?= htmlspecialchars($hospitalName ?? '') ?>
                    </h1>
                    <h2 class="emergency_alert_title" id="emergency_alert_title">Emergency Cases</h2>

                </div>

                <div class="emergency_alert_header_controls" id="emergency_alert_header_controls">
                    <label class="emergency_alert_search" id="emergency_alert_search_wrap">
                        <span class="emergency_alert_search_icon" aria-hidden="true">⌕</span>
                        <input type="search" id="emergency_alert_search_input" class="emergency_alert_search_input"
                            placeholder="Search incidents, hospitals, or units..." autocomplete="off">
                    </label>

                    <div class="emergency_alert_notification_wrap">
                        <button type="button" class="emergency_alert_bell" id="emergency_alert_bell_btn"
                            aria-label="Notifications" aria-expanded="false"
                            aria-controls="emergency_alert_notification_panel">
                            <span aria-hidden="true">🔔</span>
                            <span class="emergency_alert_bell_dot" id="emergency_alert_bell_dot" <?= (($alertCount ?? 0) > 0) ? '' : 'hidden' ?>></span>
                        </button>
                        <section class="emergency_alert_notification_panel" id="emergency_alert_notification_panel"
                            hidden aria-label="Active emergency notifications">
                            <strong class="emergency_alert_notification_title">Active emergency cases</strong>
                            <div class="emergency_alert_notification_list" id="emergency_alert_notification_list">
                                <span class="emergency_alert_notification_empty">No active cases</span>
                            </div>
                        </section>
                    </div>

                    <button type="button" class="emergency_alert_user" id="emergency_alert_user_menu"
                        aria-label="Open user menu">
                        <span class="emergency_alert_user_text">
                            <strong class="emergency_alert_user_name"
                                id="emergency_alert_user_name"><?= htmlspecialchars($coordinatorName ?? 'Charlotte M.') ?></strong>
                            <span class="emergency_alert_user_role"
                                id="emergency_alert_user_role"><?= htmlspecialchars($coordinatorRole ?? 'Chief Coordinator') ?></span>
                        </span>
                        <span class="emergency_alert_avatar" id="emergency_alert_avatar"
                            aria-hidden="true"><?php if (!empty($coordinatorImage)): ?><img
                                    src="<?= htmlspecialchars($coordinatorImage) ?>" alt="">
                            <?php else: ?>    <?= htmlspecialchars($coordinatorInitials ?? 'HA') ?><?php endif; ?></span>
                    </button>
                </div>
            </header>

            <section class="emergency_alert_summary" id="emergency_alert_summary" aria-label="Emergency alert summary">
                <button type="button" class="emergency_alert_summary_card" id="emergency_alert_summary_all"
                    data-status="all">
                    <span class="emergency_alert_summary_label">All</span>
                    <strong class="emergency_alert_summary_value"
                        id="emergency_alert_summary_all_value"><?= (int) ($summary['all'] ?? 0) ?></strong>
                </button>

                <button type="button" class="emergency_alert_summary_card" id="emergency_alert_summary_critical"
                    data-status="critical">
                    <span class="emergency_alert_summary_label">Critical</span>
                    <strong class="emergency_alert_summary_value"
                        id="emergency_alert_summary_critical_value"><?= (int) ($summary['critical'] ?? 0) ?></strong>
                </button>

                <button type="button" class="emergency_alert_summary_card" id="emergency_alert_summary_urgent"
                    data-status="urgent">
                    <span class="emergency_alert_summary_label">Urgent</span>
                    <strong class="emergency_alert_summary_value"
                        id="emergency_alert_summary_urgent_value"><?= (int) ($summary['urgent'] ?? 0) ?></strong>
                </button>

                <button type="button" class="emergency_alert_summary_card" id="emergency_alert_summary_stable"
                    data-status="stable">
                    <span class="emergency_alert_summary_label">Stable</span>
                    <strong class="emergency_alert_summary_value"
                        id="emergency_alert_summary_stable_value"><?= (int) ($summary['stable'] ?? 0) ?></strong>
                </button>

            </section>

            <section class="emergency_alert_filter_bar" id="emergency_alert_filter_bar" aria-label="Alert filters">
                <button type="button" class="emergency_alert_filter emergency_alert_filter_active"
                    id="emergency_alert_filter_all" data-status="all">ALL</button>
                <button type="button" class="emergency_alert_filter" id="emergency_alert_filter_critical"
                    data-status="critical">CRITICAL</button>
                <button type="button" class="emergency_alert_filter" id="emergency_alert_filter_urgent"
                    data-status="urgent">URGENT</button>
                <button type="button" class="emergency_alert_filter" id="emergency_alert_filter_stable"
                    data-status="stable">STABLE</button>
            </section>

            <div class="emergency_alert_notice" id="emergency_alert_critical_notice" hidden>
                THESE CASES NEED IMMEDIATE STAFF NOTIFICATION. CONFIRM DOCTOR AND NURSE ASSIGNMENT AS SOON AS POSSIBLE.
            </div>

            <section class="emergency_alert_table_panel" id="emergency_alert_table_panel">
                <div class="emergency_alert_table_wrap" id="emergency_alert_table_wrap">
                    <table class="emergency_alert_table emergency_alert_table_all" id="emergency_alert_table"
                        data-view="all">
                        <thead id="emergency_alert_table_head">
                            <tr id="emergency_alert_table_head_row">
                                <th>STATUS</th>
                                <th>INCIDENT / PATIENT</th>
                                <th>LOCATION</th>
                                <th>RESPONDER / TRANSPORT</th>
                                <th>REPORTED</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="emergency_alert_table_body">
                            <?php if (!empty($alerts)): ?>
                                <?php foreach ($alerts as $index => $alert): ?>
                                    <?php $reportedParts = preg_split('/\s+/', trim((string) ($alert['reportedAt'] ?? '')), 2); ?>
                                    <tr class="emergency_alert_row" id="emergency_alert_row_<?= (int) $alert['id'] ?>"
                                        data-alert-id="<?= (int) $alert['id'] ?>"
                                        data-status="<?= htmlspecialchars(strtolower($alert['status'])) ?>"
                                        style="animation-delay: <?= $index * 55 ?>ms;">
                                        <td>
                                            <select class="emergency_alert_status_select" data-action="status"
                                                data-alert-id="<?= (int) $alert['id'] ?>" aria-label="Change incident status">
                                                <?php foreach (['critical' => 'Critical', 'urgent' => 'Urgent', 'stable' => 'Stable'] as $statusValue => $statusLabel): ?>
                                                    <option value="<?= $statusValue ?>"
                                                        <?= strtolower($alert['status']) === $statusValue ? 'selected' : '' ?>>
                                                        <?= $statusLabel ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td class="emergency_alert_incident_cell">
                                            <strong
                                                class="emergency_alert_incident"><?= htmlspecialchars($alert['incidentType']) ?></strong>
                                            <?php if (!empty($alert['patientName'])): ?>
                                                <div class="emergency_alert_patient_info">
                                                    <strong class="emergency_alert_patient_name"><?= htmlspecialchars($alert['patientName']) ?></strong>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="emergency_alert_location">
                                            <span><?= htmlspecialchars($alert['location']) ?></span>
                                            <?php if (!empty($alert['barangay'])): ?>
                                                <small><?= htmlspecialchars($alert['barangay']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="emergency_alert_response_info">
                                                <strong><?= htmlspecialchars($alert['responder'] ?: '—') ?></strong>
                                                <span><?= htmlspecialchars($alert['transport'] ?: 'No transport assigned') ?></span>
                                            </div>
                                        </td>
                                        <td class="emergency_alert_time">
                                            <div class="emergency_alert_reported">
                                                <?php if (!empty($reportedParts[0])): ?>
                                                    <span
                                                        class="emergency_alert_reported_date"><?= htmlspecialchars($reportedParts[0]) ?></span>
                                                    <?php if (!empty($reportedParts[1])): ?><span
                                                            class="emergency_alert_reported_clock"><?= htmlspecialchars($reportedParts[1]) ?></span><?php endif; ?>
                                                <?php else: ?>
                                                    <span
                                                        class="emergency_alert_reported_clock"><?= htmlspecialchars($alert['timeAgo']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="emergency_alert_actions_cell">
                                            <button type="button" class="emergency_alert_action emergency_alert_action_details"
                                                data-action="details" data-alert-id="<?= (int) $alert['id'] ?>">
                                                VIEW DETAILS <span>&rarr;</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="emergency_alert_empty" id="emergency_alert_empty" <?= empty($alerts) ? '' : 'hidden' ?>>
                        No emergency alerts found.</div>
                </div>
                <div class="emergency_alert_pagination" id="emergency_alert_pagination">
                    <span id="emergency_alert_page_info">Showing 0 of 0 incidents</span>
                    <div class="emergency_alert_page_controls">
                        <button type="button" id="emergency_alert_page_previous" aria-label="Previous page"
                            disabled>Previous</button>
                        <button type="button" id="emergency_alert_page_next" aria-label="Next page"
                            disabled>Next</button>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div class="emergency_alert_modal" id="emergency_alert_details_modal" aria-hidden="true">
        <div class="emergency_alert_modal_backdrop" id="emergency_alert_modal_backdrop"></div>
        <section class="emergency_alert_modal_card" id="emergency_alert_modal_card" role="dialog" aria-modal="true"
            aria-labelledby="emergency_alert_modal_title">
            <button type="button" class="emergency_alert_modal_close" id="emergency_alert_modal_close"
                aria-label="Close">&times;</button>
            <p class="emergency_alert_modal_kicker">Selected incident</p>
            <h2 class="emergency_alert_modal_title" id="emergency_alert_modal_title">Alert Details</h2>
            <div class="emergency_alert_modal_body" id="emergency_alert_modal_body"></div>
            <div class="emergency_alert_modal_actions" id="emergency_alert_modal_actions"></div>
        </section>
    </div>

    <div class="emergency_alert_toast" id="emergency_alert_toast" role="status" aria-live="polite"></div>

    <script type="application/json"
        id="emergency_alert_initial_data"><?= htmlspecialchars(json_encode($initialData ?? ['alerts' => $alerts, 'summary' => $summary], JSON_UNESCAPED_SLASHES), ENT_NOQUOTES) ?></script>
    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>