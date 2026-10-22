<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/NurseSettings.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/NurseSettings.css') ?>">
</head>
<body class="nurse-settings-page">
<div class="nurse-settings-app-shell">
    <aside class="sidebar nurse-sidebar">
        <?php require __DIR__ . '/partials/nurseSidebarView.php'; ?>
    </aside>

    <main class="nurse-settings-content">
        <header class="nurse-settings-heading">
            <span>Nurse</span>
            <h1>Settings</h1>
        </header>

        <section class="nurse-settings-card" id="nurse-settings-card"
            data-endpoint="<?= htmlspecialchars(assetUrl('app/controllers/NurseSettingsController.php')) ?>">
            <div class="nurse-settings-profile-row">
                <button type="button" class="nurse-settings-avatar" id="nurse-settings-profile-avatar" aria-label="Change profile photo">
                    <?php if (!empty($nurse['profile_image_url'])): ?>
                        <img src="<?= htmlspecialchars($nurse['profile_image_url']) ?>" alt="">
                    <?php else: ?>
                        <?= htmlspecialchars($nurse['initials']) ?>
                    <?php endif; ?>
                    <span class="nurse-settings-avatar-camera" aria-hidden="true">+</span>
                </button>
                <div>
                    <h2><?= htmlspecialchars($nurse['full_name']) ?></h2>
                    <span>Nurse</span>
                </div>
            </div>

            <hr>

            <h3>Account Information</h3>
            <form id="nurse-settings-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['nurse_settings_csrf_token']) ?>">
                <input type="hidden" name="action" value="save_settings">
                <label for="nurse-settings-email">Email</label>
                <div class="nurse-settings-input">
                    <input id="nurse-settings-email" name="email" type="email" maxlength="150"
                        value="<?= htmlspecialchars($nurse['work_email']) ?>" readonly required>
                    <button type="button" data-nurse-settings-edit="nurse-settings-email" aria-label="Edit email">⌕</button>
                </div>
                <label for="nurse-settings-phone">Phone</label>
                <div class="nurse-settings-input">
                    <input id="nurse-settings-phone" name="phone_number" type="tel" maxlength="32"
                        value="<?= htmlspecialchars($nurse['phone_number'] ?? '') ?>" readonly>
                    <button type="button" data-nurse-settings-edit="nurse-settings-phone" aria-label="Edit phone number">⌕</button>
                </div>
                <label for="nurse-settings-license">PRC License</label>
                <div class="nurse-settings-input">
                    <input id="nurse-settings-license" value="<?= htmlspecialchars($nurse['license_number'] ?? '') ?>" readonly>
                </div>
                <div class="nurse-settings-save-row">
                    <button type="submit" class="nurse-settings-save-button" id="nurse-settings-save-button">Save changes</button>
                </div>
            </form>

            <h3>Security</h3>
            <button type="button" class="nurse-settings-security-row" id="nurse-settings-change-password">
                Change password <span>›</span><small><?= htmlspecialchars($nurse['password_changed_label']) ?></small>
            </button>
            <button type="button" class="nurse-settings-security-row" id="nurse-settings-two-factor">
                Two-factor authentication <span>›</span><small><?= htmlspecialchars($nurse['two_factor_label']) ?></small>
            </button>

            <div class="nurse-settings-actions">
                <button type="button" class="nurse-settings-logout" id="nurse-settings-logout-button">↪ &nbsp; Log out</button>
            </div>
        </section>
    </main>
</div>

<div class="nurse-settings-toast" id="nurse-settings-toast" role="status" aria-live="polite"></div>

<div class="nurse-settings-drawer-overlay" id="nurse-settings-photo-drawer" aria-hidden="true" inert>
    <section class="nurse-settings-drawer" role="dialog" aria-modal="true" aria-labelledby="nurse-settings-photo-title">
        <button type="button" class="nurse-settings-drawer-close" data-nurse-settings-close aria-label="Close">×</button>
        <h2 id="nurse-settings-photo-title">Update profile photo</h2>
        <div class="nurse-settings-photo-preview">
            <img id="nurse-settings-photo-preview-image" src="<?= htmlspecialchars($nurse['profile_image_url'] ?? '') ?>" alt="" <?= empty($nurse['profile_image_url']) ? 'hidden' : '' ?>>
            <span id="nurse-settings-photo-preview-initials" <?= !empty($nurse['profile_image_url']) ? 'hidden' : '' ?>><?= htmlspecialchars($nurse['initials']) ?></span>
        </div>
        <input type="file" id="nurse-settings-profile-image-input" accept="image/jpeg,image/png,image/webp" hidden>
        <button type="button" class="nurse-settings-secondary-button" id="nurse-settings-choose-photo">Choose photo</button>
        <button type="button" class="nurse-settings-primary-button" id="nurse-settings-upload-photo" disabled>Upload photo</button>
    </section>
</div>

<div class="nurse-settings-drawer-overlay" id="nurse-settings-password-drawer" aria-hidden="true" inert>
    <section class="nurse-settings-drawer" role="dialog" aria-modal="true" aria-labelledby="nurse-settings-password-title">
        <button type="button" class="nurse-settings-drawer-close" data-nurse-settings-close aria-label="Close">×</button>
        <h2 id="nurse-settings-password-title">Change password</h2>
        <form id="nurse-settings-password-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['nurse_settings_csrf_token']) ?>">
            <input type="hidden" name="action" value="change_password">
            <label for="nurse-settings-current-password">Current password</label>
            <input id="nurse-settings-current-password" type="password" name="current_password" autocomplete="current-password" required>
            <label for="nurse-settings-new-password">New password</label>
            <input id="nurse-settings-new-password" type="password" name="new_password" minlength="8" autocomplete="new-password" required>
            <label for="nurse-settings-confirm-password">Confirm new password</label>
            <input id="nurse-settings-confirm-password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
            <button type="submit" class="nurse-settings-primary-button">Update password</button>
        </form>
    </section>
</div>

<div class="nurse-settings-logout-overlay" id="nurse-settings-logout-overlay" aria-hidden="true" inert>
    <section class="nurse-settings-logout-dialog" role="dialog" aria-modal="true" aria-labelledby="nurse-settings-logout-title">
        <h2 id="nurse-settings-logout-title">Log out of Pulse Alert?</h2>
        <p>You'll need to sign back in to view your nurse account.</p>
        <div>
            <button type="button" id="nurse-settings-logout-cancel">Cancel</button>
            <a href="<?= htmlspecialchars(assetUrl('app/controllers/logoutController.php?role=nurse')) ?>">Log out</a>
        </div>
    </section>
</div>

<?php $staffAlertRole = 'nurse'; require __DIR__ . '/partials/staffIncomingAlertView.php'; ?>
<script src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
</body>
</html>
