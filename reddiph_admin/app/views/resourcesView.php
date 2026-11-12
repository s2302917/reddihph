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
                <details class="admin-resources-menu" open>
                    <summary class="emergency_alert_nav_item emergency_alert_nav_item_active"
                        id="emergency_alert_nav_resources"><span>Resources</span></summary>
                    <div class="admin-resources-subnav">
                        <?php foreach ([
                            'bed-capacity' => 'Bed Capacity',
                            'ambulance' => 'Ambulance',
                            'ptv' => 'PTV',
                        ] as $sectionKey => $sectionLabel): ?>
                            <a href="resourcesController.php?section=<?= rawurlencode($sectionKey) ?>"
                                <?= $resourceSection === $sectionKey ? 'aria-current="page"' : '' ?>>
                                <?= htmlspecialchars($sectionLabel) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
                <a href="hospitalnetworkController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_network"><span>Referral</span></a>
                <a href="demographicsController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_logs"><span>Logs &amp; Demographics</span></a>
                <a href="manageAccountsController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_manage_accounts"><span>Manage Accounts</span></a>
                <a href="settingsController.php" class="emergency_alert_nav_item"
                    id="emergency_alert_nav_settings"><span>Settings</span></a>
            </nav>
        </aside>

        <main class="emergency_alert_main resources-main" id="emergency_alert_main"
            data-resource-selected-section="<?= htmlspecialchars($resourceSection) ?>">
            <header class="emergency_alert_header resources-header" id="emergency_alert_header">
                <div class="emergency_alert_header_copy" id="emergency_alert_header_copy">
                    <h1 class="emergency_alert_hospital_name" id="emergency_alert_hospital_name">
                        <?= htmlspecialchars($hospitalName ?? '') ?>
                    </h1>
                    <h2 class="emergency_alert_title" id="emergency_alert_title">Resources</h2>
                    <p class="emergency_alert_subtitle">Hospital capacity and operational resources</p>
                </div>
                <div class="emergency_alert_header_controls" id="emergency_alert_header_controls">
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

            <?php if ($resourceDataError !== null): ?>
                <p class="resources-schema-notice resources-schema-notice--error" role="alert">
                    <?= htmlspecialchars((string) $resourceDataError) ?>
                </p>
            <?php endif; ?>

            <div class="resources-toolbar resources-toolbar--inventory">
                <?php if (!empty($canManageResources)): ?>
                    <?php if ($resourceSection === 'bed-capacity'): ?>
                        <button type="button" class="resources-vehicle-add" data-resource-bed-add>Add Bed</button>
                    <?php endif; ?>
                    <?php if ($resourceSection === 'ambulance' || $resourceSection === 'ptv'): ?>
                        <button type="button" class="resources-vehicle-add" data-resource-vehicle-add
                            data-resource-default-type="<?= $resourceSection === 'ptv' ? 'PTV' : 'Ambulance' ?>">Add Vehicle</button>
                    <?php endif; ?>
                <?php endif; ?>
                <label class="resources-status-filter" for="resources-status-filter">Status
                    <select id="resources-status-filter">
                        <option value="all">All statuses</option>
                        <?php if ($resourceSection === 'bed-capacity'): ?>
                            <option value="ready">Ready</option>
                            <option value="preparing">Preparing</option>
                            <option value="occupied">Occupied</option>
                            <option value="cleaning">Cleaning</option>
                        <?php else: ?>
                            <option value="available">Available</option>
                            <option value="en_route">En route</option>
                            <option value="arrived">Arrived</option>
                            <option value="offline">Offline</option>
                        <?php endif; ?>
                    </select>
                </label>
                <label class="emergency_alert_search" id="emergency_alert_search_wrap">
                    <span class="emergency_alert_search_icon" aria-hidden="true">⌕</span>
                    <input type="search" id="resources-search-input" class="emergency_alert_search_input"
                        placeholder="Search this inventory..." autocomplete="off" aria-label="Search resources">
                </label>
            </div>

            <?php
            $vehicleSections = [
                'ambulance' => ['label' => 'Ambulance', 'filter' => 'Ambulance'],
                'ptv' => ['label' => 'PTV', 'filter' => 'PTV'],
            ];
            ?>
            <section class="resources-section" id="resources-section-bed-capacity"
                data-resource-section="bed-capacity" aria-label="Bed capacity and management"
                <?= $resourceSection === 'bed-capacity' ? '' : 'hidden' ?>>
                <header class="resources-section-heading">
                    <div>
                        <h3>Bed Capacity</h3>
                        <p>Live totals are calculated from the hospital bed inventory.</p>
                    </div>
                    <span><?= (int) array_sum(array_column($bedAreas, 'total')) ?> beds</span>
                </header>
                <?php if (!$bedAssignmentsAvailable): ?>
                    <p class="resources-schema-notice" role="status">
                        Bed inventory is available. Room and staff assignments require the bed assignment migration.
                    </p>
                <?php elseif (!$multipleBedStaffAvailable): ?>
                    <p class="resources-schema-notice" role="status">
                        Single doctor and nurse assignments remain available. Multiple staff assignments require the bed staff assignments migration.
                    </p>
                <?php endif; ?>
                <div class="resources-area-grid">
                    <?php foreach ($bedAreas as $area): ?>
                        <article class="resources-area-card" data-resource-bed-area="<?= htmlspecialchars((string) $area['key']) ?>">
                            <header class="resources-area-heading">
                                <h4><?= htmlspecialchars((string) $area['label']) ?></h4>
                                <span><?= (int) $area['total'] ?> total</span>
                            </header>
                            <dl class="resources-bed-counts">
                                <div><dt>Available</dt><dd><?= (int) $area['available'] ?></dd></div>
                                <div><dt>Occupied</dt><dd><?= (int) $area['occupied'] ?></dd></div>
                                <div><dt>Unavailable</dt><dd><?= (int) $area['unavailable'] ?></dd></div>
                            </dl>
                            <div class="resources-table-wrap">
                                <table class="resources-table resources-bed-table">
                                    <thead>
                                        <tr>
                                            <th><button type="button" data-resource-sort="0">Bed / room</button></th>
                                            <th><button type="button" data-resource-sort="1">Room</button></th>
                                            <th><button type="button" data-resource-sort="2">Status</button></th>
                                            <th><button type="button" data-resource-sort="3">Doctor</button></th>
                                            <th><button type="button" data-resource-sort="4">Nurse</button></th>
                                            <th><button type="button" data-resource-sort="5">Patient</button></th>
                                            <?php if (!empty($canManageResources)): ?><th>Actions</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($area['beds'] as $bed): ?>
                                            <tr data-resource-name="<?= htmlspecialchars('bed ' . $bed['number'] . ' ' . $bed['room']) ?>"
                                                data-resource-status="<?= htmlspecialchars((string) $bed['status']) ?>"
                                                data-resource-bed-details
                                                data-bed-id="<?= (int) $bed['id'] ?>"
                                                data-bed-type="<?= htmlspecialchars((string) $bed['type']) ?>"
                                                data-bed-number="<?= (int) $bed['number'] ?>"
                                                data-room-number="<?= htmlspecialchars((string) $bed['room']) ?>"
                                                data-bed-status="<?= htmlspecialchars((string) $bed['status']) ?>"
                                                data-bed-status-label="<?= htmlspecialchars((string) $bed['statusLabel']) ?>"
                                                data-doctor-id="<?= htmlspecialchars((string) $bed['doctorId']) ?>"
                                                data-nurse-id="<?= htmlspecialchars((string) $bed['nurseId']) ?>"
                                                data-doctor-ids="<?= htmlspecialchars(json_encode($bed['doctorIds'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?: '[]') ?>"
                                                data-nurse-ids="<?= htmlspecialchars(json_encode($bed['nurseIds'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?: '[]') ?>"
                                                data-doctor-name="<?= htmlspecialchars((string) $bed['doctorName']) ?>"
                                                data-nurse-name="<?= htmlspecialchars((string) $bed['nurseName']) ?>"
                                                data-doctor-names="<?= htmlspecialchars(json_encode($bed['doctorNames'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?: '[]') ?>"
                                                data-nurse-names="<?= htmlspecialchars(json_encode($bed['nurseNames'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) ?: '[]') ?>"
                                                data-patient-name="<?= htmlspecialchars((string) $bed['patientName']) ?>"
                                                data-incident-id="<?= htmlspecialchars((string) ($bed['incidentId'] ?? '')) ?>"
                                                data-incident-type="<?= htmlspecialchars((string) $bed['incidentType']) ?>"
                                                data-incident-status="<?= htmlspecialchars((string) $bed['incidentStatus']) ?>"
                                                data-triage-category="<?= htmlspecialchars((string) $bed['triageCategory']) ?>"
                                                data-patient-age="<?= htmlspecialchars((string) ($bed['patientAge'] ?? '')) ?>"
                                                data-patient-gender="<?= htmlspecialchars((string) $bed['patientGender']) ?>"
                                                data-reported-at="<?= htmlspecialchars((string) $bed['reportedAt']) ?>"
                                                data-heart-rate="<?= htmlspecialchars((string) ($bed['vitals']['heartRate'] ?? '')) ?>"
                                                data-blood-pressure="<?= htmlspecialchars((string) ($bed['vitals']['bloodPressure'] ?? '')) ?>"
                                                data-spo2="<?= htmlspecialchars((string) ($bed['vitals']['spo2'] ?? '')) ?>"
                                                data-gcs="<?= htmlspecialchars((string) ($bed['vitals']['gcs'] ?? '')) ?>"
                                                data-vitals-recorded-at="<?= htmlspecialchars((string) ($bed['vitals']['recordedAt'] ?? '')) ?>"
                                                data-nurse-notes="<?= htmlspecialchars((string) ($bed['nurseNotes'] ?? '')) ?>"
                                                data-incident-notes="<?= htmlspecialchars((string) ($bed['incidentNotes'] ?? '')) ?>"
                                                data-patient-notes="<?= htmlspecialchars((string) ($bed['patientNotes'] ?? '')) ?>"
                                                data-clinical-notes="[]"
                                                tabindex="0"
                                                aria-label="<?= htmlspecialchars($bed['room'] !== '' ? $bed['room'] : 'Bed ' . (int) $bed['number']) ?>, <?= htmlspecialchars((string) $bed['statusLabel']) ?>">
                                                <td>
                                                    <strong class="resources-bed-primary-label"><?= htmlspecialchars($bed['room'] !== '' ? $bed['room'] : 'Bed ' . (int) $bed['number']) ?></strong>
                                                    <?php if ($bed['room'] !== ''): ?>
                                                        <small class="resources-bed-number-label">Bed <?= (int) $bed['number'] ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($bed['room'] !== '' ? $bed['room'] : 'Not recorded') ?></td>
                                                <td><span class="resources-status resources-status--<?= htmlspecialchars((string) $bed['status']) ?>">
                                                    <?= htmlspecialchars((string) $bed['statusLabel']) ?>
                                                </span></td>
                                                <td><?= htmlspecialchars($bed['doctorName'] ?: 'Not assigned') ?></td>
                                                <td><?= htmlspecialchars($bed['nurseName'] ?: 'Not assigned') ?></td>
                                                <td><?= htmlspecialchars($bed['patientName'] ?: '—') ?></td>
                                                <?php if (!empty($canManageResources)): ?>
                                                    <td>
                                                        <button type="button" class="resources-bed-view"
                                                            data-resource-bed-view>View</button>
                                                        <button type="button" class="resources-bed-view"
                                                            data-resource-bed-edit>Edit</button>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$area['beds']): ?>
                                            <tr data-resource-empty>
                                                <td colspan="<?= !empty($canManageResources) ? '7' : '6' ?>" class="resources-empty">
                                                    No beds are registered in this area.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr data-resource-search-empty hidden>
                                            <td colspan="<?= !empty($canManageResources) ? '7' : '6' ?>" class="resources-empty">
                                                No beds match the current search or status filter.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <nav class="resources-table-pagination" aria-label="<?= htmlspecialchars($area['label']) ?> bed pages"
                                    data-resource-pagination hidden>
                                    <button type="button" data-resource-page-previous disabled>Previous</button>
                                    <span data-resource-page-label aria-live="polite">Page 1 of 1</span>
                                    <button type="button" data-resource-page-next disabled>Next</button>
                                </nav>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php foreach ($vehicleSections as $sectionKey => $section): ?>
                <?php
                $sectionFleet = array_values(array_filter(
                    $fleet,
                    static fn(array $vehicle): bool => $vehicle['type'] === $section['filter']
                ));
                $sectionCounts = $vehicleCounts[strtolower($section['filter'])] ?? [
                    'total' => count($sectionFleet),
                    'available' => 0,
                ];
                ?>
                <section class="resources-section" id="resources-section-<?= htmlspecialchars($sectionKey) ?>"
                    data-resource-section="<?= htmlspecialchars($sectionKey) ?>"
                    aria-label="<?= htmlspecialchars($section['label']) ?> vehicles"
                    <?= $resourceSection === $sectionKey ? '' : 'hidden' ?>>
                    <header class="resources-section-heading">
                        <div>
                            <h3><?= htmlspecialchars($section['label']) ?></h3>
                            <p>Registered <?= htmlspecialchars(strtolower($section['label'])) ?> vehicles.</p>
                        </div>
                        <span><?= (int) $sectionCounts['total'] ?> total · <?= (int) $sectionCounts['available'] ?> available</span>
                    </header>
                    <div class="resources-table-wrap">
                        <table class="resources-table">
                            <thead>
                                <tr>
                                    <th><button type="button" data-resource-sort="0">Call sign</button></th>
                                    <th><button type="button" data-resource-sort="1">Plate</button></th>
                                    <th><button type="button" data-resource-sort="2">Driver</button></th>
                                    <th><button type="button" data-resource-sort="3">Status</button></th>
                                    <th><button type="button" data-resource-sort="4">ETA / Notes</button></th>
                                    <?php if (!empty($canManageResources)): ?><th>Actions</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sectionFleet as $vehicle): ?>
                                    <tr data-resource-type="<?= htmlspecialchars(strtolower((string) $vehicle['type'])) ?>"
                                        data-resource-status="<?= htmlspecialchars((string) $vehicle['status']) ?>"
                                        data-resource-name="<?= htmlspecialchars(strtolower((string) $vehicle['name'])) ?>">
                                        <td><strong><?= htmlspecialchars((string) $vehicle['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($vehicle['plateNumber'] ?: 'Not recorded') ?></td>
                                        <td><?= htmlspecialchars($vehicle['driverName'] ?: 'Not assigned') ?></td>
                                        <td><span class="resources-status resources-status--<?= htmlspecialchars((string) $vehicle['status']) ?>">
                                            <?= htmlspecialchars((string) $vehicle['statusLabel']) ?>
                                        </span></td>
                                        <td><?= htmlspecialchars((string) $vehicle['eta']) ?>
                                            <?php if (!empty($vehicle['note'])): ?><span class="resources-note"><?= htmlspecialchars((string) $vehicle['note']) ?></span><?php endif; ?>
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
                                                    <input type="hidden" name="resource_section" value="<?= htmlspecialchars($resourceSection) ?>">
                                                    <input type="hidden" name="action" value="vehicle_delete">
                                                    <input type="hidden" name="vehicle_id" value="<?= (int) $vehicle['id'] ?>">
                                                    <button type="submit" class="resources-vehicle-delete">Delete</button>
                                                </form>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$sectionFleet): ?>
                                    <tr data-resource-empty><td colspan="<?= !empty($canManageResources) ? '6' : '5' ?>" class="resources-empty">
                                        No <?= htmlspecialchars(strtolower($section['label'])) ?> vehicles are registered.
                                    </td></tr>
                                <?php endif; ?>
                                <tr data-resource-search-empty hidden>
                                    <td colspan="<?= !empty($canManageResources) ? '6' : '5' ?>" class="resources-empty">
                                        No vehicles match the current search or status filter.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <nav class="resources-table-pagination" aria-label="<?= htmlspecialchars($section['label']) ?> pages"
                            data-resource-pagination hidden>
                            <button type="button" data-resource-page-previous disabled>Previous</button>
                            <span data-resource-page-label aria-live="polite">Page 1 of 1</span>
                            <button type="button" data-resource-page-next disabled>Next</button>
                        </nav>
                    </div>
                </section>
            <?php endforeach; ?>

            <?php if (!empty($canManageResources)): ?>
                <dialog class="resources-vehicle-dialog" id="resources-bed-dialog"
                    aria-labelledby="resources-bed-dialog-title">
                    <form method="post" class="resources-vehicle-form resources-bed-form" id="resources-bed-form">
                        <div class="resources-vehicle-dialog-head">
                            <div>
                                <p>Bed management</p>
                                <h2 id="resources-bed-dialog-title">Register bed</h2>
                            </div>
                            <button type="button" class="resources-vehicle-close" data-resource-bed-close
                                aria-label="Close bed form">×</button>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($resourcesCsrf) ?>">
                        <input type="hidden" name="resource_section" value="<?= htmlspecialchars($resourceSection) ?>">
                        <input type="hidden" name="action" value="bed_save">
                        <input type="hidden" name="bed_id" id="resources-bed-id">
                        <div class="resources-bed-edit-fields" data-bed-edit-fields>
                            <label><span>Bed area <span class="resources-required-indicator" aria-hidden="true">*</span></span>
                                <select name="bed_type" id="resources-bed-type" required>
                                    <option value="er">Emergency Room (ER)</option>
                                    <option value="pediatric">Pediatric Bay</option>
                                </select>
                            </label>
                            <label><span>Bed number <span class="resources-required-indicator" aria-hidden="true">*</span></span>
                                <input type="number" name="bed_number" id="resources-bed-number" min="1"
                                    max="65535" step="1" required>
                            </label>
                        <?php if ($bedAssignmentsAvailable): ?>
                            <label><span>Room number</span>
                                <input type="text" name="room_number" id="resources-room-number" maxlength="30">
                            </label>
                        <?php endif; ?>
                            <label><span>Status <span class="resources-required-indicator" aria-hidden="true">*</span></span>
                                <select name="bed_status" id="resources-bed-status" required>
                                    <option value="ready">Ready</option>
                                    <option value="preparing">Preparing</option>
                                    <option value="occupied">Occupied</option>
                                    <option value="cleaning">Cleaning</option>
                                </select>
                            </label>
                        <?php if ($bedAssignmentsAvailable): ?>
                            <div class="resources-bed-staff-picker" data-staff-picker="doctor"
                                data-multiple-available="<?= $multipleBedStaffAvailable ? 'true' : 'false' ?>">
                                <label for="resources-bed-doctor-search">Assigned doctors</label>
                                <div class="resources-bed-picker-search">
                                    <input type="search" id="resources-bed-doctor-search" class="resources-bed-staff-search"
                                        placeholder="Name or specialization" autocomplete="off">
                                    <button type="button" class="resources-bed-search-button" data-staff-search="doctor">Search</button>
                                    <button type="button" class="resources-bed-clear-button" data-staff-clear="doctor">Clear</button>
                                </div>
                                <p class="resources-bed-staff-feedback" data-staff-feedback="doctor" aria-live="polite"></p>
                                <div class="resources-bed-table-wrap">
                                    <table class="resources-bed-selection-table">
                                        <thead><tr><th scope="col">Select</th><th scope="col">Doctor / specialization</th></tr></thead>
                                        <tbody data-staff-results="doctor"></tbody>
                                    </table>
                                </div>
                                <div class="resources-bed-table-pagination" data-staff-pagination="doctor">
                                    <button type="button" data-staff-prev="doctor">Previous</button>
                                    <span data-staff-page="doctor">Page 1 of 1</span>
                                    <button type="button" data-staff-next="doctor">Next</button>
                                </div>
                                <small class="resources-bed-staff-summary" data-staff-summary="doctor">0 doctors selected</small>
                                <div data-staff-selected="doctor" hidden></div>
                                <input type="hidden" name="doctor_id" id="resources-bed-doctor" value="">
                            </div>
                            <div class="resources-bed-staff-picker" data-staff-picker="nurse"
                                data-multiple-available="<?= $multipleBedStaffAvailable ? 'true' : 'false' ?>">
                                <label for="resources-bed-nurse-search">Assigned nurses</label>
                                <div class="resources-bed-picker-search">
                                    <input type="search" id="resources-bed-nurse-search" class="resources-bed-staff-search"
                                        placeholder="Name or staff information" autocomplete="off">
                                    <button type="button" class="resources-bed-search-button" data-staff-search="nurse">Search</button>
                                    <button type="button" class="resources-bed-clear-button" data-staff-clear="nurse">Clear</button>
                                </div>
                                <p class="resources-bed-staff-feedback" data-staff-feedback="nurse" aria-live="polite"></p>
                                <div class="resources-bed-table-wrap">
                                    <table class="resources-bed-selection-table">
                                        <thead><tr><th scope="col">Select</th><th scope="col">Nurse / staff information</th></tr></thead>
                                        <tbody data-staff-results="nurse"></tbody>
                                    </table>
                                </div>
                                <div class="resources-bed-table-pagination" data-staff-pagination="nurse">
                                    <button type="button" data-staff-prev="nurse">Previous</button>
                                    <span data-staff-page="nurse">Page 1 of 1</span>
                                    <button type="button" data-staff-next="nurse">Next</button>
                                </div>
                                <small class="resources-bed-staff-summary" data-staff-summary="nurse">0 nurses selected</small>
                                <div data-staff-selected="nurse" hidden></div>
                                <input type="hidden" name="nurse_id" id="resources-bed-nurse" value="">
                            </div>
                        <?php endif; ?>
                            <section class="resources-bed-incident-assignment" aria-labelledby="resources-bed-incident-title">
                                <div class="resources-bed-incident-heading">
                                    <div>
                                        <h3 id="resources-bed-incident-title">Incident assignment</h3>
                                        <p>Find an active incident at this hospital to associate with this bed.</p>
                                    </div>
                                    <button type="button" class="resources-bed-view" data-incident-search-toggle>Add incident</button>
                                </div>
                                <div class="resources-bed-current-incident" data-bed-current-incident></div>
                                <div class="resources-bed-incident-search" data-bed-incident-search hidden>
                                    <label for="resources-bed-incident-query">Search incident</label>
                                    <div class="resources-bed-incident-search-controls">
                                        <input type="search" id="resources-bed-incident-query"
                                            placeholder="Incident ID, type, or patient name" autocomplete="off">
                                        <button type="button" class="resources-bed-search-button" data-incident-search>Search</button>
                                        <button type="button" class="resources-bed-clear-button" data-incident-clear>Clear</button>
                                    </div>
                                    <p class="resources-bed-incident-feedback" data-incident-feedback aria-live="polite"></p>
                                    <div class="resources-bed-table-wrap">
                                        <table class="resources-bed-selection-table resources-bed-incident-table">
                                            <colgroup>
                                                <col class="resources-bed-incident-column-id">
                                                <col class="resources-bed-incident-column-type">
                                                <col class="resources-bed-incident-column-patient">
                                                <col class="resources-bed-incident-column-hospital">
                                                <col class="resources-bed-incident-column-status">
                                                <col class="resources-bed-incident-column-date">
                                                <col class="resources-bed-incident-column-action">
                                            </colgroup>
                                            <thead><tr><th scope="col">Incident ID</th><th scope="col">Type</th><th scope="col">Patient</th><th scope="col">Hospital</th><th scope="col">Status</th><th scope="col">Date and Time</th><th scope="col">Action</th></tr></thead>
                                            <tbody data-incident-results></tbody>
                                        </table>
                                    </div>
                                    <div class="resources-bed-table-pagination" data-incident-pagination>
                                        <button type="button" data-incident-prev>Previous</button>
                                        <span data-incident-page>Page 1 of 1</span>
                                        <button type="button" data-incident-next>Next</button>
                                    </div>
                                </div>
                                <button type="button" class="resources-bed-incident-remove"
                                    data-incident-unassign hidden>Remove incident</button>
                            </section>
                        </div>
                        <div class="resources-bed-details" id="resources-bed-details" hidden></div>
                        <div class="resources-vehicle-dialog-actions">
                            <button type="button" data-resource-bed-close>Cancel</button>
                            <button type="button" class="resources-vehicle-save" data-bed-switch-edit hidden>Edit bed</button>
                            <button type="submit" class="resources-vehicle-save" data-bed-save>Save bed</button>
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
                        <input type="hidden" name="resource_section" value="<?= htmlspecialchars($resourceSection) ?>">
                        <input type="hidden" name="action" value="vehicle_save">
                        <input type="hidden" name="vehicle_id" id="resources-vehicle-id">
                        <input type="hidden" name="vehicle_type" id="resources-vehicle-type"
                            value="<?= $resourceSection === 'ptv' ? 'PTV' : 'Ambulance' ?>">
                        <label><span>Call sign</span>
                            <input type="text" name="call_sign" id="resources-call-sign" maxlength="30" required>
                        </label>
                        <label><span>Plate number</span>
                            <input type="text" name="plate_number" id="resources-plate-number" maxlength="30" required>
                        </label>
                        <label><span>Driver name</span>
                            <input type="text" name="driver_name" id="resources-driver-name" maxlength="160" required>
                        </label>
                        <label id="resources-vehicle-status-field" hidden><span>Status</span>
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