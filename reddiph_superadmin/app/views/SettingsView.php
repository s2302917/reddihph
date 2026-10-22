<section class="page-content settings-page">
  <div class="page-heading"><div><p class="eyebrow">CONFIGURATION</p><h1>System settings</h1><p>Manage shared, website, and mobile application settings.</p></div></div>
  <nav class="scope-tabs" aria-label="Settings platform">
    <?php foreach (['shared' => 'Shared', 'web' => 'Website', 'mobile' => 'Mobile'] as $scope => $label): ?>
      <a class="<?= $settings['scope'] === $scope ? 'selected' : '' ?>" href="<?= htmlspecialchars($navigation[6]['url'] . '?scope=' . $scope) ?>"><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <section class="content-card">
    <div class="table-toolbar"><div><h2>Environment</h2><p>Application configuration and safe database metadata.</p></div></div>
    <dl class="settings-list">
      <div><dt>Application name</dt><dd><?= htmlspecialchars($settings['appName']) ?></dd></div>
      <div><dt>Environment</dt><dd><?= htmlspecialchars($settings['environment']) ?></dd></div>
      <div><dt>Database name</dt><dd><span><?= htmlspecialchars($settings['databaseName']) ?></span><button type="button" class="settings-copy-button" data-copy-value="<?= htmlspecialchars($settings['databaseName']) ?>">Copy</button></dd></div>
      <div><dt>Database host</dt><dd><span><?= htmlspecialchars($settings['databaseHost']) ?></span><button type="button" class="settings-copy-button" data-copy-value="<?= htmlspecialchars($settings['databaseHost']) ?>">Copy</button></dd></div>
    </dl>
  </section>
  <section class="content-card">
      <div class="table-toolbar"><div><h2>Stored settings</h2><p><?= htmlspecialchars(ucfirst($settings['scope'])) ?> values shared with the application.</p></div></div>
      <div class="table-scroll"><table class="data-table"><thead><tr><th>Key</th><th>Value</th><th>Type</th><th>Description</th><th></th></tr></thead><tbody>
        <?php foreach ($settings['values'] as $setting): ?>
          <tr><td><code><?= htmlspecialchars($setting['setting_key']) ?></code></td><td><pre class="setting-value"><?= htmlspecialchars($setting['setting_value'] ?? '') ?></pre></td><td><?= htmlspecialchars($setting['value_type']) ?></td><td><?= htmlspecialchars($setting['description'] ?? '') ?></td><td>
            <form method="post" onsubmit="return confirm('Delete this setting?');"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="scope" value="<?= htmlspecialchars($settings['scope']) ?>"><input type="hidden" name="setting_key" value="<?= htmlspecialchars($setting['setting_key']) ?>"><button class="page-action-button" type="submit">Delete</button></form>
          </td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
      <?php if (!$settings['values']): ?><p class="empty-state">No settings are stored for this platform yet.</p><?php endif; ?>
    </section>
    <form class="admin-form setting-editor" method="post">
      <h2>Add or update a setting</h2><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="scope" value="<?= htmlspecialchars($settings['scope']) ?>">
      <label>Key<input name="setting_key" required maxlength="120" pattern="[A-Za-z][A-Za-z0-9_.-]*" placeholder="mobile.support_email"></label>
      <label>Value<textarea name="setting_value" rows="4"></textarea></label>
      <label>Type<select name="value_type"><option value="string">String</option><option value="integer">Integer</option><option value="boolean">Boolean</option><option value="json">JSON</option></select></label>
      <label>Description<input name="description" maxlength="255"></label>
      <button class="page-action-button" type="submit">Save setting</button>
  </form>
</section>
