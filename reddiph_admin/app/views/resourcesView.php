<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/emergency_alert.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/emergency_alert.css'); ?>">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/resources.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/resources.css'); ?>">
</head>

<body class="emergency_alert_body resources-body">
    <div class="emergency_alert_shell resources-shell" id="emergency_alert_shell">
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
                <a href="staffschedController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_schedule"><span>Staff Schedule</span></a>
                <a href="resourcesController.php" class="emergency_alert_nav_item emergency_alert_nav_item_active"
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

        <main class="emergency_alert_main resources-main" id="emergency_alert_main">
            <header class="emergency_alert_header resources-header" id="emergency_alert_header">
                <div class="emergency_alert_header_copy" id="emergency_alert_header_copy">
                    <h1 class="emergency_alert_hospital_name" id="emergency_alert_hospital_name">
                        <?= htmlspecialchars($hospitalName ?? '') ?>
                    </h1>
                    <h2 class="emergency_alert_title" id="emergency_alert_title">Resources</h2>
                    <p class="emergency_alert_subtitle">Hospital capacity and operational resources</p>
                </div>
                <div class="emergency_alert_header_controls" id="emergency_alert_header_controls">
                    <label class="emergency_alert_search" id="emergency_alert_search_wrap">
                        <span class="emergency_alert_search_icon" aria-hidden="true">⌕</span>
                        <input type="search" id="resources-search-input" class="emergency_alert_search_input"
                            placeholder="Search resources..." autocomplete="off" aria-label="Search resources">
                    </label>
                    <div class="emergency_alert_notification_wrap">
                        <button type="button" class="emergency_alert_bell" id="resources-bell-btn"
                            aria-label="Resource alerts" aria-expanded="false"
                            aria-controls="resources-notification-panel">
                            <span aria-hidden="true">🔔</span>
                            <span class="emergency_alert_bell_dot" id="resources-bell-dot" <?php echo empty($resourceAlerts) ? 'hidden' : ''; ?>></span>
                        </button>
                        <section class="emergency_alert_notification_panel" id="resources-notification-panel" hidden
                            aria-label="Resource alerts">
                            <strong class="emergency_alert_notification_title">Resource updates</strong>
                            <div class="emergency_alert_notification_list" id="resources-notification-list">
                                <?php if (!empty($resourceAlerts)): ?>
                                    <?php foreach ($resourceAlerts as $alert): ?>
                                        <button type="button" class="resources-notification-item"
                                            data-resource-name="<?= htmlspecialchars($alert['name'] ?? '') ?>">
                                            <span
                                                class="resources-notification-status resources-notification-status--<?= htmlspecialchars((string) ($alert['status'] ?? 'available')) ?>"><?= htmlspecialchars((string) ($alert['statusLabel'] ?? 'Status')) ?></span>
                                            <strong><?= htmlspecialchars((string) ($alert['name'] ?? 'Resource')) ?></strong>
                                            <small><?= htmlspecialchars((string) ($alert['message'] ?? '')) ?></small>
                                            <?php if (!empty($alert['updatedAt'])): ?>
                                                <small class="resources-notification-updated">Updated <?= htmlspecialchars((string) $alert['updatedAt']) ?></small>
                                            <?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="emergency_alert_notification_empty">No resource alerts at the moment</span>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                    <button type="button" class="emergency_alert_user" aria-label="Signed-in user">
                        <span class="emergency_alert_user_text">
                            <strong
                                class="emergency_alert_user_name"><?= htmlspecialchars($coordinatorName ?? '') ?></strong>
                            <span
                                class="emergency_alert_user_role"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $coordinatorRole ?? 'hospital_admin'))) ?></span>
                        </span>
                        <span class="emergency_alert_avatar"
                            aria-hidden="true"><?php if (!empty($coordinatorImage)): ?><img src="<?= htmlspecialchars($coordinatorImage) ?>" alt="">
                            <?php else: ?><?= htmlspecialchars($coordinatorInitials ?? 'HA') ?><?php endif; ?></span>
                    </button>
                </div>
            </header>

            <section class="resources-summary" aria-label="Hospital resource summary">
                <?php foreach ($summaryCards as $card): ?>
                    <article
                        class="resources-summary-card resources-summary-card--<?= htmlspecialchars((string) ($card['status'] ?? 'available')) ?>">
                        <span><?= htmlspecialchars((string) ($card['label'] ?? 'Resource')) ?></span>
                        <strong><?= htmlspecialchars((string) ($card['value'] ?? '0')) ?></strong>
                        <small><?= htmlspecialchars((string) ($card['meta'] ?? '')) ?></small>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="resources-panel" aria-label="Hospital resources">
                <div class="resources-filters" role="group" aria-label="Resource filters">
                    <button class="resources-filter resources-filter--active" type="button"
                        data-resource-filter="all">All resources</button>
                    <button class="resources-filter" type="button" data-resource-filter="available">Available</button>
                    <button class="resources-filter" type="button" data-resource-filter="limited">Limited</button>
                    <button class="resources-filter" type="button" data-resource-filter="critical">Critical</button>
                </div>
                <div class="resources-grid" id="resources-grid">
                    <?php foreach ($resourceCards as $card): ?>
                        <article
                            class="resources-card resources-card--<?= htmlspecialchars((string) ($card['status'] ?? 'available')) ?>"
                            data-resource-type="<?= htmlspecialchars(strtolower((string) ($card['type'] ?? 'resource'))) ?>"
                            data-resource-name="<?= htmlspecialchars(strtolower((string) ($card['name'] ?? 'resource'))) ?>"
                            data-resource-status="<?= htmlspecialchars((string) ($card['status'] ?? 'available')) ?>">
                            <div class="resources-card-head">
                                <span
                                    class="resources-card-badge resources-card-badge--<?= htmlspecialchars((string) ($card['status'] ?? 'available')) ?>"><?= htmlspecialchars((string) ($card['badge'] ?? 'Status')) ?></span>
                            </div>
                            <h3><?= htmlspecialchars((string) ($card['name'] ?? 'Resource')) ?></h3>
                            <p><?= htmlspecialchars((string) ($card['meta'] ?? '')) ?></p>
                            <div class="resources-card-meta">
                                <strong><?= htmlspecialchars((string) ($card['primary'] ?? '0')) ?></strong>
                                <small><?= htmlspecialchars((string) ($card['secondary'] ?? '')) ?></small>
                            </div>
                            <?php if (!empty($canManageResources) && ($card['metricKey'] ?? '') === 'bed_capacity'): ?>
                                <div class="resources-bed-actions" aria-label="Adjust available beds">
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($resourcesCsrf) ?>">
                                        <input type="hidden" name="action" value="adjust_bed">
                                        <input type="hidden" name="change" value="-1">
                                        <button type="submit" aria-label="Subtract one available bed"
                                            <?= (int) $card['current'] <= 0 ? 'disabled' : '' ?>>−</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($resourcesCsrf) ?>">
                                        <input type="hidden" name="action" value="adjust_bed">
                                        <input type="hidden" name="change" value="1">
                                        <button type="submit" aria-label="Add one available bed"
                                            <?= (int) $card['current'] >= (int) $card['maximum'] ? 'disabled' : '' ?>>+</button>
                                    </form>
                                    <button type="button" class="resources-bed-edit" id="resources-bed-edit"
                                        data-current-maximum="<?= (int) $card['maximum'] ?>">Edit maximum</button>
                                </div>
                            <?php elseif (!empty($canManageResources) && in_array(($card['metricKey'] ?? ''), ['ambulance_bay', 'ptv'], true)): ?>
                                <button type="button" class="resources-vehicle-add"
                                    data-resource-vehicle-add="<?= htmlspecialchars($card['metricKey'] === 'ptv' ? 'PTV' : 'Ambulance') ?>">
                                    Add <?= htmlspecialchars($card['metricKey'] === 'ptv' ? 'PTV' : 'ambulance') ?>
                                </button>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="resources-table-panel" aria-label="Vehicle resource inventory">
                <div class="resources-table-wrap">
                    <table class="resources-table">
                        <thead>
                            <tr>
                                <th>Resource</th>
                                <th>Type</th>
                                <th>Plate</th>
                                <th>Driver</th>
                                <th>Status</th>
                                <th>ETA / Notes</th>
                                <?php if (!empty($canManageResources)): ?><th>Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="resources-table-body">
                            <?php foreach ($fleet as $vehicle): ?>
                                <tr data-resource-type="<?= htmlspecialchars(strtolower((string) ($vehicle['type'] ?? 'resource'))) ?>"
                                    data-resource-status="<?= htmlspecialchars((string) ($vehicle['filterStatus'] ?? 'available')) ?>"
                                    data-resource-name="<?= htmlspecialchars(strtolower((string) ($vehicle['name'] ?? 'resource'))) ?>">
                                    <td><strong><?= htmlspecialchars((string) ($vehicle['name'] ?? 'Vehicle')) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($vehicle['type'] ?? 'Vehicle')) ?></td>
                                    <td><?= htmlspecialchars((string) ($vehicle['plateNumber'] ?: 'Not recorded')) ?></td>
                                    <td><?= htmlspecialchars((string) ($vehicle['driverName'] ?: 'Not assigned')) ?></td>
                                    <td><span
                                            class="resources-status resources-status--<?= htmlspecialchars((string) ($vehicle['status'] ?? 'available')) ?>"><?= htmlspecialchars((string) ($vehicle['statusLabel'] ?? 'Available')) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($vehicle['eta'] ?? '—')) ?><?php if (!empty($vehicle['note'])): ?><span
                                                class="resources-note"><?= htmlspecialchars((string) $vehicle['note']) ?></span><?php endif; ?>
                                    </td>
                                    <?php if (!empty($canManageResources)): ?>
                                        <td class="resources-vehicle-actions">
                                            <button type="button" data-resource-vehicle-edit
                                                data-vehicle-id="<?= (int) $vehicle['id'] ?>"
                                                data-vehicle-type="<?= htmlspecialchars((string) $vehicle['type']) ?>"
                                                data-call-sign="<?= htmlspecialchars((string) $vehicle['name']) ?>"
                                                data-plate-number="<?= htmlspecialchars((string) $vehicle['plateNumber']) ?>"
                                                data-driver-name="<?= htmlspecialchars((string) $vehicle['driverName']) ?>"
                                                data-vehicle-status="<?= htmlspecialchars((string) $vehicle['status']) ?>">Edit</button>
                                            <form method="post" onsubmit="return confirm('Delete this vehicle from the hospital fleet?')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($resourcesCsrf) ?>">
                                                <input type="hidden" name="action" value="vehicle_delete">
                                                <input type="hidden" name="vehicle_id" value="<?= (int) $vehicle['id'] ?>">
                                                <button type="submit" class="resources-vehicle-delete">Delete</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($fleet)): ?>
                                <tr><td colspan="<?= !empty($canManageResources) ? '7' : '6' ?>" class="resources-empty">No vehicles registered for this hospital.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php if (!empty($canManageResources)): ?>
                <dialog class="resources-vehicle-dialog" id="resources-bed-dialog"
                    aria-labelledby="resources-bed-dialog-title">
                    <form method="post" class="resources-vehicle-form">
                        <div class="resources-vehicle-dialog-head">
                            <div>
                                <p>Bed capacity</p>
                                <h2 id="resources-bed-dialog-title">Edit maximum beds</h2>
                            </div>
                            <button type="button" class="resources-vehicle-close" data-resource-bed-close
                                aria-label="Close bed capacity form">×</button>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($resourcesCsrf) ?>">
                        <input type="hidden" name="action" value="update_bed_max">
                        <label class="resources-bed-maximum-field">Maximum beds
                            <input type="number" name="max_beds" id="resources-bed-maximum" min="0"
                                max="4294967295" step="1" required>
                        </label>
                        <p class="resources-bed-capacity-note">Current occupancy is preserved. The maximum cannot be set below occupied beds.</p>
                        <div class="resources-vehicle-dialog-actions">
                            <button type="button" data-resource-bed-close>Cancel</button>
                            <button type="submit" class="resources-vehicle-save">Save maximum</button>
                        </div>
                    </form>
                </dialog>
                <dialog class="resources-vehicle-dialog" id="resources-vehicle-dialog"
                    aria-labelledby="resources-vehicle-dialog-title">
                    <form method="post" class="resources-vehicle-form" id="resources-vehicle-form">
                        <div class="resources-vehicle-dialog-head">
                            <div>
                                <p>Hospital fleet</p>
                                <h2 id="resources-vehicle-dialog-title">Add vehicle</h2>
                            </div>
                            <button type="button" class="resources-vehicle-close" data-resource-vehicle-close
                                aria-label="Close vehicle form">×</button>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($resourcesCsrf) ?>">
                        <input type="hidden" name="action" value="vehicle_save">
                        <input type="hidden" name="vehicle_id" id="resources-vehicle-id">
                        <label>Vehicle type
                            <select name="vehicle_type" id="resources-vehicle-type" required>
                                <option value="Ambulance">Ambulance</option>
                                <option value="PTV">PTV</option>
                            </select>
                        </label>
                        <label>Call sign
                            <input type="text" name="call_sign" id="resources-call-sign" maxlength="30" required>
                        </label>
                        <label>Plate number
                            <input type="text" name="plate_number" id="resources-plate-number" maxlength="30" required>
                        </label>
                        <label>Driver name
                            <input type="text" name="driver_name" id="resources-driver-name" maxlength="160" required>
                        </label>
                        <label>Status
                            <select name="status" id="resources-vehicle-status" required>
                                <option value="available">Available</option>
                                <option value="en_route">En route</option>
                                <option value="arrived">Arrived</option>
                                <option value="offline">Offline</option>
                            </select>
                        </label>
                        <div class="resources-vehicle-dialog-actions">
                            <button type="button" data-resource-vehicle-close>Cancel</button>
                            <button type="submit" class="resources-vehicle-save">Save vehicle</button>
                        </div>
                    </form>
                </dialog>
            <?php endif; ?>
        </main>
    </div>
    <?php if (!empty($resourceMessage)): ?>
        <div class="resources-notification-toast<?= ($resourceMessageType ?? 'success') === 'error' ? ' is-error' : '' ?>"
            id="resources-notification-toast" role="status" aria-live="polite"><?= htmlspecialchars($resourceMessage) ?></div>
    <?php endif; ?>
    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>