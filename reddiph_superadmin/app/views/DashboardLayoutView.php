<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($config['app']['name']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars(APP_BASE_URL) ?>/assets/css/auth.css?v=<?= filemtime(BASE_PATH . '/public/assets/css/auth.css') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(APP_BASE_URL) ?>/assets/css/dashboard.css?v=<?= filemtime(BASE_PATH . '/public/assets/css/dashboard.css') ?>&amp;layout=2">
  <link rel="stylesheet" href="<?= htmlspecialchars(APP_BASE_URL) ?>/assets/css/<?= htmlspecialchars($activePage) ?>.css?v=<?= filemtime(BASE_PATH . '/public/assets/css/' . $activePage . '.css') ?>">
</head>
<body class="dashboard"
      data-session-idle-timeout="<?= \App\Controllers\DashboardController::sessionIdleTimeoutSeconds() ?>"
      data-session-activity-url="<?= htmlspecialchars(\App\Controllers\DashboardController::url('/session-activity')) ?>"
      data-session-logout-url="<?= htmlspecialchars(\App\Controllers\DashboardController::url('/logout')) ?>"
      data-session-login-url="<?= htmlspecialchars(\App\Controllers\DashboardController::url('/login')) ?>">
  <div class="admin-shell">
    <aside class="sidebar">
      <a class="brand" href="<?= htmlspecialchars(\App\Controllers\DashboardController::url('/overview')) ?>">
        <span class="brand-mark" aria-hidden="true">R</span>
        <span class="brand-copy"><strong>REDDIPH</strong><small>SUPER ADMIN</small></span>
      </a>
      <nav class="side-nav" aria-label="Main navigation">
        <?php foreach ($navigationGroups as $group => $items): ?>
          <div class="nav-group">
            <span class="nav-caption"><?= htmlspecialchars(strtoupper($group)) ?></span>
            <?php foreach ($items as $item): ?>
              <a class="nav-link<?= $activePage === ltrim($item['path'], '/') ? ' active' : '' ?>"
                 href="<?= htmlspecialchars($item['url']) ?>"
                 <?= $activePage === ltrim($item['path'], '/') ? 'aria-current="page"' : '' ?>>
                <span class="nav-icon" data-icon="<?= htmlspecialchars($item['icon']) ?>" aria-hidden="true"></span>
                <?= htmlspecialchars($item['label']) ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </nav>
      <div class="sidebar-footer">
        <div class="user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($user['name'] ?: 'A', 0, 1))) ?></div>
        <div class="user-details"><strong><?= htmlspecialchars($user['name']) ?></strong><small>Super Administrator</small></div>
        <form method="post" action="<?= htmlspecialchars(\App\Controllers\DashboardController::url('/logout')) ?>">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <button class="logout-button" type="submit" aria-label="Log out" title="Log out">↗</button>
        </form>
      </div>
    </aside>
    <main class="admin-main">
      <header class="topbar">
        <span>Administration <span aria-hidden="true">/</span> <?= htmlspecialchars($pageTitle) ?></span>
        <span class="user-menu"><span><?= htmlspecialchars(strtoupper(substr($user['name'] ?: 'A', 0, 1))) ?></span><?= htmlspecialchars($user['name']) ?></span>
      </header>
      <?php if ($flash): ?>
        <div class="dashboard-flash <?= $flash['type'] === 'success' ? 'is-success' : 'is-error' ?>" role="<?= $flash['type'] === 'success' ? 'status' : 'alert' ?>">
          <?= htmlspecialchars($flash['message']) ?>
        </div>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>
  <script src="<?= htmlspecialchars(APP_BASE_URL) ?>/assets/js/script.js?v=<?= filemtime(BASE_PATH . '/public/assets/js/script.js') ?>" defer></script>
</body>
</html>
