<section class="page-content facilities-page">
  <?php if (!empty($pageError)): ?>
    <div class="page-data-error" role="alert">
      <h1>Hospital and barangay data unavailable</h1>
      <p><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  <?php else: ?>
    <?php
    $escape = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    $facilitiesUrl = \App\Controllers\DashboardController::url('/facilities');
    $typeLabel = $directoryType === 'hospitals' ? 'Hospitals' : 'Barangays';
    $pageStart = $totalRecords > 0 ? (($currentPage - 1) * $pageSize) + 1 : 0;
    $pageEnd = min($currentPage * $pageSize, $totalRecords);
    $buildFacilitiesUrl = static function (array $overrides = []) use (
        $facilitiesUrl,
        $directoryType,
        $directoryStatus,
        $searchQuery,
        $currentPage
    ): string {
        $parameters = [
            'type' => $directoryType,
            'status' => $directoryStatus,
            'q' => $searchQuery !== '' ? $searchQuery : null,
            'page' => $currentPage > 1 ? $currentPage : null,
        ];
        foreach ($overrides as $key => $value) {
            $parameters[$key] = $value;
        }
        $parameters = array_filter($parameters, static fn ($value): bool => $value !== null && $value !== '');
        return $facilitiesUrl . '?' . http_build_query($parameters);
    };
    $editId = $editRecord ? (int) $editRecord['id'] : 0;
    $createType = $editRecord ? $directoryType : 'hospitals';
    ?>

    <header class="facilities-heading">
      <div>
        <h1>Hospitals and barangays</h1>
        <p><?= number_format($hospitalCounts['total']) ?> hospitals · <?= number_format($barangayCounts['total']) ?> barangays</p>
      </div>
      <?php if ($directoryStatus === 'active' && $supportsWrites): ?>
        <button class="facility-add-button" type="button" data-toggle-facility-form
                aria-controls="facility-form-card" aria-expanded="<?= $editRecord ? 'true' : 'false' ?>">
          <?= $editRecord ? 'Close form' : 'Add record' ?>
        </button>
      <?php endif; ?>
    </header>

    <form class="facility-filters" method="get" action="<?= $escape($facilitiesUrl) ?>" data-facility-filter-form>
      <label>
        <span>Directory</span>
        <select name="type" data-facility-auto-submit>
          <option value="hospitals" <?= $directoryType === 'hospitals' ? 'selected' : '' ?>>Hospitals</option>
          <option value="barangays" <?= $directoryType === 'barangays' ? 'selected' : '' ?>>Barangays</option>
        </select>
      </label>
      <?php if ($supportsArchive): ?>
        <label>
          <span>Status</span>
          <select name="status" data-facility-auto-submit>
            <option value="active" <?= $directoryStatus === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="archived" <?= $directoryStatus === 'archived' ? 'selected' : '' ?>>Archived</option>
          </select>
        </label>
      <?php endif; ?>
      <label class="facility-search">
        <span>Search <?= strtolower($typeLabel) ?></span>
        <input type="search" name="q" value="<?= $escape($searchQuery) ?>" maxlength="100"
               placeholder="<?= $directoryType === 'hospitals' ? 'Name, address, or specialty' : 'Name, slug, or hospital' ?>"
               data-facility-search>
      </label>
      <button class="facility-filter-submit" type="submit">Search</button>
    </form>

    <?php if (!$supportsWrites): ?>
      <div class="facility-schema-notice" role="status">
        Directory changes require the audit log table. After taking a database backup, review and apply
        <code>reddiph_superadmin/config/migrations/20261022_portal_pages.sql</code>.
      </div>
    <?php elseif (!$supportsArchive): ?>
      <div class="facility-schema-notice" role="status">
        Archive and visibility controls require <code>is_active</code> and <code>archived_at</code>.
        After taking a database backup, review and apply
        <code>reddiph_superadmin/config/migrations/20261022_portal_pages.sql</code>.
      </div>
    <?php endif; ?>

    <?php if ($directoryStatus === 'active' && $supportsWrites): ?>
      <section class="facility-form-card" id="facility-form-card" <?= $editRecord ? '' : 'hidden' ?>
               aria-labelledby="facility-form-title">
        <div class="facility-form-heading">
          <div>
            <h2 id="facility-form-title"><?= $editRecord ? 'Edit directory record' : 'Add a directory record' ?></h2>
            <p><?= $editRecord ? 'Update the selected record.' : 'Choose which kind of record to add.' ?></p>
          </div>
          <?php if (!$editRecord): ?>
            <label class="facility-create-type">
              <span>Record type</span>
              <select data-facility-create-type>
                <option value="hospitals">Hospital</option>
                <option value="barangays">Barangay</option>
              </select>
            </label>
          <?php endif; ?>
          <button class="facility-form-close" type="button" data-close-facility-form aria-label="Close form">×</button>
        </div>

        <?php if ($directoryType === 'hospitals' || !$editRecord): ?>
          <form class="admin-form facility-editor facility-record-form"
                method="post" action="<?= $escape($buildFacilitiesUrl(['type' => 'hospitals', 'status' => 'active', 'page' => null, 'edit' => null])) ?>"
                data-facility-record-form="hospitals"
                <?= !$editRecord && $createType !== 'hospitals' ? 'hidden' : '' ?>>
            <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
            <input type="hidden" name="action" value="<?= $editRecord ? 'update_hospital' : 'create_hospital' ?>">
            <?php if ($editRecord): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
            <label>Name<input name="name" maxlength="150" required value="<?= $escape($editRecord['name'] ?? '') ?>"></label>
            <label>Address<input name="address" maxlength="255" value="<?= $escape($editRecord['address'] ?? '') ?>"></label>
            <label>Specialties<input name="specialties" maxlength="255" value="<?= $escape($editRecord['specialties'] ?? '') ?>"></label>
            <label>Total beds<input name="total_beds" type="number" min="0" required value="<?= (int) ($editRecord['total_beds'] ?? 0) ?>"></label>
            <label>Available beds<input name="available_beds" type="number" min="0" required value="<?= (int) ($editRecord['available_beds'] ?? 0) ?>"></label>
            <button class="page-action-button facility-submit-button" type="submit"><?= $editRecord ? 'Save hospital' : 'Add hospital' ?></button>
          </form>
        <?php endif; ?>

        <?php if ($directoryType === 'barangays' || !$editRecord): ?>
          <form class="admin-form facility-editor facility-record-form"
                method="post" action="<?= $escape($buildFacilitiesUrl(['type' => 'barangays', 'status' => 'active', 'page' => null, 'edit' => null])) ?>"
                data-facility-record-form="barangays"
                <?= !$editRecord && $createType !== 'barangays' ? 'hidden' : '' ?>>
            <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
            <input type="hidden" name="action" value="<?= $editRecord ? 'update_barangay' : 'create_barangay' ?>">
            <?php if ($editRecord): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
            <label>Name<input name="name" maxlength="100" required value="<?= $escape($editRecord['name'] ?? '') ?>"></label>
            <label>Slug (optional)<input name="slug" maxlength="120" value="<?= $escape($editRecord['slug'] ?? '') ?>"></label>
            <label>Hospital
              <select name="hospital_id" required>
                <option value="">Choose a hospital</option>
                <?php foreach ($hospitalsForForms as $hospital): ?>
                  <option value="<?= (int) $hospital['id'] ?>" <?= isset($editRecord['hospital_id']) && (int) $editRecord['hospital_id'] === (int) $hospital['id'] ? 'selected' : '' ?>>
                    <?= $escape($hospital['name']) ?>
                  </option>
                <?php endforeach; ?>
                <?php if ($editRecord && !in_array((int) $editRecord['hospital_id'], array_map('intval', array_column($hospitalsForForms, 'id')), true)): ?>
                  <option value="<?= (int) $editRecord['hospital_id'] ?>" selected>Currently assigned hospital</option>
                <?php endif; ?>
              </select>
            </label>
            <label>Sort order<input name="sort_order" type="number" min="0" required value="<?= (int) ($editRecord['sort_order'] ?? 0) ?>"></label>
            <button class="page-action-button facility-submit-button" type="submit"
                    <?= !$hospitalsForForms && !$editRecord ? 'disabled' : '' ?>>
              <?= $editRecord ? 'Save barangay' : 'Add barangay' ?>
            </button>
            <?php if (!$hospitalsForForms && !$editRecord): ?>
              <p class="facility-form-note">Add an active hospital before adding a barangay.</p>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <section class="facility-table-card" aria-label="<?= $escape($directoryStatus . ' ' . strtolower($typeLabel)) ?>">
      <div class="table-scroll">
        <table class="data-table facility-table <?= $directoryType === 'hospitals' ? 'is-hospital-table' : 'is-barangay-table' ?>">
          <thead>
            <?php if ($directoryType === 'hospitals'): ?>
              <tr><th>Hospital</th><th>Specialties</th><th>Beds</th><th>Status</th><th>Added</th><th><span class="visually-hidden">Actions</span></th></tr>
            <?php else: ?>
              <tr><th>Barangay</th><th>Hospital</th><th>Slug</th><th>Dispatches</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr>
            <?php endif; ?>
          </thead>
          <tbody>
            <?php foreach ($records as $record): ?>
              <?php $archived = $record['archived_at'] !== null; ?>
              <tr>
                <?php if ($directoryType === 'hospitals'): ?>
                  <td><strong class="facility-record-name"><?= $escape($record['name']) ?></strong><small class="facility-record-subtitle"><?= $escape($record['address'] ?: 'Address not provided') ?></small></td>
                  <td><?= $escape($record['specialties'] !== '' ? $record['specialties'] : 'Not specified') ?></td>
                  <td><strong><?= (int) $record['available_beds'] ?></strong> / <?= (int) $record['total_beds'] ?></td>
                  <td><span class="facility-status<?= $archived ? ' is-archived' : ((int) $record['is_active'] === 1 ? ' is-active' : ' is-hidden') ?>"><?= $archived ? 'Archived' : ((int) $record['is_active'] === 1 ? 'Active' : 'Hidden') ?></span></td>
                  <td><?= $escape(date('M j, Y', strtotime($record['created_at']))) ?></td>
                <?php else: ?>
                  <td><strong class="facility-record-name"><?= $escape($record['name']) ?></strong><small class="facility-record-subtitle">Sort order <?= (int) $record['sort_order'] ?></small></td>
                  <td><?= $escape($record['hospital_name'] ?: 'Not assigned') ?></td>
                  <td><?= $escape($record['slug'] ?: 'No slug') ?></td>
                  <td><?= number_format((int) $record['active_dispatch_count']) ?></td>
                  <td><span class="facility-status<?= $archived ? ' is-archived' : ((int) $record['is_active'] === 1 ? ' is-active' : ' is-hidden') ?>"><?= $archived ? 'Archived' : ((int) $record['is_active'] === 1 ? 'Visible' : 'Hidden') ?></span></td>
                <?php endif; ?>
                <td class="facility-actions">
                  <div class="facility-action-group">
                  <?php if ($directoryStatus === 'archived'): ?>
                    <?php if ($supportsWrites): ?>
                    <form method="post" onsubmit="return confirm('Restore this <?= $directoryType === 'hospitals' ? 'hospital' : 'barangay' ?>?');">
                      <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
                      <input type="hidden" name="action" value="restore_<?= $directoryType === 'hospitals' ? 'hospital' : 'barangay' ?>">
                      <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                      <button class="facility-action-button is-primary" type="submit">Restore</button>
                    </form>
                    <?php endif; ?>
                  <?php else: ?>
                    <?php if ($supportsWrites): ?>
                      <a class="facility-action-button" href="<?= $escape($buildFacilitiesUrl(['edit' => (int) $record['id']])) ?>">Edit</a>
                    <?php endif; ?>
                    <?php if ($directoryType === 'barangays' && $supportsArchive && $supportsWrites): ?>
                      <form method="post" class="facility-inline-action">
                        <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
                        <input type="hidden" name="action" value="set_barangay_visibility">
                        <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                        <input type="hidden" name="is_active" value="<?= (int) $record['is_active'] === 1 ? '0' : '1' ?>">
                        <button class="facility-action-button" type="submit"><?= (int) $record['is_active'] === 1 ? 'Hide' : 'Show' ?></button>
                      </form>
                    <?php endif; ?>
                    <?php if ($supportsArchive && $supportsWrites): ?>
                      <form method="post" class="facility-inline-action"
                            onsubmit="return confirm('Archive this <?= $directoryType === 'hospitals' ? 'hospital? Reassign or archive its barangays first.' : 'barangay?' ?> Existing related history will be preserved.');">
                        <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
                        <input type="hidden" name="action" value="archive_<?= $directoryType === 'hospitals' ? 'hospital' : 'barangay' ?>">
                        <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                        <button class="facility-action-button is-danger" type="submit">Archive</button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?>
              <tr><td colspan="6" class="facility-empty-row">
                <?= $searchQuery !== '' ? 'No records match your search.' : ($directoryStatus === 'archived' ? 'There are no archived records.' : ($supportsWrites ? 'No records found. Add one using Add record.' : 'No directory records found. Apply the support migration to enable changes.')) ?>
              </td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <footer class="facility-pagination">
        <p>Showing <strong><?= number_format($pageStart) ?>–<?= number_format($pageEnd) ?></strong> of <strong><?= number_format($totalRecords) ?></strong> <?= strtolower($typeLabel) ?></p>
        <nav aria-label="<?= $escape($typeLabel) ?> pages">
          <?php if ($currentPage > 1): ?>
            <a href="<?= $escape($buildFacilitiesUrl(['page' => $currentPage - 1, 'edit' => null])) ?>" rel="prev">Previous</a>
          <?php else: ?>
            <span aria-disabled="true">Previous</span>
          <?php endif; ?>
          <span>Page <?= number_format($currentPage) ?> of <?= number_format($totalPages) ?></span>
          <?php if ($currentPage < $totalPages): ?>
            <a href="<?= $escape($buildFacilitiesUrl(['page' => $currentPage + 1, 'edit' => null])) ?>" rel="next">Next</a>
          <?php else: ?>
            <span aria-disabled="true">Next</span>
          <?php endif; ?>
        </nav>
      </footer>
    </section>
  <?php endif; ?>
</section>
