<section class="page-content branding-page">
  <header class="branding-heading"><div><h1>UI and branding</h1><p>Manage application identity for shared, website, and mobile experiences.</p></div></header>
  <nav class="scope-tabs" aria-label="Branding platform">
    <?php foreach (['shared' => 'Shared', 'web' => 'Website', 'mobile' => 'Mobile'] as $scope => $label): ?>
      <a class="<?= $selectedScope === $scope ? 'selected' : '' ?>" href="<?= htmlspecialchars($navigation[4]['url'] . '?scope=' . $scope) ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php
    $websiteBaseUrl = rtrim(dirname(APP_BASE_URL), '/') . '/reddiph_admin/';
  ?>
  <section class="branding-preview"
    data-branding-preview-base="<?= htmlspecialchars($websiteBaseUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-branding-scope="<?= htmlspecialchars($selectedScope, ENT_QUOTES, 'UTF-8') ?>"
    data-default-app-name="<?= htmlspecialchars($defaultBranding['app_name'], ENT_QUOTES, 'UTF-8') ?>"
    data-default-logo-path="<?= htmlspecialchars($defaultBranding['logo_path'], ENT_QUOTES, 'UTF-8') ?>"
    data-default-favicon-path="<?= htmlspecialchars($defaultBranding['favicon_path'], ENT_QUOTES, 'UTF-8') ?>"
    data-default-primary-color="<?= htmlspecialchars($defaultBranding['primary_color'], ENT_QUOTES, 'UTF-8') ?>"
    data-default-secondary-color="<?= htmlspecialchars($defaultBranding['secondary_color'], ENT_QUOTES, 'UTF-8') ?>">
    <div class="branding-preview-top">
      <span>LIVE WEBSITE PREVIEW</span>
      <a href="<?= htmlspecialchars($websiteBaseUrl . 'app/controllers/portalController.php', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Open website ↗</a>
    </div>
    <label class="branding-preview-page">
      Preview page
      <select data-branding-preview-page>
        <option value="portalController.php">Portal</option>
        <option value="hospital_admin_loginController.php">Hospital Admin login</option>
        <option value="doctor_loginController.php">Doctor login</option>
        <option value="nurse_loginController.php">Nurse login</option>
      </select>
    </label>
    <div class="branding-preview-frame">
      <iframe data-branding-website-preview
        src="<?= htmlspecialchars($websiteBaseUrl . 'app/controllers/portalController.php', ENT_QUOTES, 'UTF-8') ?>"
        title="Reddiph website live preview"></iframe>
    </div>
  </section>
  <form class="admin-form branding-form" method="post">
      <h2><?= $selectedScope === 'shared' ? 'Shared identity and accents' : htmlspecialchars(ucfirst($selectedScope)) . ' identity and accents' ?></h2><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="scope" value="<?= htmlspecialchars($selectedScope) ?>">
      <label>Application name<input name="app_name" maxlength="120" value="<?= htmlspecialchars($selectedBranding['app_name'] ?: $defaultBranding['app_name']) ?>" data-brand-name-input></label>
      <label>Logo path<input name="logo_path" maxlength="255" value="<?= htmlspecialchars($selectedBranding['logo_path'] ?: $defaultBranding['logo_path']) ?>" placeholder="https://... or relative asset path" data-brand-logo-input></label>
      <label>Favicon path<input name="favicon_path" maxlength="255" value="<?= htmlspecialchars($selectedBranding['favicon_path'] ?? '') ?>" data-brand-favicon-input></label>
      <label>
        Primary accent color
        <input name="primary_color" type="color" value="<?= htmlspecialchars($selectedBranding['primary_color'] ?: $defaultBranding['primary_color']) ?>" data-primary-color>
        <small>Website variable: --brand-primary-color. Used for buttons, links, and active accents.</small>
      </label>
      <label>
        Secondary accent color
        <input name="secondary_color" type="color" value="<?= htmlspecialchars($selectedBranding['secondary_color'] ?: $defaultBranding['secondary_color']) ?>" data-secondary-color>
        <small>Website variable: --brand-secondary-color. Used for secondary accents and hover states.</small>
      </label>
      <div class="branding-form-actions">
        <button class="page-action-button branding-reset-button" type="button" data-reset-branding>Reset to default</button>
        <button class="page-action-button" type="submit">Save branding</button>
        <span class="branding-reset-note"><?= $selectedScope === 'shared' ? 'Saving shared branding applies these values to both web and mobile. You can customize either platform separately afterward; the next shared save will apply shared values again. ' : '' ?>Portal role-card colors are part of the website design and are not changed by these accent settings. Reset values are saved only when you select Save branding.</span>
      </div>
  </form>
</section>
