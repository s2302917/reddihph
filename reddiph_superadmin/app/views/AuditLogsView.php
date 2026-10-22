<section class="page-content audit-logs-page">
  <div class="page-heading"><div><p class="eyebrow">SECURITY</p><h1>Audit logs</h1><p>Review a traceable history of administrative activity.</p></div></div>
  <section class="content-card">
    <div class="table-toolbar"><div><h2>Activity history</h2><p>Up to 250 recent events matching your filters.</p></div><button class="page-action-button" type="button" data-recheck-audit>Refresh</button></div>
    <form class="admin-form audit-filters" method="get" action="<?= htmlspecialchars($navigation[5]['url']) ?>">
      <label>Search<input type="search" name="query" value="<?= htmlspecialchars($filters['query']) ?>" placeholder="Actor, entity, or details"></label>
      <label>Action<input name="action" value="<?= htmlspecialchars($filters['action']) ?>" placeholder="e.g. user.updated"></label>
      <label>Platform<select name="scope"><option value="">All platforms</option><?php foreach (['superadmin', 'website', 'mobile', 'system'] as $scope): ?><option value="<?= $scope ?>" <?= $filters['scope'] === $scope ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($scope)) ?></option><?php endforeach; ?></select></label>
      <label>From<input type="date" name="from" value="<?= htmlspecialchars($filters['from']) ?>"></label>
      <label>To<input type="date" name="to" value="<?= htmlspecialchars($filters['to']) ?>"></label>
      <button class="page-action-button" type="submit">Filter</button>
    </form>
    <div class="table-scroll"><table class="data-table audit-table"><thead><tr><th>When</th><th>Actor</th><th>Platform</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead><tbody>
      <?php foreach ($auditRows as $row): ?>
        <tr><td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($row['created_at']))) ?></td><td><?= htmlspecialchars($row['actor_name'] ?? 'System') ?></td><td><?= htmlspecialchars($row['platform_scope']) ?></td><td><?= htmlspecialchars($row['action']) ?></td><td><?= htmlspecialchars(trim(($row['entity_type'] ?? '') . ' ' . ($row['entity_id'] ?? ''))) ?></td><td><pre class="setting-value"><?= htmlspecialchars($row['details'] ?? '') ?></pre></td><td><?= htmlspecialchars($row['ip_address'] ?? '') ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php if (!$auditRows): ?><p class="empty-state">No audit events match these filters.</p><?php endif; ?>
  </section>
</section>
