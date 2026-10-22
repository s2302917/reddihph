<section class="page-content facilities-page">
  <header class="facilities-heading"><div><h1>Hospital and barangays</h1><p><?= count($hospitals) ?> hospitals · <?= number_format($barangayCount) ?> barangays</p></div></header>
  <div class="facility-list">
    <?php foreach ($hospitals as $hospital): ?>
      <section class="facility-group" data-facility-group>
        <article class="hospital-summary">
          <span class="hospital-symbol" aria-hidden="true"></span>
          <div class="hospital-copy"><h2><?= htmlspecialchars($hospital['name']) ?></h2><p><?= htmlspecialchars($hospital['address'] ?: 'Address not provided') ?><?= $hospital['specialties'] !== '' ? ' · ' . htmlspecialchars($hospital['specialties']) : '' ?></p></div>
          <div class="hospital-beds"><strong><?= (int) $hospital['available_beds'] ?> / <?= (int) $hospital['total_beds'] ?></strong><span>beds available</span></div>
        </article>
        <form class="admin-form facility-editor" method="post">
          <h3>Edit hospital</h3><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="update_hospital"><input type="hidden" name="id" value="<?= (int) $hospital['id'] ?>">
          <label>Name<input name="name" maxlength="150" required value="<?= htmlspecialchars($hospital['name']) ?>"></label>
          <label>Address<input name="address" maxlength="255" value="<?= htmlspecialchars($hospital['address'] ?? '') ?>"></label>
          <label>Specialties<input name="specialties" maxlength="255" value="<?= htmlspecialchars($hospital['specialties']) ?>"></label>
          <label>Total beds<input name="total_beds" type="number" min="0" required value="<?= (int) $hospital['total_beds'] ?>"></label>
          <label>Available beds<input name="available_beds" type="number" min="0" max="<?= (int) $hospital['total_beds'] ?>" required value="<?= (int) $hospital['available_beds'] ?>"></label>
          <button class="page-action-button" type="submit">Save hospital</button>
        </form>
        <?php if ($archivedFacilities['hospitals'] || $archivedFacilities['barangays']): ?>
          <section class="content-card archived-facilities">
            <h2>Archived directory records</h2>
            <?php foreach ($archivedFacilities['hospitals'] as $archived): ?>
              <div class="archived-facility-row"><span><?= htmlspecialchars($archived['name']) ?> <small>Hospital</small></span><form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="restore_hospital"><input type="hidden" name="id" value="<?= (int) $archived['id'] ?>"><button class="page-action-button" type="submit">Restore</button></form></div>
            <?php endforeach; ?>
            <?php foreach ($archivedFacilities['barangays'] as $archived): ?>
              <div class="archived-facility-row"><span><?= htmlspecialchars($archived['name']) ?> <small>Barangay</small></span><form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="restore_barangay"><input type="hidden" name="id" value="<?= (int) $archived['id'] ?>"><button class="page-action-button" type="submit">Restore</button></form></div>
            <?php endforeach; ?>
          </section>
        <?php endif; ?>
        <form class="admin-form archive-form" method="post" onsubmit="return confirm('Archive this hospital? Hospitals with active barangays cannot be archived.');">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="archive_hospital"><input type="hidden" name="id" value="<?= (int) $hospital['id'] ?>"><button class="page-action-button" type="submit">Archive hospital</button>
        </form>
        <section class="barangay-panel">
          <div class="barangay-toolbar"><div><h3>Barangays</h3><p><?= count($hospital['barangays']) ?> assigned to this hospital</p></div><label class="barangay-search"><span aria-hidden="true"></span><input type="search" data-barangay-search placeholder="Filter barangays" aria-label="Filter barangays"></label></div>
          <?php if ($hospital['barangays']): ?>
            <div class="barangay-grid">
              <?php foreach ($hospital['barangays'] as $barangay): ?>
                <article class="barangay-card<?= (int) $barangay['active_dispatch_count'] > 0 ? ' has-dispatch' : '' ?><?= (int) $barangay['is_active'] !== 1 ? ' is-hidden' : '' ?>" data-barangay-card data-search="<?= htmlspecialchars(strtolower($barangay['name'])) ?>">
                  <div><strong><?= htmlspecialchars($barangay['name']) ?></strong><span><?= (int) $barangay['active_dispatch_count'] ?> active dispatch<?= (int) $barangay['active_dispatch_count'] === 1 ? '' : 'es' ?></span></div>
                  <form class="barangay-visibility-form" method="post">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="action" value="set_barangay_visibility">
                    <input type="hidden" name="id" value="<?= (int) $barangay['id'] ?>">
                    <input type="hidden" name="is_active" value="<?= (int) $barangay['is_active'] === 1 ? '0' : '1' ?>">
                    <button class="barangay-visibility-toggle<?= (int) $barangay['is_active'] === 1 ? ' is-active' : '' ?>" type="submit" role="switch" aria-checked="<?= (int) $barangay['is_active'] === 1 ? 'true' : 'false' ?>" aria-label="<?= (int) $barangay['is_active'] === 1 ? 'Hide ' : 'Show ' ?><?= htmlspecialchars($barangay['name']) ?> on the Reddiph admin site" title="<?= (int) $barangay['is_active'] === 1 ? 'Visible on the Reddiph admin site' : 'Hidden from the Reddiph admin site' ?>"></button>
                  </form>
                  <form class="admin-form compact-form" method="post">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="update_barangay"><input type="hidden" name="id" value="<?= (int) $barangay['id'] ?>">
                    <label>Name<input name="name" maxlength="100" required value="<?= htmlspecialchars($barangay['name']) ?>"></label>
                    <label>Slug<input name="slug" maxlength="120" value="<?= htmlspecialchars($barangay['slug'] ?? '') ?>"></label>
                    <label>Sort order<input name="sort_order" type="number" min="0" value="<?= (int) $barangay['sort_order'] ?>"></label>
                    <input type="hidden" name="hospital_id" value="<?= (int) $hospital['id'] ?>">
                    <button class="page-action-button" type="submit">Save barangay</button>
                  </form>
                  <form method="post" onsubmit="return confirm('Archive this barangay?');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="archive_barangay"><input type="hidden" name="id" value="<?= (int) $barangay['id'] ?>"><button class="page-action-button" type="submit">Archive</button></form>
                </article>
              <?php endforeach; ?>
            </div>
            <p class="barangay-no-results" hidden>No barangays match your filter.</p>
          <?php else: ?><p class="facility-empty">No barangays are assigned to this hospital.</p><?php endif; ?>
          <form class="admin-form facility-editor" method="post">
            <h3>Add barangay</h3><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="create_barangay"><input type="hidden" name="hospital_id" value="<?= (int) $hospital['id'] ?>">
            <label>Name<input name="name" maxlength="100" required></label><label>Slug (optional)<input name="slug" maxlength="120"></label><label>Sort order<input name="sort_order" type="number" min="0" value="0"></label><button class="page-action-button" type="submit">Add barangay</button>
          </form>
        </section>
      </section>
    <?php endforeach; ?>
  </div>
  <form class="admin-form facility-editor" method="post">
    <h2>Add hospital</h2><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="create_hospital">
    <label>Name<input name="name" maxlength="150" required></label><label>Address<input name="address" maxlength="255"></label><label>Specialties<input name="specialties" maxlength="255"></label><label>Total beds<input name="total_beds" type="number" min="0" value="0" required></label><label>Available beds<input name="available_beds" type="number" min="0" value="0" required></label><button class="page-action-button" type="submit">Add hospital</button>
  </form>
  <?php if (!$hospitals): ?><section class="facility-empty-state"><h2>No hospitals in the directory</h2><p>Hospital records will appear here when they are added to the database.</p></section><?php endif; ?>
</section>
