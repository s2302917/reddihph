<?php
/**
 * dispatchView.php
 *
 * VIEW — HTML only. No SQL, no business logic.
 * All variables ($barangays, $facilityStatus, $staffOnDuty,
 * $hospitalName, $coordinatorName, $coordinatorRole, $alertCount) are
 * injected by dispatchController::render().
 *
 * Every class/id starts with "incomingdispatch" so incomingdispatch.css
 * stays fully scoped and won't collide with other pages' styles.
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Incoming Dispatches') ?> — Reddiph</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet"
    href="<?php echo htmlspecialchars(assetUrl('public/css/incomingdispatch.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/incomingdispatch.css'); ?>">
</head>

<body class="incomingdispatch-body">

  <div class="incomingdispatch-shell">

    <!-- ===================== SIDEBAR ===================== -->
    <aside class="incomingdispatch-sidebar" id="incomingdispatch-sidebar">
      <div class="incomingdispatch-brand">
        <span class="incomingdispatch-brand-tag">ADMIN</span>
      </div>

      <nav class="incomingdispatch-nav" aria-label="Primary">
        <a href="dispatchController.php" class="incomingdispatch-nav-item incomingdispatch-nav-item--active"
          id="incomingdispatch-nav-dispatches">
          Dashboard </a>
        <a href="livemappingController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-mapping">
          Live Mapping
        </a>
        <a href="emergency_alertController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-alerts">
          Emergency Cases
        </a>

        <a href="staffschedController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-schedule">
          Staff Schedule
        </a>
        <a href="resourcesController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-resources">
          Resources
        </a>
        <a href="hospitalnetworkController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-network">
          Referral
        </a>
        <a href="analyticsController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-analytics">
          Analytics
        </a>
        <a href="demographicsController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-logs">
          Logs &amp; Demographics
        </a>
        <a href="settingsController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-settings">
          Settings
        </a>
      </nav>

      <div class="incomingdispatch-system-status" id="incomingdispatch-system-status">
        <span class="incomingdispatch-system-status-icon">!</span>
        <div>
          <p class="incomingdispatch-system-status-title">System Status</p>
          <p class="incomingdispatch-system-status-sub">All systems operational.</p>
        </div>
      </div>
    </aside>

    <!-- ===================== MAIN CONTENT ===================== -->
    <main class="incomingdispatch-main">

      <!-- Header -->
      <header class="incomingdispatch-header">
        <div class="incomingdispatch-header-copy">
          <h1 class="incomingdispatch-hospital-name"><?= htmlspecialchars($hospitalName ?? '') ?></h1>
          <h2 class="incomingdispatch-page-title"><?= htmlspecialchars($pageTitle ?? '') ?></h2>
        </div>

        <div class="incomingdispatch-header-controls">
          <button type="button" class="incomingdispatch-bell" id="incomingdispatch-bell-btn" aria-label="Notifications">
            🔔
            <?php if (($alertCount ?? 0) > 0): ?>
              <span class="incomingdispatch-bell-dot" id="incomingdispatch-bell-dot"></span>
            <?php endif; ?>
          </button>

          <div class="incomingdispatch-notification-panel" id="incomingdispatch-notification-panel" hidden>
            <strong>Active emergency dispatches</strong>
            <div id="incomingdispatch-notification-list">
              <?php foreach (($activeDispatches ?? []) as $notification): ?>
                <a href="<?= htmlspecialchars($notification['mapUrl']) ?>"
                  data-dispatch-id="<?= (int) $notification['id'] ?>"
                  data-barangay-id="<?= (int) $notification['barangayId'] ?>"
                  data-barangay-slug="<?= htmlspecialchars($notification['barangaySlug'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($notification['label']) ?><br>
                  <small><?= htmlspecialchars($notification['patientName'] ?? 'Patient name not provided') ?><?php if (!empty($notification['patientGender']) || $notification['patientAge'] !== null): ?>
                      ·
                      <?= htmlspecialchars(ucfirst((string) ($notification['patientGender'] ?? ''))) ?>
                      <?= $notification['patientAge'] !== null ? ', ' . (int) $notification['patientAge'] : '' ?>
                    <?php endif; ?></small><br>
                  <small><?= htmlspecialchars($notification['barangay']) ?> ·
                    <?= htmlspecialchars($notification['status']) ?> ·
                    <?= htmlspecialchars($notification['vehicleName']) ?> ·
                    <?= htmlspecialchars($notification['driverName']) ?></small>
                </a>
              <?php endforeach; ?>
              <?php if (empty($activeDispatches)): ?><span class="incomingdispatch-notification-empty">No active
                  emergencies</span><?php endif; ?>
            </div>
          </div>

          <button type="button" class="incomingdispatch-download" id="incomingdispatch-download-btn"
            aria-label="Download emergency report" title="Download emergency report">
            <span class="incomingdispatch-download-icon" aria-hidden="true"></span>
          </button>

          <div class="incomingdispatch-user" id="incomingdispatch-user-menu">
            <div class="incomingdispatch-user-avatar" aria-hidden="true">
              <?php if (!empty($coordinatorImage)): ?>
                <img src="<?= htmlspecialchars($coordinatorImage) ?>" alt="">
              <?php else: ?>
                <?= htmlspecialchars($coordinatorInitials ?? 'US') ?>
              <?php endif; ?>
            </div>
            <div class="incomingdispatch-user-text">
              <p class="incomingdispatch-user-name"><?= htmlspecialchars($coordinatorName ?? '') ?></p>
              <p class="incomingdispatch-user-role"><?= htmlspecialchars($coordinatorRole ?? '') ?></p>
            </div>
          </div>
        </div>
      </header>

      <!-- Barangay dispatch grid -->
      <section class="incomingdispatch-grid" id="incomingdispatch-barangay-grid" aria-label="Barangay dispatch status">
        <?php foreach (($barangays ?? []) as $b): ?>
          <button type="button" class="incomingdispatch-card <?= $b['isActive'] ? 'incomingdispatch-card--active' : '' ?>"
            id="incomingdispatch-card-<?= htmlspecialchars($b['slug']) ?>" data-barangay-id="<?= (int) $b['id'] ?>"
            data-count="<?= (int) $b['count'] ?>" data-map-url="<?= htmlspecialchars($b['mapUrl']) ?>"
            data-barangay-slug="<?= htmlspecialchars($b['slug']) ?>"
            data-dispatch-ids="<?= htmlspecialchars(json_encode(array_map('intval', array_column($b['dispatches'] ?? [], 'id'))), ENT_QUOTES, 'UTF-8') ?>">
            <span class="incomingdispatch-card-pin" aria-hidden="true">📍</span>
            <?php if ($b['count'] > 0): ?>
              <span class="incomingdispatch-card-badge" aria-label="New active dispatch"></span>
            <?php endif; ?>
            <span class="incomingdispatch-card-name"><?= htmlspecialchars($b['name']) ?></span>
            <span
              class="incomingdispatch-card-status <?= $b['isActive'] ? 'incomingdispatch-card-status--active' : '' ?>"><?= htmlspecialchars($b['statusLabel']) ?></span>
          </button>
        <?php endforeach; ?>

        <button type="button" class="incomingdispatch-card incomingdispatch-card--network"
          id="incomingdispatch-card-allbrgys" data-map-url="<?= htmlspecialchars(assetUrl('app/controllers/livemappingController.php'), ENT_QUOTES, 'UTF-8') ?>">
          <span class="incomingdispatch-card-grid-icon" aria-hidden="true">▦</span>
          <span class="incomingdispatch-card-name">All Barangays</span>
          <span class="incomingdispatch-card-status">View Network</span>
        </button>
      </section>

      <section class="incomingdispatch-dispatch-table" aria-labelledby="incomingdispatch-active-title">
        <div class="incomingdispatch-dispatch-table-header">
          <div>
            <h3 id="incomingdispatch-active-title">Incoming dispatch details</h3>
            <p>Active cases requiring hospital coordination</p>
          </div>
          <span class="incomingdispatch-dispatch-table-count"><?= (int) ($alertCount ?? 0) ?> active</span>
        </div>
        <div class="incomingdispatch-dispatch-table-scroll">
          <table>
            <thead>
              <tr>
                <th>Patient</th>
                <th>Barangay</th>
                <th>Dispatch purpose</th>
                <th>Vehicle</th>
                <th>Driver</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="incomingdispatch-active-list">
              <?php if (empty($activeDispatches)): ?>
                <tr>
                  <td colspan="6" class="incomingdispatch-dispatch-table-empty">No active dispatches</td>
                </tr>
              <?php else: ?>
                <?php foreach ($activeDispatches as $dispatch): ?>
                  <tr data-dispatch-id="<?= (int) $dispatch['id'] ?>">
                    <td>
                      <strong><?= htmlspecialchars($dispatch['patientName']) ?></strong>
                      <?php if ($dispatch['patientAge'] !== null || $dispatch['patientGender']): ?>
                        <small><?= $dispatch['patientAge'] !== null ? (int) $dispatch['patientAge'] . ' years' : '' ?><?= $dispatch['patientAge'] !== null && $dispatch['patientGender'] ? ' · ' : '' ?><?= htmlspecialchars(ucfirst((string) ($dispatch['patientGender'] ?? ''))) ?></small>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($dispatch['barangay']) ?></td>
                    <td><?= htmlspecialchars($dispatch['dispatchPurpose']) ?></td>
                    <td>
                      <?= htmlspecialchars($dispatch['vehicleName']) ?><small><?= htmlspecialchars($dispatch['transportType']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($dispatch['driverName']) ?></td>
                    <td><span class="incomingdispatch-status-pill"><?= htmlspecialchars($dispatch['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Facility status + Staff on duty -->
      <section class="incomingdispatch-panels-row">

        <div class="incomingdispatch-panel" id="incomingdispatch-panel-facility">
          <h3 class="incomingdispatch-panel-title">Facility Status</h3>
          <div class="incomingdispatch-panel-body">

            <?php foreach (($facilityStatus ?? []) as $key => $metric): ?>
              <div class="incomingdispatch-metric-row" id="incomingdispatch-metric-<?= htmlspecialchars($key) ?>">

                <span class="incomingdispatch-metric-label">
                  <?= htmlspecialchars($metric['label']) ?>
                </span>

                <?php if (isset($metric['percent'])): ?>

                  <div class="incomingdispatch-metric-bar-track">
                    <div class="incomingdispatch-metric-bar-fill <?= htmlspecialchars($metric['capacityClass']) ?>"
                      data-percent="<?= (int) $metric['percent'] ?>">
                    </div>
                  </div>

                  <span class="incomingdispatch-metric-value">
                    <?= htmlspecialchars($metric['display']) ?>
                  </span>

                <?php endif; ?>

              </div>
            <?php endforeach; ?>

          </div>
        </div>

        <div class="incomingdispatch-panel" id="incomingdispatch-panel-staff">
          <h3 class="incomingdispatch-panel-title">Staff On Duty</h3>
          <div class="incomingdispatch-panel-body">
            <div id="incomingdispatch-staff-list">
              <?php foreach (($staffOnDuty ?? []) as $i => $s): ?>
                <div class="incomingdispatch-staff-row" id="incomingdispatch-staff-<?= $i ?>">
                  <span class="incomingdispatch-staff-avatar">
                    <?php if (!empty($s['image'])): ?><img src="<?= htmlspecialchars($s['image']) ?>" alt="">
                    <?php else: ?>     <?= htmlspecialchars($s['initials']) ?>   <?php endif; ?>
                  </span>
                  <div class="incomingdispatch-staff-text">
                    <p class="incomingdispatch-staff-name"><?= htmlspecialchars($s['name']) ?></p>
                    <p class="incomingdispatch-staff-dept"><?= htmlspecialchars($s['department']) ?></p>
                    <?php if (!empty($s['dutyHours'])): ?>
                      <p class="incomingdispatch-staff-dept"><?= htmlspecialchars($s['dutyHours']) ?></p><?php endif; ?>
                  </div>
                  <span
                    class="incomingdispatch-staff-dot <?= $s['isAvailable'] ? 'incomingdispatch-staff-dot--on' : 'incomingdispatch-staff-dot--off' ?>"></span>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="incomingdispatch-staff-pagination">
              <button type="button" id="incomingdispatch-staff-prev" aria-label="Previous staff">‹</button>
              <span id="incomingdispatch-staff-page" aria-live="polite">1 / 1</span>
              <button type="button" id="incomingdispatch-staff-next" aria-label="Next staff">›</button>
            </div>

          </div>
        </div>

      </section>

    </main>
  </div>

  <div class="incomingdispatch-download-overlay" id="incomingdispatch-download-overlay" aria-hidden="true">
    <form class="incomingdispatch-download-dialog" method="get" action="../../api/emergency-report.php">
      <h2>Download emergency report</h2>
      <label for="incomingdispatch-report-range">Date range</label>
      <select id="incomingdispatch-report-range" name="range">
        <option value="today">Today</option>
        <option value="7">Last 7 days</option>
        <option value="15">Last 15 days</option>
        <option value="30">Last 30 days</option>
        <option value="365">Last year</option>
      </select>
      <div class="incomingdispatch-download-actions">
        <button type="button" id="incomingdispatch-download-cancel">Cancel</button>
        <button type="submit">Download PDF</button>
      </div>
    </form>
  </div>

  <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
  <script
    src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>