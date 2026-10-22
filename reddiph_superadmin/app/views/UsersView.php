<section class="page-content users-page">
  <header class="users-heading">
    <div><h1>Users</h1><p><?= number_format(array_sum($roleCounts)) ?> accounts · <?= number_format($roleCounts['volunteer'] ?? 0) ?> volunteers</p></div>
    <span class="profile-initial"><?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1))) ?></span>
  </header>
  <div class="users-layout">
    <div class="users-main">
      <nav class="user-filters" aria-label="Filter users by role">
        <a class="<?= $selectedRole === '' ? 'selected' : '' ?>" href="<?= htmlspecialchars($navigation[1]['url']) ?>">All <span><?= array_sum($roleCounts) ?></span></a>
        <?php foreach ($roleLabels as $role => $label): ?>
          <a class="<?= $selectedRole === $role ? 'selected' : '' ?>" href="<?= htmlspecialchars($navigation[1]['url'] . '?role=' . rawurlencode($role)) ?>"><?= htmlspecialchars($label) ?> <span><?= $roleCounts[$role] ?></span></a>
        <?php endforeach; ?>
      </nav>
      <label class="user-search"><span aria-hidden="true"></span><input type="search" data-user-search placeholder="Search name, email, role, or hospital" aria-label="Search users"></label>
      <section class="users-table-card">
        <div class="table-scroll">
          <table class="data-table users-table">
            <thead><tr><th>Name</th><th>Role</th><th>Login</th><th>Location</th><th>Last active</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($users as $account): ?>
                <?php $isSelected = $selectedUser && (int) $selectedUser['id'] === (int) $account['id']; ?>
                <tr data-user-row data-search="<?= htmlspecialchars(strtolower($account['name'] . ' ' . $account['email'] . ' ' . $account['role'] . ' ' . $account['hospital_name'])) ?>">
                  <td><a class="user-row-link<?= $isSelected ? ' selected' : '' ?>" href="<?= htmlspecialchars($navigation[1]['url'] . '?account=' . (int) $account['id'] . ($selectedRole !== '' ? '&role=' . rawurlencode($selectedRole) : '')) ?>">
                    <span class="account-avatar"><?= htmlspecialchars(strtoupper(substr($account['name'], 0, 1))) ?></span><span><strong><?= htmlspecialchars($account['name']) ?></strong><small><?= htmlspecialchars($account['email']) ?></small></span>
                  </a></td>
                  <td><span class="role-pill role-<?= htmlspecialchars($account['role']) ?>"><?= htmlspecialchars($roleLabels[$account['role']] ?? $account['role']) ?></span></td>
                  <td><span class="login-status<?= $account['is_login_disabled'] ? ' login-status-disabled' : '' ?>"><?= $account['is_login_disabled'] ? 'Disabled' : 'Enabled' ?></span></td>
                  <td><?= htmlspecialchars($account['hospital_name']) ?></td>
                  <td><?= $account['last_login_at'] ? htmlspecialchars(date('M j · H:i', strtotime($account['last_login_at']))) : 'Never' ?></td>
                  <td><a class="row-more" href="<?= htmlspecialchars($navigation[1]['url'] . '?account=' . (int) $account['id'] . ($selectedRole !== '' ? '&role=' . rawurlencode($selectedRole) : '')) ?>" aria-label="View <?= htmlspecialchars($account['name']) ?>">···</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <p class="users-no-results" hidden>No users match your search.</p>
        </div>
      </section>
    </div>
    <aside class="user-profile-card" aria-label="User account management">
      <?php if ($selectedUser): ?>
        <span class="profile-avatar"><?= htmlspecialchars(strtoupper(substr($selectedUser['name'], 0, 1))) ?></span>
        <h2><?= htmlspecialchars($selectedUser['name']) ?></h2>
        <p class="profile-role"><?= htmlspecialchars($roleLabels[$selectedUser['role']] ?? $selectedUser['role']) ?></p>
        <a class="profile-email" href="mailto:<?= htmlspecialchars($selectedUser['email']) ?>"><?= htmlspecialchars($selectedUser['email']) ?></a>
        <dl class="profile-details">
          <div><dt>Hospital</dt><dd><?= htmlspecialchars($selectedUser['hospital_name']) ?></dd></div>
          <div><dt>Login</dt><dd><span class="login-status<?= $selectedUser['is_login_disabled'] ? ' login-status-disabled' : '' ?>"><?= $selectedUser['is_login_disabled'] ? 'Disabled' : 'Enabled' ?></span></dd></div>
          <div><dt>Phone</dt><dd><?= htmlspecialchars($selectedUser['phone_number'] ?: 'Not provided') ?></dd></div>
          <div><dt>Last active</dt><dd><?= $selectedUser['last_login_at'] ? htmlspecialchars(date('M j, Y · H:i', strtotime($selectedUser['last_login_at']))) : 'Never' ?></dd></div>
          <div><dt>Account created</dt><dd><?= htmlspecialchars(date('M j, Y', strtotime($selectedUser['created_at']))) ?></dd></div>
        </dl>
        <form class="admin-form user-editor" method="post">
          <h3>Edit account</h3>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
          <label>Name<input name="name" maxlength="150" required value="<?= htmlspecialchars($selectedUser['name']) ?>"></label>
          <label>Email<input name="email" type="email" maxlength="150" required value="<?= htmlspecialchars($selectedUser['email']) ?>"></label>
          <label>Phone<input name="phone" maxlength="32" value="<?= htmlspecialchars($selectedUser['phone_number'] ?? '') ?>"></label>
          <label>Role<select name="role" required><?php foreach ($roleLabels as $role => $label): ?><option value="<?= htmlspecialchars($role) ?>" <?= $selectedUser['role'] === $role ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></label>
          <label>Hospital<select name="hospital_id"><option value="">Not assigned</option><?php foreach ($hospitals as $id => $hospitalName): ?><option value="<?= (int) $id ?>" <?= (int) $selectedUser['hospital_id'] === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($hospitalName) ?></option><?php endforeach; ?></select></label>
          <label>Barangay<select name="barangay_id"><option value="">Not assigned</option><?php foreach ($barangays as $id => $barangayName): ?><option value="<?= (int) $id ?>" <?= (int) $selectedUser['barangay_id'] === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($barangayName) ?></option><?php endforeach; ?></select></label>
          <button type="submit" class="page-action-button">Save account</button>
        </form>
        <div class="account-actions">
          <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>"><input type="hidden" name="action" value="reset_password"><button type="submit" class="page-action-button">Issue temporary password</button></form>
          <?php if ($selectedUser['role'] !== 'super_admin'): ?>
            <form method="post" onsubmit="return <?= $selectedUser['is_login_disabled'] ? 'true' : "confirm('Disable this user’s login? They will be signed out on their next request.')" ?>;">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
              <input type="hidden" name="action" value="<?= $selectedUser['is_login_disabled'] ? 'enable_login' : 'disable_login' ?>">
              <button type="submit" class="page-action-button"><?= $selectedUser['is_login_disabled'] ? 'Enable login' : 'Disable login' ?></button>
            </form>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <p class="profile-empty">No user accounts found for this filter.</p>
      <?php endif; ?>
      <form class="admin-form user-editor" method="post">
        <h3>Create account</h3>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="create">
        <label>Name<input name="name" maxlength="150" required></label>
        <label>Email<input name="email" type="email" maxlength="150" required></label>
        <label>Phone<input name="phone" maxlength="32"></label>
        <label>Role<select name="role" required><?php foreach ($roleLabels as $role => $label): ?><option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?></select></label>
        <label>Hospital<select name="hospital_id"><option value="">Not assigned</option><?php foreach ($hospitals as $id => $hospitalName): ?><option value="<?= (int) $id ?>"><?= htmlspecialchars($hospitalName) ?></option><?php endforeach; ?></select></label>
        <label>Barangay<select name="barangay_id"><option value="">Not assigned</option><?php foreach ($barangays as $id => $barangayName): ?><option value="<?= (int) $id ?>"><?= htmlspecialchars($barangayName) ?></option><?php endforeach; ?></select></label>
        <button type="submit" class="page-action-button">Create account</button>
      </form>
    </aside>
  </div>
</section>
