<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs &amp; Demographics - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/incomingdispatch.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/incomingdispatch.css'); ?>">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/demographics.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/demographics.css'); ?>">
</head>

<body class="incomingdispatch-body demographics-body">
    <div class="incomingdispatch-shell demographics-shell">
        <aside class="incomingdispatch-sidebar" id="incomingdispatch-sidebar">
            <div class="incomingdispatch-brand"><span class="incomingdispatch-brand-tag">ADMIN</span></div>
            <nav class="incomingdispatch-nav" aria-label="Primary">
                <a href="dispatchController.php" class="incomingdispatch-nav-item"
                    id="incomingdispatch-nav-dispatches">Dashboard</a>
                <a href="livemappingController.php" class="incomingdispatch-nav-item"
                    id="incomingdispatch-nav-mapping">Live Mapping</a>
                <a href="emergency_alertController.php" class="incomingdispatch-nav-item"
                    id="incomingdispatch-nav-alerts">Emergency Cases</a>
                <a href="staffschedController.php" class="incomingdispatch-nav-item"
                    id="incomingdispatch-nav-schedule">Staff Schedule</a>
                <a href="resourcesController.php" class="incomingdispatch-nav-item"
                    id="incomingdispatch-nav-resources">Resources</a>
                <a href="hospitalnetworkController.php" class="incomingdispatch-nav-item"
                    id="incomingdispatch-nav-network">Referral</a>
                <a href="analyticsController.php" class="incomingdispatch-nav-item"
                    id="incomingdispatch-nav-analytics">Analytics</a>
                <a href="demographicsController.php" class="incomingdispatch-nav-item incomingdispatch-nav-item--active"
                    id="incomingdispatch-nav-logs">Logs &amp; Demographics</a>
                <a href="settingsController.php" class="incomingdispatch-nav-item"
                    id="incomingdispatch-nav-settings">Settings</a>
            </nav>
            <div class="incomingdispatch-system-status" id="incomingdispatch-system-status">
                <span class="incomingdispatch-system-status-icon">!</span>
                <div>
                    <p class="incomingdispatch-system-status-title">System Status</p>
                    <p class="incomingdispatch-system-status-sub">All systems operational.</p>
                </div>
            </div>
        </aside>

        <main class="incomingdispatch-main demographics-main" id="demographics-page">
            <header class="incomingdispatch-header">
                <div class="incomingdispatch-header-copy">
                    <h1 class="incomingdispatch-hospital-name">Logs &amp; Demographics</h1>
                    <h2 class="incomingdispatch-page-title"><?= htmlspecialchars($hospitalName) ?></h2>
                    <p class="incomingdispatch-page-subtitle"><?= htmlspecialchars($rangeLabel) ?></p>
                </div>
                <div class="incomingdispatch-header-controls">
                    <form class="demographics-range-form" method="get" action="demographicsController.php">
                        <select id="demographics-range" name="range" aria-label="Demographics time range">
                            <option value="today" <?= $range === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="7days" <?= $range === '7days' ? 'selected' : '' ?>>Last 7 days</option>
                            <option value="15days" <?= $range === '15days' ? 'selected' : '' ?>>Last 15 days</option>
                            <option value="30days" <?= $range === '30days' ? 'selected' : '' ?>>Last 30 days</option>
                            <option value="year" <?= $range === 'year' ? 'selected' : '' ?>>This year</option>
                            <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Custom range</option>
                        </select>
                        <span class="demographics-custom-dates" <?= $range === 'custom' ? '' : 'hidden' ?>>
                            <input type="date" id="demographics-from-date" name="from"
                                value="<?= htmlspecialchars($_GET['from'] ?? '') ?>" aria-label="Start date">
                            <input type="date" id="demographics-to-date" name="to"
                                value="<?= htmlspecialchars($_GET['to'] ?? '') ?>" aria-label="End date">
                            <button type="submit">Apply</button>
                        </span>
                    </form>
                    <button type="button" class="incomingdispatch-bell" id="incomingdispatch-bell-btn"
                        aria-label="Notifications" aria-expanded="false"
                        aria-controls="incomingdispatch-notification-panel">
                        🔔<span class="incomingdispatch-bell-dot" id="incomingdispatch-bell-dot" hidden></span>
                    </button>
                    <div class="incomingdispatch-notification-panel" id="incomingdispatch-notification-panel" hidden>
                        <strong>Active emergency dispatches</strong>
                        <div id="incomingdispatch-notification-list">
                            <span class="incomingdispatch-notification-empty">Loading active emergencies</span>
                        </div>
                    </div>
                    <form class="demographics-download-form" method="get"
                        action="<?= htmlspecialchars(assetUrl('api/demographics-report.php')) ?>">
                        <input type="hidden" name="from" value="<?= htmlspecialchars($fromDate) ?>">
                        <input type="hidden" name="to" value="<?= htmlspecialchars($toDate) ?>">
                        <button type="submit" class="incomingdispatch-download" id="demographics-download-btn"
                            aria-label="Download complete hospital report" title="Download complete hospital report">
                            <span class="incomingdispatch-download-icon" aria-hidden="true"></span>
                        </button>
                    </form>
                    <div class="incomingdispatch-user">
                        <div class="incomingdispatch-user-avatar" aria-hidden="true">
                            <?php if (!empty($coordinatorImage)): ?><img
                                    src="<?= htmlspecialchars($coordinatorImage) ?>" alt="">
                            <?php else: ?>    <?= htmlspecialchars($coordinatorInitials) ?><?php endif; ?>
                        </div>
                        <div class="incomingdispatch-user-text">
                            <p class="incomingdispatch-user-name"><?= htmlspecialchars($coordinatorName) ?></p>
                            <p class="incomingdispatch-user-role"><?= htmlspecialchars($coordinatorRole) ?></p>
                        </div>
                    </div>
                </div>
            </header>

            <section class="demographics-stats" aria-label="Demographic summary">
                <article class="demographics-stat-card" data-stat="18 active alerts">
                    <div class="demographics-stat-icon demographics-stat-icon--alert">!</div>
                    <div class="demographics-stat-value"
                        data-target="<?php echo (int) ($summary['activeAlerts'] ?? 0); ?>">0</div>
                    <div class="demographics-stat-label">Active Alerts</div>
                    <small>Currently being coordinated</small>
                </article>
                <article class="demographics-stat-card" data-stat="42 resolved today">
                    <div class="demographics-stat-icon demographics-stat-icon--ok">✓</div>
                    <div class="demographics-stat-value"
                        data-target="<?php echo (int) ($summary['resolvedToday'] ?? 0); ?>">0</div>
                    <div class="demographics-stat-label">Resolved in Range</div>
                    <small>Cases closed in selected range</small>
                </article>
                <article class="demographics-stat-card" data-stat="135 patients logged">
                    <div class="demographics-stat-icon demographics-stat-icon--people">◔</div>
                    <div class="demographics-stat-value"
                        data-target="<?php echo (int) ($summary['patientsLogged'] ?? 0); ?>">0</div>
                    <div class="demographics-stat-label">Patients Logged</div>
                    <small>Recorded in selected range</small>
                </article>
                <article class="demographics-stat-card" data-stat="7 high risk cases">
                    <div class="demographics-stat-icon demographics-stat-icon--warning">!</div>
                    <div class="demographics-stat-value"
                        data-target="<?php echo (int) ($summary['highRiskCases'] ?? 0); ?>">0</div>
                    <div class="demographics-stat-label">High-Risk Cases</div>
                    <small>Need close monitoring</small>
                </article>
            </section>

            <section class="demographics-panel demographics-log-panel" aria-label="Emergency logs">
                <div class="demographics-panel-header">
                    <h2>Emergency Logs</h2>
                    <div class="demographics-filter-group" aria-label="Log filters">
                        <button class="demographics-filter demographics-filter--active" data-filter="all"
                            type="button">All</button>
                        <button class="demographics-filter" data-filter="critical" type="button">Critical</button>
                        <button class="demographics-filter" data-filter="pending" type="button">Pending</button>
                        <button class="demographics-filter" data-filter="assigned" type="button">Assigned</button>
                        <button class="demographics-filter" data-filter="arrived" type="button">Arrived</button>
                        <button class="demographics-filter" data-filter="resolved" type="button">Resolved</button>
                    </div>
                </div>

                <table class="demographics-log-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient / Caller</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr data-status="<?php echo htmlspecialchars(strtolower($log['statusClass'] ?? 'pending')); ?>">
                                <td><?php echo htmlspecialchars($log['time']); ?></td>
                                <td>
                                    <div class="demographics-patient-cell">
                                        <span
                                            class="demographics-patient-name"><?php echo htmlspecialchars($log['patient']); ?></span>
                                        <small><?php echo htmlspecialchars($log['caller']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($log['location']); ?></td>
                                <td><?php echo htmlspecialchars($log['type']); ?></td>
                                <td><span
                                        class="demographics-status demographics-status--<?php echo htmlspecialchars($log['statusClass'] ?? 'pending'); ?>"><?php echo htmlspecialchars($log['status']); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($log['assigned']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <nav class="demographics-log-pagination" aria-label="Emergency log pages">
                    <button type="button" id="demographics-log-previous" aria-label="Previous log page"
                        disabled>Previous</button>
                    <span id="demographics-log-page" aria-live="polite">Page 1 of 1</span>
                    <button type="button" id="demographics-log-next" aria-label="Next log page" disabled>Next</button>
                </nav>
            </section>

            <section class="demographics-profile-grid" aria-label="Patient demographics">
                <article class="demographics-panel demographics-age-panel">
                    <h3>Age Groups</h3>
                    <?php foreach ($ageGroups as $group): ?>
                        <div class="demographics-bar-row">
                            <span><?php echo htmlspecialchars($group['label']); ?></span>
                            <div class="demographics-bar-track">
                                <i style="width: <?php echo min(100, (int) ($group['percentage'] ?? 0)); ?>%"></i>
                            </div>
                            <strong><?php echo (int) $group['value']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                </article>

                <article class="demographics-panel demographics-gender-panel">
                    <h3>Gender</h3>
                    <div class="demographics-donut-wrap">
                        <div class="demographics-donut"
                            style="background: <?= htmlspecialchars($genderGradient, ENT_QUOTES, 'UTF-8') ?>;"></div>
                    </div>
                    <div class="demographics-legend">
                        <?php foreach ($genderBreakdown as $item): ?>
                            <span><i
                                    style="background: <?php echo htmlspecialchars($item['color']); ?>;"></i><?php echo htmlspecialchars($item['label']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="demographics-panel demographics-location-panel">
                    <h3>Top Locations</h3>
                    <?php foreach ($topLocations as $location): ?>
                        <div class="demographics-location-row">
                            <span><?php echo htmlspecialchars($location['label']); ?></span>
                            <div class="demographics-location-track">
                                <i style="width: <?php echo min(100, (int) ($location['percentage'] ?? 0)); ?>%"></i>
                            </div>
                            <strong><?php echo (int) $location['value']; ?></strong>
                        </div>
                    <?php endforeach; ?>
                </article>
            </section>
        </main>
    </div>

    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>