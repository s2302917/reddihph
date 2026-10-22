<section class="page-content roles-page">
  <header class="roles-heading"><div><h1>Roles and permissions</h1><p>Manage application roles and their cross-platform permissions.</p></div><span class="read-only-badge">DATABASE BACKED</span></header>
  <div class="roles-layout">
    <section class="roles-list-card">
      <div class="roles-list-heading"><h2>Roles</h2><span><?= count($roleCounts) ?></span></div>
      <ul class="roles-list">
        <?php foreach ($roleCounts as $role => $count): ?>
          <li><button type="button" class="role-row" data-role-select="<?= htmlspecialchars($role) ?>" aria-pressed="<?= $role === 'super_admin' ? 'true' : 'false' ?>"><span class="role-color role-color-<?= htmlspecialchars($role) ?>"></span><span><?= htmlspecialchars($roleLabels[$role] ?? $role) ?></span><strong><?= number_format($count) ?></strong></button></li>
        <?php endforeach; ?>
      </ul>
      <form class="admin-form" method="post">
        <h3>Create role</h3><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="create">
        <label>Role key<input name="role_key" required pattern="[a-z][a-z0-9_]{1,49}" maxlength="50" placeholder="dispatch_manager"></label>
        <label>Display name<input name="display_name" required maxlength="100"></label>
        <label>Description<input name="description" maxlength="255"></label>
        <button class="page-action-button" type="submit">Create role</button>
      </form>
    </section>
    <section class="permissions-card">
        <div class="permissions-card-heading"><div><h2>Roles and permissions</h2><p>Changes apply to application permission records.</p></div></div>
        <?php foreach ($roles as $role): ?>
          <details class="admin-form role-editor">
            <summary><?= htmlspecialchars($role['display_name']) ?> · <?= (int) $role['account_count'] ?> <?= (int) $role['account_count'] === 1 ? 'account' : 'accounts' ?></summary>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="role_key" value="<?= htmlspecialchars($role['role_key']) ?>">
              <label>Display name<input name="display_name" maxlength="100" required value="<?= htmlspecialchars($role['display_name']) ?>"></label>
              <label>Description<input name="description" maxlength="255" value="<?= htmlspecialchars($role['description'] ?? '') ?>"></label>
              <button class="page-action-button" type="submit">Save role</button>
            </form>
            <?php if (!(bool) $role['is_system']): ?>
              <form method="post" onsubmit="return confirm('Delete this role? Roles assigned to accounts cannot be deleted.');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="role_key" value="<?= htmlspecialchars($role['role_key']) ?>"><button class="page-action-button" type="submit">Delete role</button>
              </form>
            <?php endif; ?>
          </details>
        <?php endforeach; ?>
        <form method="post" class="permission-matrix-form">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="permission_matrix">
          <div class="permissions-scroll">
            <table class="permissions-table">
              <thead><tr><th>PERMISSION</th><?php foreach ($roleLabels as $role => $label): ?><th data-role-column="<?= htmlspecialchars($role) ?>" data-selected="<?= $role === 'super_admin' ? 'true' : 'false' ?>"><?= htmlspecialchars($label) ?></th><?php endforeach; ?></tr></thead>
              <tbody>
                <?php foreach ($permissions as $permission): ?>
                  <tr><th><span><?= htmlspecialchars($permission['display_name']) ?></span><small><?= htmlspecialchars($permission['platform_scope']) ?> · <?= htmlspecialchars($permission['permission_key']) ?></small></th>
                    <?php foreach ($roleLabels as $role => $label): $granted = !empty($grantedPermissions[$role][(int) $permission['id']]); ?>
                      <td data-role-column="<?= htmlspecialchars($role) ?>" data-selected="<?= $role === 'super_admin' ? 'true' : 'false' ?>">
                        <?php if ($role === 'super_admin'): ?><span class="permission-protected" aria-label="Super Admin permission always granted">✓</span>
                        <?php else: ?>
                          <label class="permission-toggle">
                            <input type="hidden" name="grants[<?= htmlspecialchars($role) ?>][<?= (int) $permission['id'] ?>]" value="0">
                            <input type="checkbox" name="grants[<?= htmlspecialchars($role) ?>][<?= (int) $permission['id'] ?>]" value="1" <?= $granted ? 'checked' : '' ?> aria-label="<?= htmlspecialchars($label . ': ' . $permission['display_name']) ?>">
                          </label>
                        <?php endif; ?>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="permissions-save-row"><span>Super Admin permissions are protected and always enabled.</span><button type="submit" class="page-action-button">Save permissions</button></div>
        </form>
    </section>
  </div>
</section>
