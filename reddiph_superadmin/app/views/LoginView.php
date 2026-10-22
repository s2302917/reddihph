<div class="shell">
  <section class="hero" aria-label="Service status">
    <svg class="logo" viewBox="0 0 48 48" aria-hidden="true">
      <path fill="currentColor" d="M6 6h22c8 0 13 4 13 11 0 5-3 8-7 9.5L43 42H31l-7-13h-5v13H6V6zm13 9v6h8c2 0 3-1 3-3s-1-3-3-3h-8z"/>
    </svg>
    <div class="hero-copy">
      <h1>Everything you run, in one place.</h1>
      <p>Manage users, roles, hospitals and barangays, and keep an eye on system health.</p>
    </div>
    
  </section>

  <main class="panel">
    <form class="card" method="post" action="<?= htmlspecialchars(\App\Controllers\DashboardController::url('/login')) ?>" novalidate>
      <h2>Super Admin sign in</h2>
      <p class="sub">Use your administrator account to continue.</p>

      <?php if ($error): ?>
        <div class="alert" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <input type="hidden" name="csrf" value="<?= $csrf ?>">

      <label for="email">Email</label>
      <input id="email" name="email" type="email" autocomplete="username" required autofocus
             value="<?= htmlspecialchars($email) ?>" placeholder="you@example.com">

      <label for="password">Password</label>
      <div class="pw">
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="button" class="toggle" aria-controls="password" aria-pressed="false">Show</button>
      </div>

      <button class="btn" type="submit">Sign in</button>
      <p class="note">This portal is restricted to Super Admin accounts.</p>
    </form>
  </main>
</div>
