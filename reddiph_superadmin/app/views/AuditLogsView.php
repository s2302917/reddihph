<section class="page-content audit-logs-page">
  <?php
  $formatAuditDetails = static function (string $action, ?string $details): string {
      $descriptions = [
          'user.created' => 'Added a user account',
          'user.updated' => 'Updated user details',
          'user.account_archived' => 'Archived a user account',
          'user.account_restored' => 'Restored a user account',
          'user.login_disabled' => 'Disabled user login',
          'user.login_enabled' => 'Enabled user login',
          'user.password_reset' => 'Issued a temporary password',
          'facility.hospital_created' => 'Added a hospital',
          'facility.hospital_updated' => 'Updated hospital details',
          'facility.archive_hospital' => 'Archived a hospital',
          'facility.archive_barangay' => 'Archived a barangay',
          'facility.restore_hospital' => 'Restored a hospital',
          'facility.restore_barangay' => 'Restored a barangay',
          'facility.barangay_created' => 'Added a barangay',
          'facility.barangay_updated' => 'Updated barangay details',
          'facility.barangay_shown' => 'Made a barangay visible on the admin site',
          'facility.barangay_hidden' => 'Hid a barangay from the admin site',
          'nurse.prep_task_updated' => 'Updated a nurse preparation task',
          'nurse.clinical_note_saved' => 'Saved a nurse clinical note',
          'nurse.bed_assigned' => 'Assigned a bed to an incident',
          'nurse.arrival_confirmed' => 'Confirmed a nurse arrival',
          'nurse.bed_updated' => 'Updated a hospital bed status',
          'nurse.handoff_recorded' => 'Recorded a patient handoff',
          'nurse.shift_swap_requested' => 'Requested a nurse shift swap',
          'nurse.settings_updated' => 'Updated nurse settings',
          'system.migration_completed' => 'Recorded a database migration',
      ];
      $description = $descriptions[$action] ?? 'Recorded a system activity';
      $details = trim((string) $details);
      if ($details === '') {
          return $description . '.';
      }

      $decoded = json_decode($details, true);
      if (json_last_error() !== JSON_ERROR_NONE) {
          return $description . '.';
      }
      if (!is_array($decoded)) {
          return $description . '.';
      }

      $labels = [
          'display_name' => 'Account',
          'name' => 'Name',
          'role' => 'Role',
          'hospital_name' => 'Hospital',
          'barangay_name' => 'Barangay',
          'reason' => 'Reason',
          'changed_fields' => 'Updated fields',
      ];
      $parts = [];
      foreach ($decoded as $key => $value) {
          if (is_array($value)) {
              if (!array_is_list($value) || array_filter($value, 'is_scalar') !== $value) {
                  continue;
              }
              $value = implode(', ', $value);
          }
          if (!is_scalar($value) || (string) $value === '') {
              continue;
          }
          if (!isset($labels[$key])) {
              continue;
          }
          if ($key === 'role') {
              $value = ucwords(str_replace('_', ' ', (string) $value));
          }
          $label = $labels[$key];
          if ($key === 'name' && str_contains($action, 'hospital')) {
              $label = 'Hospital';
          } elseif ($key === 'name' && str_contains($action, 'barangay')) {
              $label = 'Barangay';
          }
          $parts[] = $label . ': ' . (string) $value;
      }
      return $description . ($parts ? ' — ' . implode(' · ', $parts) . '.' : '.');
  };
  ?>
  <?php
  $auditLogsUrl = \App\Controllers\DashboardController::url('/audit-logs');
  $buildAuditLogsUrl = static function (int $page) use ($auditLogsUrl, $filters): string {
      $parameters = array_filter($filters, static fn (string $value): bool => $value !== '');
      if ($page > 1) {
          $parameters['page'] = $page;
      }
      $query = http_build_query($parameters);
      return $auditLogsUrl . ($query !== '' ? '?' . $query : '');
  };
  $pageStart = $totalLogs > 0 ? (($currentPage - 1) * $pageSize) + 1 : 0;
  $pageEnd = min($currentPage * $pageSize, $totalLogs);
  ?>
  <div class="page-heading"><div><p class="eyebrow">SECURITY</p><h1>Audit logs</h1><p>Review a traceable history of administrative activity.</p></div></div>
  <section class="content-card">
    <?php if (!empty($auditError)): ?>
      <div class="page-data-error" role="alert"><h2>Audit logs unavailable</h2><p><?= htmlspecialchars($auditError) ?></p></div>
    <?php else: ?>
    <div class="table-toolbar"><div><h2>Activity history</h2><p>Newest activity is shown first.</p></div><button class="page-action-button" type="button" data-recheck-audit>Refresh</button></div>
    <form class="admin-form audit-filters" method="get" action="<?= htmlspecialchars(\App\Controllers\DashboardController::url('/audit-logs')) ?>">
      <label>Search<input type="search" name="query" value="<?= htmlspecialchars($filters['query']) ?>" placeholder="Actor, entity, or details"></label>
      <label>Action<input name="action" value="<?= htmlspecialchars($filters['action']) ?>" placeholder="e.g. user.updated"></label>
      <label>Platform<select name="platform" data-audit-platform-filter><option value="">All platforms</option><?php foreach (['superadmin', 'website', 'mobile', 'system'] as $platform): ?><option value="<?= $platform ?>" <?= $filters['platform'] === $platform ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($platform)) ?></option><?php endforeach; ?></select></label>
      <label>From<input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>"></label>
      <label>To<input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>"></label>
      <button class="page-action-button" type="submit">Filter</button>
    </form>
    <div class="table-scroll"><table class="data-table audit-table"><thead><tr><th>When</th><th>Actor</th><th>Platform</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead><tbody>
      <?php foreach ($auditRows as $row): ?>
        <?php $details = $formatAuditDetails($row['action_code'] ?? '', $row['details'] ?? null); ?>
        <tr><td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($row['created_at']))) ?></td><td><?= htmlspecialchars($row['actor_name'] ?? 'System') ?></td><td><?= htmlspecialchars(ucfirst($row['platform'])) ?></td><td><?= htmlspecialchars($row['action']) ?></td><td><?= htmlspecialchars(trim(($row['entity_type'] ?? '') . ' ' . ($row['entity_id'] ?? ''))) ?></td><td class="audit-details"><?= htmlspecialchars($details, ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['ip_address'] ?? '') ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php if (!$auditRows): ?><p class="empty-state">No audit events match these filters.</p><?php endif; ?>
    <footer class="audit-pagination">
      <p>Showing <strong><?= number_format($pageStart) ?>–<?= number_format($pageEnd) ?></strong> of <strong><?= number_format($totalLogs) ?></strong> events</p>
      <nav aria-label="Audit log pages">
        <?php if ($currentPage > 1): ?>
          <a href="<?= htmlspecialchars($buildAuditLogsUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') ?>" rel="prev">Previous</a>
        <?php else: ?>
          <span aria-disabled="true">Previous</span>
        <?php endif; ?>
        <span>Page <?= number_format($currentPage) ?> of <?= number_format($totalPages) ?></span>
        <?php if ($currentPage < $totalPages): ?>
          <a href="<?= htmlspecialchars($buildAuditLogsUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') ?>" rel="next">Next</a>
        <?php else: ?>
          <span aria-disabled="true">Next</span>
        <?php endif; ?>
      </nav>
    </footer>
    <?php endif; ?>
  </section>
</section>
