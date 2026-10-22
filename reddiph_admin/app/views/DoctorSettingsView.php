<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/DoctorSettings.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/DoctorSettings.css') ?>">
</head>
<body class="doctor-settings-page">
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <span>DOCTOR</span>
        </div>

        <nav class="main-nav" aria-label="Doctor navigation">
            <a class="<?= $currentPage === 'notified-cases' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorNotifiedCasesController.php')) ?>">
                <span class="nav-icon">⌂</span>
                <span>Notified Cases</span>
            </a>
            <a class="<?= $currentPage === 'case-history' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorCaseHistoryController.php')) ?>">
                <span class="nav-icon">◇</span>
                <span>Case History</span>
            </a>
            <a class="<?= $currentPage === 'schedule' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorScheduleController.php')) ?>">
                <span class="nav-icon">▣</span>
                <span>Schedule</span>
            </a>
            <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>" href="<?= htmlspecialchars(assetUrl('app/controllers/DoctorSettingsController.php')) ?>">
                <span class="nav-icon">⚙</span>
                <span>Settings</span>
            </a>
        </nav>

        <div class="doctor-profile">
            <span class="doctor-profile-avatar" aria-hidden="true">
                <?php if (!empty($sidebarDoctor['profile_image_url'])): ?>
                    <img src="<?= htmlspecialchars($sidebarDoctor['profile_image_url']) ?>" alt="">
                <?php else: ?>
                    <?= htmlspecialchars($sidebarDoctor['initials']) ?>
                <?php endif; ?>
            </span>
            <span class="doctor-profile-copy">
                <strong><?= htmlspecialchars($sidebarDoctor['full_name']) ?></strong>
                <span><?= htmlspecialchars($sidebarDoctor['coordinator_role']) ?></span>
            </span>
        </div>
    </aside>

    <main class="content settings-page">
        <div>
            <span>Doctor</span>
            <h1>Settings</h1>
        </div>

        <section class="settings-card" id="settings-card" data-endpoint="<?= htmlspecialchars(assetUrl('app/controllers/DoctorSettingsController.php')) ?>">
            <div class="profile-row">
                <button type="button" class="avatar large settings-profile-avatar" id="settings-profile-avatar" aria-label="Change profile photo">
                    <?php if (!empty($sidebarDoctor['profile_image_url'])): ?>
                        <img src="<?= htmlspecialchars($sidebarDoctor['profile_image_url']) ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars($sidebarDoctor['initials']) ?>
                    <?php endif; ?>
                    <span class="avatar-camera" aria-hidden="true">+</span>
                </button>
                <div>
                    <h2><?= htmlspecialchars($sidebarDoctor['full_name']) ?></h2>
                    <span><?= htmlspecialchars($sidebarDoctor['coordinator_role']) ?></span>
                </div>
            </div>

            <hr>

            <h3>Account Information</h3>
            <form id="settings-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf_token']) ?>">
                <input type="hidden" name="action" value="save_settings">
                <label for="settings-email">Email</label>
                <div class="setting-input">
                    <input class="settings-input" id="settings-email" name="email" type="email" maxlength="150" value="<?= htmlspecialchars($doctor['work_email']) ?>" readonly required>
                    <button type="button" class="settings-edit-button" data-settings-edit="settings-email" aria-label="Edit email">⌕</button>
                </div>
                <label for="settings-phone">Phone</label>
                <div class="setting-input">
                    <input class="settings-input" id="settings-phone" name="phone_number" type="tel" maxlength="32" value="<?= htmlspecialchars($doctor['phone_number'] ?? '') ?>" readonly>
                    <button type="button" class="settings-edit-button" data-settings-edit="settings-phone" aria-label="Edit phone number">⌕</button>
                </div>
                <label for="settings-license">PRC License</label>
                <div class="setting-input">
                    <input class="settings-input" id="settings-license" value="<?= htmlspecialchars($doctor['license_number'] ?? '') ?>" readonly>
                </div>
                <div class="settings-save-row">
                    <button type="submit" class="settings-save-button" id="settings-save-button">Save changes</button>
                </div>
            </form>

            <h3>Security</h3>
            <button type="button" class="security-row" id="settings-change-password">Change password <span>›</span><small><?= htmlspecialchars($doctor['password_changed_label']) ?></small></button>
            <button type="button" class="security-row" id="settings-two-factor">Two-factor authentication <span>›</span><small><?= htmlspecialchars($doctor['two_factor_label']) ?></small></button>

            <div class="settings-actions">
                <button type="button" class="logout" id="settings-logout-button">↪ &nbsp; Log out</button>
            </div>
        </section>
    </main>
</div>
<div class="settings-toast" id="settings-toast" role="status" aria-live="polite"></div>

<div class="settings-drawer-overlay" id="settings-photo-drawer" aria-hidden="true">
    <section class="settings-drawer" role="dialog" aria-modal="true" aria-labelledby="settings-photo-title">
        <button type="button" class="settings-drawer-close" data-settings-close aria-label="Close">×</button>
        <h2 id="settings-photo-title">Update profile photo</h2>
        <div class="photo-preview" id="settings-photo-preview">
            <img id="settings-photo-preview-image" src="<?= htmlspecialchars($doctor['profile_image_url'] ?? '') ?>" alt="" <?= empty($doctor['profile_image_url']) ? 'hidden' : '' ?>>
            <span id="settings-photo-preview-initials" <?= !empty($doctor['profile_image_url']) ? 'hidden' : '' ?>><?= htmlspecialchars($doctor['initials']) ?></span>
        </div>
        <input type="file" id="settings-profile-image-input" accept="image/jpeg,image/png,image/webp" hidden>
        <button type="button" class="settings-secondary-button" id="settings-choose-photo">Choose photo</button>
        <button type="button" class="settings-primary-button" id="settings-upload-photo" disabled>Upload photo</button>
    </section>
</div>

<div class="settings-drawer-overlay" id="settings-password-drawer" aria-hidden="true">
    <section class="settings-drawer" role="dialog" aria-modal="true" aria-labelledby="settings-password-title">
        <button type="button" class="settings-drawer-close" data-settings-close aria-label="Close">×</button>
        <h2 id="settings-password-title">Change password</h2>
        <form id="settings-password-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['settings_csrf_token']) ?>">
            <input type="hidden" name="action" value="change_password">
            <label for="settings-current-password">Current password</label>
            <input id="settings-current-password" type="password" name="current_password" autocomplete="current-password" required>
            <label for="settings-new-password">New password</label>
            <input id="settings-new-password" type="password" name="new_password" minlength="8" autocomplete="new-password" required>
            <label for="settings-confirm-password">Confirm new password</label>
            <input id="settings-confirm-password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
            <button type="submit" class="settings-primary-button">Update password</button>
        </form>
    </section>
</div>

<?php $staffAlertRole = 'doctor'; require __DIR__ . '/partials/staffIncomingAlertView.php'; ?>
<script src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
</body>
</html>
