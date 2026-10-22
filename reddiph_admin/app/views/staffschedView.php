<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Schedule - Reddi PH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/incomingdispatch.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/incomingdispatch.css'); ?>">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/hospitalsched.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/hospitalsched.css'); ?>">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/emergency_alert.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/emergency_alert.css'); ?>">
</head>

<body class="emergency_alert_body hospitalsched-body">
    <div class="emergency_alert_shell hospitalsched-shell" id="emergency_alert_shell">
        <aside class="emergency_alert_sidebar" id="emergency_alert_sidebar">
            <div class="emergency_alert_brand" id="emergency_alert_brand">
                <span class="emergency_alert_brand_tag">ADMIN</span>
            </div>
            <nav class="emergency_alert_nav" id="emergency_alert_nav" aria-label="Primary">
                <a href="dispatchController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_dispatches"><span>Dashboard</span></a>
                <a href="livemappingController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_mapping"><span>Live Mapping</span></a>
                <a href="emergency_alertController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_alerts"><span>Emergency Cases</span></a>
                <a href="staffschedController.php" class="emergency_alert_nav_item emergency_alert_nav_item_active"
                    id="emergency_alert_nav_schedule"><span>Staff Schedule</span></a>
                <a href="resourcesController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_resources"><span>Resources</span></a>
                <a href="hospitalnetworkController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_network"><span>Referral</span></a>
                <a href="analyticsController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_analytics"><span>Analytics</span></a>
                <a href="demographicsController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_logs"><span>Logs &amp; Demographics</span></a>
                <a href="settingsController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_settings"><span>Settings</span></a>
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
                        <?= htmlspecialchars($hospitalName) ?>
                    </h1>
                    <h2 class="emergency_alert_title" id="emergency_alert_title">Staff Schedule</h2>
                    <p class="emergency_alert_subtitle">Doctors and nurses on duty</p>
                </div>
                <div class="emergency_alert_header_controls" id="emergency_alert_header_controls">
                    <label class="emergency_alert_search" id="emergency_alert_search_wrap">
                        <span class="emergency_alert_search_icon" aria-hidden="true">⌕</span>
                        <input type="search" id="hospitalsched-search-input" class="emergency_alert_search_input"
                            placeholder="Search doctors or nurses..." autocomplete="off"
                            aria-label="Search doctors or nurses">
                    </label>
                    <div class="emergency_alert_notification_wrap">
                        <button type="button" class="emergency_alert_bell" id="hospitalsched-bell-btn"
                            aria-label="New doctor and nurse accounts" aria-expanded="false"
                            aria-controls="hospitalsched-notification-panel">
                            <span aria-hidden="true">🔔</span>
                            <span class="emergency_alert_bell_dot" id="hospitalsched-bell-dot" <?= $recentStaff ? '' : 'hidden' ?>></span>
                        </button>
                        <section class="emergency_alert_notification_panel" id="hospitalsched-notification-panel" hidden
                            aria-label="Recently created doctor and nurse accounts">
                            <strong class="emergency_alert_notification_title">New staff accounts · last 7 days</strong>
                            <div class="emergency_alert_notification_list" id="hospitalsched-notification-list">
                                <?php if ($recentStaff): ?>
                                    <?php foreach ($recentStaff as $person): ?>
                                        <button type="button" class="hospitalsched-notification-item"
                                            data-staff-name="<?= htmlspecialchars($person['full_name']) ?>">
                                            <span
                                                class="hospitalsched-notification-role"><?= htmlspecialchars(ucfirst($person['role'])) ?></span>
                                            <strong><?= htmlspecialchars($person['full_name']) ?></strong>
                                            <small><?= htmlspecialchars(date('M j, g:i A', strtotime($person['created_at']))) ?></small>
                                        </button>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="emergency_alert_notification_empty">No new doctor or nurse accounts this
                                        week</span>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                    <button type="button" class="emergency_alert_user" aria-label="Signed-in user">
                        <span class="emergency_alert_user_text">
                            <strong class="emergency_alert_user_name"><?= htmlspecialchars($coordinatorName) ?></strong>
                            <span
                                class="emergency_alert_user_role"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $coordinatorRole))) ?></span>
                        </span>
                        <span class="emergency_alert_avatar"
                            aria-hidden="true"><?php if (!empty($coordinatorImage)): ?><img
                                    src="<?= htmlspecialchars($coordinatorImage) ?>" alt="">
                            <?php else: ?>    <?= htmlspecialchars($coordinatorInitials) ?><?php endif; ?></span>
                    </button>
                </div>
            </header>
            <section class="hospitalsched-summary" aria-label="Staff schedule summary">
                <article><span>On duty now</span><strong id="hospitalsched-on-duty-count"><?php echo $onDutyCount; ?></strong></article>
                <article><span>Doctors on duty</span><strong id="hospitalsched-doctor-count"><?php echo $doctorCount; ?></strong></article>
                <article><span>Nurses on duty</span><strong id="hospitalsched-nurse-count"><?php echo $nurseCount; ?></strong></article>
            </section>
            <section class="hospitalsched-panel">
                <div class="hospitalsched-filters" role="group" aria-label="Staff filters"><button
                        class="hospitalsched-filter hospitalsched-filter--active" data-role="all">All
                        staff</button><button class="hospitalsched-filter" data-role="on_duty">On duty
                        now</button><button class="hospitalsched-filter" data-role="doctor">Doctors</button><button
                        class="hospitalsched-filter" data-role="nurse">Nurses</button></div>
                <div class="hospitalsched-table-wrap">
                    <table class="hospitalsched-table">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Role</th>
                                <th>Shift today</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="hospitalsched-table-body"><?php foreach ($schedules as $schedule): ?>
                                <tr data-role="<?php echo strtolower($schedule['role']); ?>"
                                    data-status="<?php echo htmlspecialchars($schedule['status']); ?>">
                                    <td><span
                                            class="hospitalsched-initials"><?php echo htmlspecialchars($schedule['initials']); ?></span><strong><?php echo htmlspecialchars($schedule['name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($schedule['role']); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['shift']); ?></td>
                                    <td><span
                                            class="hospitalsched-status hospitalsched-status--<?php echo $schedule['online'] ? 'online' : 'offline'; ?>"
                                            title="<?php echo $schedule['online'] ? 'Online in the system' : 'Offline in the system'; ?>"
                                            aria-label="<?php echo htmlspecialchars($schedule['statusLabel'] . ', ' . ($schedule['online'] ? 'online' : 'offline')); ?>"><?php echo htmlspecialchars($schedule['statusLabel']); ?></span>
                                    </td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>