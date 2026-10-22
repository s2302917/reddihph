<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Mapping - Reddi PH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/incomingdispatch.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/incomingdispatch.css'); ?>">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/livemapping.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/livemapping.css'); ?>">
</head>

<body class="incomingdispatch-body livemapping-body">
    <div class="incomingdispatch-shell livemapping-shell">
        <aside class="incomingdispatch-sidebar" id="incomingdispatch-sidebar">
            <div class="incomingdispatch-brand">
                <span class="incomingdispatch-brand-tag">ADMIN</span>
            </div>
            <nav class="incomingdispatch-nav" aria-label="Primary">
                <a href="dispatchController.php" class="incomingdispatch-nav-item"
                    id="incomingdispatch-nav-dispatches">Dashboard</a>
                <a href="livemappingController.php" class="incomingdispatch-nav-item incomingdispatch-nav-item--active"
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
                <a href="demographicsController.php" class="incomingdispatch-nav-item"
                    id="incomingdispatch-nav-logs">Logs &amp; Demographics</a>
                <a href="settingsController.php" class="incomingdispatch-nav-item"
                    id="incomingdispatch-nav-settings">Settings</a>
            </nav>
            <div class="incomingdispatch-system-status" id="incomingdispatch-system-status"><span
                    class="incomingdispatch-system-status-icon">!</span>
                <div>
                    <p class="incomingdispatch-system-status-title">System Status</p>
                    <p class="incomingdispatch-system-status-sub">All systems operational.</p>
                </div>
            </div>
        </aside>
        <main class="incomingdispatch-main livemapping-main">
            <header class="incomingdispatch-header">
                <div class="incomingdispatch-header-copy">
                    <h1 class="incomingdispatch-hospital-name"><?= htmlspecialchars($hospitalName ?? '') ?></h1>
                    <h2 class="incomingdispatch-page-title">Live Mapping</h2>
                </div>
                <div class="incomingdispatch-header-controls">
                    <button type="button" class="incomingdispatch-bell" id="incomingdispatch-bell-btn"
                        aria-label="Notifications">🔔</button>
                    <div class="incomingdispatch-notification-panel" id="incomingdispatch-notification-panel" hidden>
                        <strong>Active emergency dispatches</strong>
                        <div id="incomingdispatch-notification-list"><span
                                class="incomingdispatch-notification-empty">Loading active emergencies</span></div>
                        <strong class="incomingdispatch-staff-alert-heading">Staff alert receipts</strong>
                        <div id="incomingdispatch-staff-alert-list" aria-live="polite">
                            <span class="incomingdispatch-notification-empty">Loading staff alert receipts</span>
                        </div>
                    </div>
                    <button type="button" class="incomingdispatch-download" id="incomingdispatch-download-btn"
                        aria-label="Download emergency report" title="Download emergency report">
                        <span class="incomingdispatch-download-icon" aria-hidden="true"></span>
                    </button>
                    <div class="incomingdispatch-user" id="incomingdispatch-user-menu">
                        <div class="incomingdispatch-user-avatar" aria-hidden="true">
                            <?php if (!empty($coordinatorImage)): ?><img
                                    src="<?= htmlspecialchars($coordinatorImage) ?>" alt="">
                            <?php else: ?>    <?php $nameParts = preg_split('/\s+/', trim((string) $coordinatorName)); ?>
                                <?= htmlspecialchars(strtoupper(substr($nameParts[0] ?? 'U', 0, 1) . substr($nameParts[1] ?? '', 0, 1))) ?><?php endif; ?>
                        </div>
                        <div class="incomingdispatch-user-text">
                            <p class="incomingdispatch-user-name"><?= htmlspecialchars($coordinatorName ?? '') ?></p>
                            <p class="incomingdispatch-user-role">
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $coordinatorRole ?? ''))) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </header>
            <section class="livemapping-content" aria-label="Live emergency response map">
                <div class="livemapping-map-panel">
                    <div class="livemapping-legend"><span><i
                                class="livemapping-legend-dot livemapping-legend-dot--en-route"></i>En
                            route</span><span><i
                                class="livemapping-legend-dot livemapping-legend-dot--arrived"></i>Arrived</span><span><i
                                class="livemapping-legend-dot livemapping-legend-dot--available"></i>Available</span><span><i
                                class="livemapping-legend-dot livemapping-legend-dot--offline"></i>Offline</span></div>
                    <div id="livemapping-map" aria-label="Live ambulance and PTV map"></div>
                    <p id="livemapping-route-status" class="livemapping-route-status"
                        role="status" aria-live="polite" hidden></p>
                </div>
                <aside class="livemapping-fleet">
                    <div class="livemapping-fleet-heading">
                        <h2 class="livemapping-fleet-title">Fleet Status</h2>
                        <span id="livemapping-incident-count" class="livemapping-incident-count">0 incidents</span>
                    </div>
                    <div class="livemapping-fleet-filters" role="group" aria-label="Fleet status filter">
                        <button type="button" class="livemapping-fleet-filter livemapping-fleet-filter--active"
                            data-fleet-filter="all">All</button>
                        <button type="button" class="livemapping-fleet-filter" data-fleet-filter="en_route">En
                            route</button>
                        <button type="button" class="livemapping-fleet-filter"
                            data-fleet-filter="arrived">Arrived</button>
                        <button type="button" class="livemapping-fleet-filter"
                            data-fleet-filter="available">Available</button>
                        <button type="button" class="livemapping-fleet-filter"
                            data-fleet-filter="offline">Offline</button>
                    </div>
                    <div id="livemapping-fleet-list"></div>
                </aside>
            </section>
            <section class="livemapping-ambulance-directory" aria-labelledby="livemapping-ambulance-directory-title">
                <div class="livemapping-ambulance-directory-header">
                    <div>
                        <p class="livemapping-section-eyebrow">All hospitals · live database records</p>
                        <h2 id="livemapping-ambulance-directory-title">Fleet Status &amp; Incidents</h2>
                    </div>
                    <span id="livemapping-ambulance-count" class="livemapping-ambulance-count">0 vehicles · 0 incidents</span>
                </div>
                <div id="livemapping-ambulance-directory-list" class="livemapping-ambulance-directory-list"></div>
            </section>
        </main>
    </div>
    <div class="livemapping-alert-overlay" id="livemapping-alert-overlay" hidden>
        <form class="livemapping-alert-drawer" id="livemapping-alert-form">
            <button type="button" class="livemapping-alert-close" id="livemapping-alert-close"
                aria-label="Close alert drawer">×</button>
            <h2>Send alert to staff</h2>
            <p id="livemapping-alert-transport" class="livemapping-alert-transport"></p>

            <div class="livemapping-alert-incident" aria-live="polite">
                <p class="livemapping-alert-section-label">Selected incident</p>
                <strong id="livemapping-alert-incident-name">Loading incident...</strong>
                <dl class="livemapping-alert-incident-grid">
                    <div>
                        <dt>Incident ID</dt>
                        <dd id="livemapping-alert-incident-id">—</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd id="livemapping-alert-incident-status">—</dd>
                    </div>
                    <div>
                        <dt>Location</dt>
                        <dd id="livemapping-alert-incident-location">—</dd>
                    </div>
                    <div>
                        <dt>Patient</dt>
                        <dd id="livemapping-alert-incident-patient">—</dd>
                    </div>
                    <div>
                        <dt>ETA</dt>
                        <dd id="livemapping-alert-incident-eta">—</dd>
                    </div>
                    <div>
                        <dt>Hospital</dt>
                        <dd id="livemapping-alert-incident-hospital">—</dd>
                    </div>
                </dl>
            </div>

            <label for="livemapping-alert-specialization">Specialization</label>
            <select id="livemapping-alert-specialization" name="specialization">
                <option value="">All specializations</option>
            </select>

            <div id="livemapping-alert-recipients" class="livemapping-alert-recipients" aria-live="polite"></div>

            <textarea name="message" placeholder="Optional message for the response team"></textarea>
            <button type="submit" class="livemapping-alert-submit">Send alert</button>
            <output id="livemapping-alert-status" class="livemapping-alert-status" aria-live="polite"></output>
        </form>
    </div>
    <div class="livemapping-notification-toast" id="livemapping-notification-toast" role="status" aria-live="polite">
    </div>
    <script id="livemapping-initial-data"
        type="application/json"><?php echo htmlspecialchars(json_encode($mapData, JSON_UNESCAPED_SLASHES), ENT_NOQUOTES, 'UTF-8'); ?></script>
    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>