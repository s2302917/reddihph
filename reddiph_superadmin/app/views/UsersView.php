<section class="page-content users-page">
  <?php if (!empty($pageError)): ?>
    <div class="page-data-error" role="alert">
      <h1>Users unavailable</h1>
      <p><?= htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  <?php else: ?>
    <?php
    $usersUrl = \App\Controllers\DashboardController::url('/users');
    $buildUsersUrl = static function (array $overrides = []) use (
        $usersUrl,
        $selectedRole,
        $selectedStatus,
        $searchQuery,
        $currentPage
    ): string {
        $parameters = [
            'status' => $selectedStatus !== 'active' ? $selectedStatus : null,
            'role' => $selectedRole !== '' ? $selectedRole : null,
            'q' => $searchQuery !== '' ? $searchQuery : null,
            'page' => $currentPage > 1 ? $currentPage : null,
        ];
        foreach ($overrides as $key => $value) {
            $parameters[$key] = $value;
        }
        $parameters = array_filter($parameters, static fn ($value): bool => $value !== null && $value !== '');
        $query = http_build_query($parameters);
        return $usersUrl . ($query !== '' ? '?' . $query : '');
    };
    $pageStart = $totalUsers > 0 ? (($currentPage - 1) * $pageSize) + 1 : 0;
    $pageEnd = min($currentPage * $pageSize, $totalUsers);
    ?>
    <header class="users-heading">
      <div>
        <h1>Users</h1>
        <p><?= number_format(array_sum($roleCounts)) ?> <?= $selectedStatus === 'archived' ? 'archived accounts' : 'accounts' ?></p>
      </div>
    </header>

    <div class="users-layout">
      <div class="users-main">
        <div class="users-toolbar">
          <nav class="user-filters" aria-label="Filter users by role">
            <a class="<?= $selectedRole === '' ? 'selected' : '' ?>"
               href="<?= htmlspecialchars($buildUsersUrl(['role' => null, 'page' => null, 'account' => null]), ENT_QUOTES, 'UTF-8') ?>">
              All <span><?= array_sum($roleCounts) ?></span>
            </a>
            <?php foreach ($roleLabels as $role => $label): ?>
              <?php if (isset($roleCounts[$role])): ?>
                <a class="<?= $selectedRole === $role ? 'selected' : '' ?>"
                   href="<?= htmlspecialchars($buildUsersUrl(['role' => $role, 'page' => null, 'account' => null]), ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                  <span><?= number_format($roleCounts[$role]) ?></span>
                </a>
              <?php endif; ?>
            <?php endforeach; ?>
          </nav>
          <?php if ($selectedStatus !== 'archived'): ?>
            <button class="add-account-button" type="button" data-toggle-create-account
                    aria-controls="create-account-card" aria-expanded="false">
              <span aria-hidden="true">+</span> Add account
            </button>
          <?php endif; ?>
        </div>

        <form class="users-search-form" method="get" action="<?= htmlspecialchars($usersUrl, ENT_QUOTES, 'UTF-8') ?>" data-user-search-form>
          <?php if ($selectedRole !== ''): ?>
            <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole, ENT_QUOTES, 'UTF-8') ?>">
          <?php endif; ?>
          <input type="hidden" name="status" value="<?= htmlspecialchars($selectedStatus, ENT_QUOTES, 'UTF-8') ?>" data-user-status>
          <label class="user-search">
            <span aria-hidden="true"></span>
            <input type="search" name="q" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" data-user-search
                   placeholder="Search name, email, role, or hospital" aria-label="Search users">
          </label>
          <fieldset class="user-status-switch">
            <legend class="visually-hidden">Show active or archived accounts</legend>
            <button type="button" data-user-status-button="active" aria-pressed="<?= $selectedStatus === 'active' ? 'true' : 'false' ?>">
              Active <span><?= number_format($activeAccountCount) ?></span>
            </button>
            <button type="button" data-user-status-button="archived" aria-pressed="<?= $selectedStatus === 'archived' ? 'true' : 'false' ?>">
              Archived <span><?= number_format($archivedAccountCount) ?></span>
            </button>
          </fieldset>
        </form>

        <?php if ($selectedStatus !== 'archived'): ?>
          <section class="create-account-card" id="create-account-card" hidden aria-labelledby="create-account-title">
          <div class="create-account-heading">
            <div>
              <h2 id="create-account-title">Add account</h2>
              <p>Create a new account. A temporary password will be generated.</p>
            </div>
            <button type="button" class="create-account-close" data-close-create-account aria-label="Close add account form">×</button>
          </div>
          <form class="admin-form user-editor create-account-form" method="post"
                data-barangays-url="<?= htmlspecialchars($usersUrl, ENT_QUOTES, 'UTF-8') ?>">
            <h3 class="visually-hidden">New account details</h3>
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="create">
            <label>Name<input name="name" maxlength="150" required></label>
            <label>Email<input name="email" type="email" maxlength="150" required></label>
            <label>Phone<input name="phone" maxlength="32"></label>
            <label>Role<select name="role" required><?php foreach ($roleLabels as $role => $label): ?><option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
            <label class="account-location-field">Hospital<select name="hospital_id" data-account-hospital><option value="">Not assigned</option><?php foreach ($hospitals as $id => $hospitalName): ?><option value="<?= (int) $id ?>"><?= htmlspecialchars($hospitalName, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
            <label class="account-location-field">Barangay
              <select name="barangay_id" data-account-barangay disabled>
                <option value="">Select a hospital first</option>
              </select>
              <small class="barangay-select-status" data-account-barangay-status role="status" aria-live="polite">Choose a hospital to load its barangays.</small>
            </label>
            <button type="submit" class="page-action-button">Create account</button>
          </form>
          </section>
        <?php endif; ?>

        <section class="users-table-card" aria-label="<?= $selectedStatus === 'archived' ? 'Archived accounts' : 'Active accounts' ?>">
          <div class="table-scroll">
            <table class="data-table users-table">
              <thead>
                <tr><th>Name</th><th>Role</th><th>Login</th><th>Location</th><th>Last active</th><th><span class="visually-hidden">Actions</span></th></tr>
              </thead>
              <tbody>
                <?php foreach ($users as $account): ?>
                  <?php
                  $isSelected = $selectedUser && (int) $selectedUser['id'] === (int) $account['id'];
                  $accountUrl = $buildUsersUrl([
                      'account' => (int) $account['id'],
                  ]);
                  $accountIsArchived = $account['account_archived_at'] !== null;
                  ?>
                  <tr>
                    <td>
                      <a class="user-row-link<?= $isSelected ? ' selected' : '' ?>"
                         href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="account-avatar"><?= htmlspecialchars(strtoupper(substr($account['name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
                        <span>
                          <strong><?= htmlspecialchars($account['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                          <small><?= htmlspecialchars($account['email'], ENT_QUOTES, 'UTF-8') ?></small>
                        </span>
                      </a>
                    </td>
                    <td><span class="role-pill role-<?= htmlspecialchars($account['role'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($roleLabels[$account['role']] ?? $account['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td>
                      <?php if ($accountIsArchived): ?>
                        <span class="login-status login-status-archived">Archived</span>
                      <?php else: ?>
                        <span class="login-status<?= $account['is_login_disabled'] ? ' login-status-disabled' : '' ?>"><?= $account['is_login_disabled'] ? 'Disabled' : 'Enabled' ?></span>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($account['hospital_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $account['last_login_at'] ? htmlspecialchars(date('M j · H:i', strtotime($account['last_login_at'])), ENT_QUOTES, 'UTF-8') : 'Never' ?></td>
                    <td class="user-actions-cell">
                      <button class="row-more" type="button"
                              aria-label="Actions for <?= htmlspecialchars($account['name'], ENT_QUOTES, 'UTF-8') ?>"
                              popovertarget="user-actions-<?= (int) $account['id'] ?>"
                              data-user-menu-trigger>
                        <span aria-hidden="true">⋯</span>
                      </button>
                      <div class="user-actions-menu" id="user-actions-<?= (int) $account['id'] ?>"
                           popover="auto" data-user-actions-popover>
                          <a href="<?= htmlspecialchars($accountUrl, ENT_QUOTES, 'UTF-8') ?>">View profile</a>
                          <?php if ($account['role'] !== 'super_admin' && (int) $account['id'] !== (int) $user['id']): ?>
                            <?php if ($accountIsArchived): ?>
                              <form method="post" onsubmit="return confirm('Restore this account? Its previous login status will be restored too.');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="restore_account">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                <button class="user-action-restore" type="submit">Restore account</button>
                              </form>
                            <?php else: ?>
                              <form method="post" class="archive-account-form"
                                    onsubmit="return confirm('Archive this account? Sign-in will be blocked, and existing history will be preserved.');">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="archive_account">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                <label>Reason for archiving
                                  <textarea name="reason" rows="3" maxlength="500" required placeholder="Explain why this account is being archived"></textarea>
                                </label>
                                <button class="user-action-archive" type="submit">Archive account</button>
                              </form>
                            <?php endif; ?>
                          <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?>
                  <tr><td colspan="6" class="users-empty-row">
                    <?= $searchQuery !== '' ? 'No accounts match this search.' : ($selectedStatus === 'archived' ? 'There are no archived accounts.' : 'No accounts found for this filter.') ?>
                  </td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <footer class="users-pagination">
            <p>Showing <strong><?= number_format($pageStart) ?>–<?= number_format($pageEnd) ?></strong> of <strong><?= number_format($totalUsers) ?></strong> accounts</p>
            <nav aria-label="User list pages">
              <?php if ($currentPage > 1): ?>
                <a href="<?= htmlspecialchars($buildUsersUrl(['page' => $currentPage - 1, 'account' => null]), ENT_QUOTES, 'UTF-8') ?>" rel="prev">Previous</a>
              <?php else: ?>
                <span class="pagination-disabled" aria-disabled="true">Previous</span>
              <?php endif; ?>
              <span class="pagination-page">Page <?= number_format($currentPage) ?> of <?= number_format($totalPages) ?></span>
              <?php if ($currentPage < $totalPages): ?>
                <a href="<?= htmlspecialchars($buildUsersUrl(['page' => $currentPage + 1, 'account' => null]), ENT_QUOTES, 'UTF-8') ?>" rel="next">Next</a>
              <?php else: ?>
                <span class="pagination-disabled" aria-disabled="true">Next</span>
              <?php endif; ?>
            </nav>
          </footer>
        </section>
      </div>

      <aside class="user-profile-card" aria-label="User account management">
        <?php if ($selectedUser): ?>
          <?php $selectedIsArchived = $selectedUser['account_archived_at'] !== null; ?>
          <span class="profile-avatar"><?= htmlspecialchars(strtoupper(substr($selectedUser['name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></span>
          <h2><?= htmlspecialchars($selectedUser['name'], ENT_QUOTES, 'UTF-8') ?></h2>
          <p class="profile-role"><?= htmlspecialchars($roleLabels[$selectedUser['role']] ?? $selectedUser['role'], ENT_QUOTES, 'UTF-8') ?></p>
          <a class="profile-email" href="mailto:<?= htmlspecialchars($selectedUser['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($selectedUser['email'], ENT_QUOTES, 'UTF-8') ?></a>
          <dl class="profile-details">
            <div><dt>Hospital</dt><dd><?= htmlspecialchars($selectedUser['hospital_name'], ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>Login</dt><dd>
              <?php if ($selectedIsArchived): ?>
                <span class="login-status login-status-archived">Archived</span>
              <?php else: ?>
                <span class="login-status<?= $selectedUser['is_login_disabled'] ? ' login-status-disabled' : '' ?>"><?= $selectedUser['is_login_disabled'] ? 'Disabled' : 'Enabled' ?></span>
              <?php endif; ?>
            </dd></div>
            <div><dt>Phone</dt><dd><?= htmlspecialchars($selectedUser['phone_number'] ?: 'Not provided', ENT_QUOTES, 'UTF-8') ?></dd></div>
            <div><dt>Last active</dt><dd><?= $selectedUser['last_login_at'] ? htmlspecialchars(date('M j, Y · H:i', strtotime($selectedUser['last_login_at'])), ENT_QUOTES, 'UTF-8') : 'Never' ?></dd></div>
            <div><dt>Account created</dt><dd><?= htmlspecialchars(date('M j, Y', strtotime($selectedUser['created_at'])), ENT_QUOTES, 'UTF-8') ?></dd></div>
          </dl>
          <?php if ($selectedIsArchived): ?>
            <div class="login-disabled-reason">
              <strong>Archive reason</strong>
              <p><?= htmlspecialchars($selectedUser['account_archived_reason'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <?php if ($selectedUser['role'] !== 'super_admin' && (int) $selectedUser['id'] !== (int) $user['id']): ?>
              <form class="profile-account-action" method="post" onsubmit="return confirm('Restore this account? Its previous login status will be restored too.');">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="restore_account">
                <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
                <button type="submit" class="page-action-button">Restore account</button>
              </form>
            <?php endif; ?>
          <?php else: ?>
            <form class="admin-form user-editor" method="post">
              <h3>Edit account</h3>
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
              <label>Name<input name="name" maxlength="150" required value="<?= htmlspecialchars($selectedUser['name'], ENT_QUOTES, 'UTF-8') ?>"></label>
              <label>Email<input name="email" type="email" maxlength="150" required value="<?= htmlspecialchars($selectedUser['email'], ENT_QUOTES, 'UTF-8') ?>"></label>
              <label>Phone<input name="phone" maxlength="32" value="<?= htmlspecialchars($selectedUser['phone_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
              <label>Role<select name="role" required><?php foreach ($roleLabels as $role => $label): ?><option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedUser['role'] === $role ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
              <label>Hospital<select name="hospital_id"><option value="" <?= empty($selectedUser['hospital_id']) ? 'selected' : '' ?>>Not assigned</option><?php foreach ($hospitals as $id => $hospitalName): ?><option value="<?= (int) $id ?>" <?= (int) $selectedUser['hospital_id'] === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($hospitalName, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?><?php if (!empty($selectedUser['hospital_id']) && !array_key_exists((int) $selectedUser['hospital_id'], $hospitals)): ?><option value="<?= (int) $selectedUser['hospital_id'] ?>" selected><?= htmlspecialchars($selectedUser['hospital_name'], ENT_QUOTES, 'UTF-8') ?> (inactive current assignment)</option><?php endif; ?></select></label>
              <label>Barangay<select name="barangay_id"><option value="" <?= empty($selectedUser['barangay_id']) ? 'selected' : '' ?>>Not assigned</option><?php foreach ($barangays as $id => $barangayName): ?><option value="<?= (int) $id ?>" <?= (int) $selectedUser['barangay_id'] === (int) $id ? 'selected' : '' ?>><?= htmlspecialchars($barangayName, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?><?php if (!empty($selectedUser['barangay_id']) && !array_key_exists((int) $selectedUser['barangay_id'], $barangays)): ?><option value="<?= (int) $selectedUser['barangay_id'] ?>" selected>Inactive current barangay assignment</option><?php endif; ?></select></label>
              <button type="submit" class="page-action-button">Save account</button>
            </form>
            <div class="account-actions">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
                <input type="hidden" name="action" value="reset_password">
                <button type="submit" class="page-action-button">Issue temporary password</button>
              </form>
              <?php if ($selectedUser['role'] !== 'super_admin'): ?>
                <form method="post" class="login-access-form"
                      onsubmit="return <?= $selectedUser['is_login_disabled'] ? 'true' : "confirm('Disable this user’s login with the entered reason?')" ?>;">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $selectedUser['id'] ?>">
                  <input type="hidden" name="action" value="<?= $selectedUser['is_login_disabled'] ? 'enable_login' : 'disable_login' ?>">
                  <?php if (!$selectedUser['is_login_disabled']): ?>
                    <details class="login-disable-disclosure">
                      <summary class="page-action-button">Disable login</summary>
                      <label class="login-disable-reason">Reason for disabling login
                        <textarea name="reason" rows="3" maxlength="500" required placeholder="Explain why this account cannot sign in"></textarea>
                      </label>
                      <button type="submit" class="page-action-button">Confirm disable login</button>
                    </details>
                  <?php endif; ?>
                  <?php if ($selectedUser['is_login_disabled']): ?>
                    <button type="submit" class="page-action-button enable-login-button">Enable login</button>
                  <?php endif; ?>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <p class="profile-empty">No user accounts found for this filter.</p>
        <?php endif; ?>
      </aside>
    </div>
  <?php endif; ?>
</section>
