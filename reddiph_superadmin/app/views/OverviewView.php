<?php
$roleColors = [
    'super_admin' => '#a52335',
    'hospital_admin' => '#d04b36',
    'barangay_admin' => '#7653a6',
    'doctor' => '#3552b7',
    'nurse' => '#3a7459',
    'responder' => '#bd6737',
    'volunteer' => '#27394d',
    'resident' => '#a7adb5',
];
$roleTotal = max(1, array_sum($roles));
$segments = [];
$offset = 0;
foreach ($roles as $role => $count) {
    if ($count === 0) {
        continue;
    }
    $next = $offset + ($count / $roleTotal * 100);
    $segments[] = ($roleColors[$role] ?? '#7653a6') . ' ' . $offset . '% ' . $next . '%';
    $offset = $next;
}
$roleGradient = $segments ? implode(', ', $segments) : '#292930 0% 100%';
$maxIncidentCount = max(1, ...array_column($incidentHistory, 'count'));
$chartPoints = [];
foreach ($incidentHistory as $index => $point) {
    $x = 8 + $index * 14;
    $y = 76 - ($point['count'] / $maxIncidentCount * 58);
    $chartPoints[] = $x . ',' . number_format($y, 2, '.', '');
}
?>
<section class="page-content overview-page">
  <header class="overview-heading">
    <div><h1>Good morning, <?= htmlspecialchars($user['name']) ?></h1><p><?= date('l, F j, Y') ?> · <?= htmlspecialchars($config['app']['env']) ?></p></div>
  </header>
  <section class="overview-banner">
    <div><h2>System overview</h2><p><?= number_format($summary['hospital_count']) ?> hospitals · <?= number_format($summary['barangay_count']) ?> barangays in the directory</p></div>
    <div class="banner-kpi"><strong><?= number_format($summary['open_incidents']) ?></strong><span>open incidents</span></div>
  </section>
  <section class="overview-stat-grid" aria-label="Key metrics">
    <article class="overview-stat"><span>Registered users</span><strong><?= number_format($summary['user_count']) ?></strong><small>Across all account roles</small></article>
    <article class="overview-stat"><span>Incidents today</span><strong><?= number_format($summary['incidents_today']) ?></strong><small>Based on report time</small></article>
    <article class="overview-stat"><span>Available ambulances</span><strong><?= number_format($summary['available_ambulances']) ?></strong><small>Marked available in the directory</small></article>
    <article class="overview-stat"><span>Ready beds</span><strong><?= number_format($summary['ready_beds']) ?></strong><small>Marked ready in the bed registry</small></article>
  </section>
  <div class="overview-analytics">
    <section class="overview-card incident-chart-card">
      <div class="overview-card-heading"><div><h2>Incident reports</h2><p>Daily reports over the last 7 days</p></div><span class="chart-total"><?= array_sum(array_column($incidentHistory, 'count')) ?> reports</span></div>
      <div class="chart-wrap">
        <svg class="incident-chart" viewBox="0 0 100 88" role="img" aria-label="Incident report counts over the last seven days">
          <line x1="5" y1="18" x2="95" y2="18"></line><line x1="5" y1="47" x2="95" y2="47"></line><line x1="5" y1="76" x2="95" y2="76"></line>
          <polyline points="<?= htmlspecialchars(implode(' ', $chartPoints)) ?>"></polyline>
          <?php foreach ($incidentHistory as $index => $point): ?>
            <?php $x = 8 + $index * 14; $y = 76 - ($point['count'] / $maxIncidentCount * 58); ?>
            <circle cx="<?= $x ?>" cy="<?= number_format($y, 2, '.', '') ?>" r="1.35" tabindex="0" data-chart-label="<?= htmlspecialchars($point['label']) ?>" data-chart-count="<?= (int) $point['count'] ?>"></circle>
          <?php endforeach; ?>
        </svg>
      </div>
      <div class="chart-days"><?php foreach ($incidentHistory as $point): ?><span><?= htmlspecialchars($point['label']) ?></span><?php endforeach; ?></div>
      <p class="chart-tooltip" aria-live="polite"></p>
    </section>
    <section class="overview-card role-chart-card">
      <div class="overview-card-heading"><div><h2>Accounts by role</h2><p><?= number_format($summary['user_count']) ?> total accounts</p></div></div>
      <div class="role-chart-content">
        <div class="role-donut" style="--role-chart: conic-gradient(<?= htmlspecialchars($roleGradient) ?>)"><strong><?= number_format($summary['user_count']) ?></strong><span>Accounts</span></div>
        <ul class="role-legend">
          <?php foreach ($roles as $role => $count): ?>
            <?php if ($count === 0) continue; ?>
            <li><span style="--role-color: <?= htmlspecialchars($roleColors[$role] ?? '#7653a6') ?>"></span><span><?= htmlspecialchars($roleLabels[$role] ?? $role) ?></span><strong><?= number_format($count) ?></strong></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>
  </div>
  <div class="overview-bottom-grid">
    <section class="overview-card resource-card">
      <div class="overview-card-heading"><div><h2>Directory summary</h2><p>Current records from the system database</p></div></div>
      <ul class="resource-list">
        <li><span class="resource-indicator"></span><span>Hospitals</span><strong><?= number_format($summary['hospital_count']) ?></strong></li>
        <li><span class="resource-indicator"></span><span>Barangays</span><strong><?= number_format($summary['barangay_count']) ?></strong></li>
        <li><span class="resource-indicator"></span><span>Ambulances available</span><strong><?= number_format($summary['available_ambulances']) ?></strong></li>
        <li><span class="resource-indicator"></span><span>Ready beds</span><strong><?= number_format($summary['ready_beds']) ?></strong></li>
      </ul>
    </section>
    <section class="overview-card recent-card">
      <div class="overview-card-heading"><div><h2>Recent incidents</h2><p>Latest reports received</p></div><a href="<?= htmlspecialchars($navigation[1]['url']) ?>">View users</a></div>
      <?php if ($recentIncidents): ?>
        <ul class="recent-list">
          <?php foreach ($recentIncidents as $incident): ?>
            <li><span class="incident-status status-<?= htmlspecialchars($incident['status']) ?>"></span><span class="recent-copy"><strong><?= htmlspecialchars($incident['incident_type'] ?: 'Incident report') ?></strong><small><?= htmlspecialchars($incident['location']) ?></small></span><time datetime="<?= htmlspecialchars(date(DATE_ATOM, strtotime($incident['reported_at']))) ?>"><?= htmlspecialchars(date('M j · H:i', strtotime($incident['reported_at']))) ?></time></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><p class="overview-empty">No incident reports have been recorded.</p><?php endif; ?>
    </section>
  </div>
</section>
