<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/incomingdispatch.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/incomingdispatch.css'); ?>">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/analytics.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/analytics.css'); ?>">
</head>

<body class="incomingdispatch-body analytics-body">
    <div class="incomingdispatch-shell analytics-shell">
        <aside class="incomingdispatch-sidebar" id="incomingdispatch-sidebar">
            <div class="incomingdispatch-brand">
                <span class="incomingdispatch-brand-tag">ADMIN</span>
            </div>
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
                <a href="analyticsController.php" class="incomingdispatch-nav-item incomingdispatch-nav-item--active"
                    id="incomingdispatch-nav-analytics">Analytics</a>
                <a href="demographicsController.php" class="incomingdispatch-nav-item"
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
        <main class="incomingdispatch-main analytics-main">
            <header class="incomingdispatch-header">
                <div class="incomingdispatch-header-copy">
                    <h1 class="incomingdispatch-hospital-name"><?= htmlspecialchars($hospitalName) ?></h1>
                    <h2 class="incomingdispatch-page-title">Analytics</h2>
                    <p class="incomingdispatch-page-subtitle"><?= htmlspecialchars($rangeLabel) ?></p>
                </div>
                <div class="incomingdispatch-header-controls">
                    <form class="analytics-range-form" method="get" action="analyticsController.php">
                        <select id="analytics-range" name="range" aria-label="Analytics time range">
                            <option value="today" <?php echo $range === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="7days" <?php echo $range === '7days' ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="15days" <?php echo $range === '15days' ? 'selected' : ''; ?>>Last 15 days
                            </option>
                            <option value="30days" <?php echo $range === '30days' ? 'selected' : ''; ?>>Last 30 days
                            </option>
                            <option value="year" <?php echo $range === 'year' ? 'selected' : ''; ?>>This year</option>
                            <option value="custom" <?php echo $range === 'custom' ? 'selected' : ''; ?>>Custom range
                            </option>
                        </select>
                        <span class="analytics-custom-dates" <?php echo $range === 'custom' ? '' : 'hidden'; ?>><input
                                type="date" id="analytics-from-date" name="from"
                                value="<?php echo htmlspecialchars($_GET['from'] ?? ''); ?>"
                                aria-label="Start date"><input type="date" id="analytics-to-date" name="to"
                                value="<?php echo htmlspecialchars($_GET['to'] ?? ''); ?>" aria-label="End date"><button
                                type="submit">Apply</button></span>
                    </form>
                    <button type="button" class="incomingdispatch-bell" id="incomingdispatch-bell-btn"
                        aria-label="Notifications" aria-expanded="false"
                        aria-controls="incomingdispatch-notification-panel">
                        🔔
                        <span class="incomingdispatch-bell-dot" id="incomingdispatch-bell-dot" hidden></span>
                    </button>
                    <div class="incomingdispatch-notification-panel" id="incomingdispatch-notification-panel" hidden>
                        <strong>Active emergency dispatches</strong>
                        <div id="incomingdispatch-notification-list">
                            <span class="incomingdispatch-notification-empty">Loading active emergencies</span>
                        </div>
                    </div>
                    <form class="analytics-download-form" method="get"
                        action="<?= htmlspecialchars(assetUrl('api/analytics-report.php')) ?>">
                        <input type="hidden" name="from" value="<?= htmlspecialchars($fromDate) ?>">
                        <input type="hidden" name="to" value="<?= htmlspecialchars($toDate) ?>">
                        <button type="submit" class="incomingdispatch-download" id="analytics-download-btn"
                            aria-label="Download complete analytics report" title="Download complete analytics report">
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
            <section class="analytics-metrics" aria-label="Analytics summary">
                <article data-search="total incidents"><span>Total Incidents</span><strong>
                        <?php echo (int) ($metrics['totalIncidents'] ?? 0); ?>
                    </strong></article>
                <article data-search="average response time"><span>Avg. Response Time</span><strong>
                        <?php echo number_format((float) ($metrics['responseTime'] ?? 0), 1); ?> min
                    </strong></article>
                <article data-search="referral rate"><span>Referral Rate</span><strong>
                        <?php echo (int) ($metrics['referralRate'] ?? 0); ?>%
                    </strong></article>
                <article data-search="bed occupancy"><span>Avg. Bed Occupancy</span><strong>
                        <?php echo (int) ($metrics['bedOccupancy'] ?? 0); ?>%
                    </strong></article>
            </section>
            <section class="analytics-grid">
                <?php $incidentTotal = array_sum(array_map(static fn($item) => (int) (($item['total'] ?? ($item['percentage'] ?? 0))), $incidentTypes)); ?>
                <article class="analytics-panel analytics-types"
                    data-search="incidents type cardiac trauma accident respiratory falls other">
                    <h2>Incidents by Type</h2>
                    <?php foreach ($incidentTypes as $item): ?>
                        <?php $share = array_key_exists('percentage', $item) ? (int) ($item['percentage'] ?? 0) : ($incidentTotal > 0 ? (int) round(((int) ($item['total'] ?? 0) / $incidentTotal) * 100) : 0); ?>
                        <div class="analytics-list-row"><span>
                                <?php echo htmlspecialchars($item['label']); ?>
                            </span><b>
                                <?php echo $share; ?>%
                            </b></div>
                    <?php endforeach; ?>
                </article>
                <article class="analytics-panel analytics-trend analytics-response" data-search="response performance">
                    <h2>Response Performance — <?php echo htmlspecialchars($rangeLabel); ?></h2><canvas
                        id="analytics-trend-chart"></canvas>
                    <script id="analytics-trend-data"
                        type="application/json"><?php echo htmlspecialchars(json_encode($trend), ENT_NOQUOTES, 'UTF-8'); ?></script>
                </article>
                <article class="analytics-panel analytics-locations" data-search="barangay locations">
                    <h2>Top Barangays by Volume</h2>
                    <?php foreach ($topLocations as $item): ?>
                        <div class="analytics-list-row"><span>
                                <?php echo htmlspecialchars($item['label']); ?>
                            </span><b>
                                <?php echo (int) $item['total']; ?> cases
                            </b></div>
                    <?php endforeach; ?>
                </article>
                <article class="analytics-panel analytics-fleet" data-search="fleet utilization">
                    <h2>Fleet Utilization</h2>
                    <div class="analytics-list-row"><span>Avg. dispatch time</span><b>
                            <?php echo number_format((float) ($fleet['avgDispatchTime'] ?? 0), 1); ?> min
                        </b></div>
                    <div class="analytics-list-row"><span>Avg. transit time</span><b>
                            <?php echo number_format((float) ($fleet['avgTransitTime'] ?? ($fleet['avgDispatchTime'] ?? 0)), 1); ?>
                            min
                        </b></div>
                    <div class="analytics-list-row"><span>Units active today</span><b>
                            <?php echo (int) ($fleet['activeUnits'] ?? 0); ?>/
                            <?php echo (int) ($fleet['totalUnits'] ?? 0); ?>
                        </b></div>
                </article>
            </section>
        </main>
    </div>
    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>