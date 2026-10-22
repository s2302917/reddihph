<?php
/**
 * ============================================================
 * settingsView.php
 * ------------------------------------------------------------
 * VIEW — HTML/PRESENTATION ONLY
 *
 * No SQL.
 * No database connection.
 * No database queries.
 * No business logic.
 *
 * Data should be supplied by the Settings Controller.
 * ============================================================
 */

$hospitalName = $hospitalName ?? 'DSB District Hospital';
$coordinatorName = $coordinatorName ?? 'Charlotte Malauan';
$coordinatorRole = $coordinatorRole ?? 'Chief Coordinator';

$accountEmail = $accountEmail ?? 'c.malajuan@dsb.ph';
$accountPhone = $accountPhone ?? '+63 917 000 0000';
$hospitalLocation = $hospitalLocation ?? 'La Carlota';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Settings — PulseAlert</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet"
        href="<?= htmlspecialchars(assetUrl('public/css/incomingdispatch.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/incomingdispatch.css') ?>">

    <link rel="stylesheet"
        href="<?= htmlspecialchars(assetUrl('public/css/settings.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/settings.css') ?>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>

<body class="incomingdispatch-body settings-body">

    <div class="incomingdispatch-shell settings-shell">

        <!-- ======================================================
         SIDEBAR
         ====================================================== -->

        <aside class="incomingdispatch-sidebar" id="settings-sidebar">

            <div class="incomingdispatch-brand">
                <span class="incomingdispatch-brand-tag">ADMIN</span>
            </div>


            <nav class="incomingdispatch-nav" aria-label="Primary">

                <a href="dispatchController.php" class="incomingdispatch-nav-item">Dashboard</a>


                <a href="livemappingController.php" class="incomingdispatch-nav-item">Live Mapping</a>


                <a href="emergency_alertController.php" class="incomingdispatch-nav-item">Emergency Cases</a>


                <a href="staffschedController.php" class="incomingdispatch-nav-item">Staff Schedule</a>

                <a href="resourcesController.php" class="incomingdispatch-nav-item">Resources</a>

                <a href="hospitalnetworkController.php" class="incomingdispatch-nav-item">Referral</a>


                <a href="analyticsController.php" class="incomingdispatch-nav-item">Analytics</a>


                <a href="demographicsController.php" class="incomingdispatch-nav-item">Logs &amp; Demographics</a>


                <a href="settingsController.php" class="incomingdispatch-nav-item incomingdispatch-nav-item--active"
                    id="settings-nav-settings" aria-current="page">
                    Settings
                </a>

            </nav>


            <!-- System Status -->

            <div class="incomingdispatch-system-status">

                <span class="incomingdispatch-system-status-icon" aria-hidden="true">!</span>

                <div>

                    <p class="incomingdispatch-system-status-title">
                        System Status
                    </p>

                    <p class="incomingdispatch-system-status-sub">
                        All systems operational.
                    </p>

                </div>

            </div>

        </aside>


        <!-- ======================================================
         MAIN CONTENT
         ====================================================== -->

        <main class="incomingdispatch-main settings-main">

            <!-- Page Heading -->

            <header class="incomingdispatch-header">
                <div class="incomingdispatch-header-copy">
                    <h1 class="incomingdispatch-hospital-name"><?= htmlspecialchars($hospitalName) ?></h1>
                    <h2 class="incomingdispatch-page-title">Settings</h2>
                </div>
                <div class="incomingdispatch-header-controls">
                    <button type="button" class="incomingdispatch-bell" id="incomingdispatch-bell-btn"
                        aria-label="Notifications" aria-expanded="false"
                        aria-controls="incomingdispatch-notification-panel">
                        🔔
                        <span class="incomingdispatch-bell-dot" id="incomingdispatch-bell-dot" hidden></span>
                    </button>
                    <div class="incomingdispatch-notification-panel" id="incomingdispatch-notification-panel" hidden>
                        <strong>Active emergency dispatches</strong>
                        <div id="incomingdispatch-notification-list">
                            <span class="incomingdispatch-notification-empty">Loading active emergencies</span>
                        </div>
                    </div>
                    <div class="incomingdispatch-user" id="incomingdispatch-user-menu">
                        <div class="incomingdispatch-user-avatar" aria-hidden="true">
                            <?php if (!empty($coordinatorImage)): ?>
                                <img src="<?= htmlspecialchars($coordinatorImage) ?>" alt="">
                            <?php else: ?>
                                <?= htmlspecialchars($coordinatorInitials) ?>
                            <?php endif; ?>
                        </div>
                        <div class="incomingdispatch-user-text">
                            <p class="incomingdispatch-user-name"><?= htmlspecialchars($coordinatorName) ?></p>
                            <p class="incomingdispatch-user-role"><?= htmlspecialchars($coordinatorRole) ?></p>
                        </div>
                    </div>
                </div>
            </header>


            <!-- ==================================================
             SETTINGS CARD
             ================================================== -->

            <section class="settings-card" id="settings-card">

                <!-- Profile Header -->

                <div class="settings-profile-header">

                    <button type="button" class="settings-profile-avatar" id="settings-profile-avatar"
                        aria-label="Change profile photo" title="Change profile photo">
                        <?php if (!empty($coordinatorImage)): ?>
                            <img src="<?= htmlspecialchars($coordinatorImage) ?>" alt="">
                        <?php else: ?>
                            <?= htmlspecialchars($coordinatorInitials) ?>
                        <?php endif; ?>
                    </button>


                    <div class="settings-profile-information">

                        <h2 class="settings-profile-name">
                            <?= htmlspecialchars($coordinatorName) ?>
                        </h2>

                        <p class="settings-profile-role">
                            <?= htmlspecialchars($coordinatorRole) ?>
                        </p>

                    </div>

                </div>


                <div class="settings-divider"></div>


                <!-- ==================================================
                 ACCOUNT + HOSPITAL PROFILE
                 ================================================== -->

                <form method="post"
                    action="<?= htmlspecialchars(assetUrl('app/controllers/settingsController.php')) ?>"
                    id="settings-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="save_settings">

                    <div class="settings-information-grid">

                        <!-- Account Information -->

                        <section class="settings-section settings-account-section">

                            <h3 class="settings-section-title">
                                Account Information
                            </h3>


                            <div class="settings-field">

                                <label for="settings-email" class="settings-field-label">
                                    Email
                                </label>


                                <div class="settings-field-control">

                                    <input type="email" id="settings-email" name="email" class="settings-input"
                                        value="<?= htmlspecialchars($accountEmail) ?>" readonly required
                                        autocomplete="email">

                                    <button type="button" class="settings-edit-button"
                                        data-settings-edit="settings-email" aria-label="Edit email">
                                        ✎
                                    </button>

                                </div>

                            </div>


                            <div class="settings-field">

                                <label for="settings-phone" class="settings-field-label">
                                    Phone
                                </label>


                                <div class="settings-field-control">

                                    <input type="tel" id="settings-phone" name="phone_number" class="settings-input"
                                        value="<?= htmlspecialchars($accountPhone) ?>" readonly autocomplete="tel"
                                        maxlength="32">

                                    <button type="button" class="settings-edit-button"
                                        data-settings-edit="settings-phone" aria-label="Edit phone number">
                                        ✎
                                    </button>

                                </div>

                            </div>


                            <!-- Security -->

                            <h3 class="settings-section-title settings-security-title">
                                Security
                            </h3>


                            <button type="button" class="settings-security-row" id="settings-change-password">

                                <span>

                                    <strong>
                                        Change password
                                    </strong>

                                    <small>
                                        Last changed: recently
                                    </small>

                                </span>

                                <span class="settings-security-arrow" aria-hidden="true">
                                    ›
                                </span>

                            </button>


                            <button type="button" class="settings-security-row" id="settings-two-factor">

                                <span>

                                    <strong>
                                        Two-factor authentication
                                    </strong>

                                    <small>
                                        Not enabled
                                    </small>

                                </span>

                                <span class="settings-security-arrow" aria-hidden="true">
                                    ›
                                </span>

                            </button>

                        </section>


                        <!-- Hospital Profile -->

                        <section class="settings-section settings-hospital-section">

                            <h3 class="settings-section-title">
                                Hospital Profile
                            </h3>


                            <div class="settings-hospital-card">
                                <?php if (!empty($canManageHospital)): ?>
                                    <input type="text" id="settings-hospital-name" name="hospital_name"
                                        class="settings-hospital-name settings-hospital-name-input"
                                        value="<?= htmlspecialchars($hospitalName) ?>" readonly required>

                                    <div class="settings-hospital-location-label">Location</div>

                                    <div class="settings-hospital-location">
                                        <?= htmlspecialchars($hospitalLocation) ?>
                                    </div>

                                    <label class="settings-coordinate-label"
                                        for="settings-hospital-latitude">Coordinates</label>
                                    <div class="settings-coordinate-fields">
                                        <input type="number" id="settings-hospital-latitude" name="latitude" min="-90"
                                            max="90" step="0.00000001"
                                            value="<?= htmlspecialchars((string) ($hospitalLatitude ?? '')) ?>"
                                            placeholder="Latitude">
                                        <input type="number" id="settings-hospital-longitude" name="longitude" min="-180"
                                            max="180" step="0.00000001"
                                            value="<?= htmlspecialchars((string) ($hospitalLongitude ?? '')) ?>"
                                            placeholder="Longitude">
                                    </div>
                                    <button type="button" class="settings-location-picker" id="settings-open-location">
                                        Pin hospital location
                                    </button>


                                    <button type="button" class="settings-hospital-edit" id="settings-edit-hospital">
                                        Edit hospital info
                                    </button>
                                <?php else: ?>
                                    <strong class="settings-hospital-name"><?= htmlspecialchars($hospitalName) ?></strong>
                                    <div class="settings-hospital-location-label">Location</div>
                                    <div class="settings-hospital-location"><?= htmlspecialchars($hospitalLocation) ?></div>
                                    <?php if ($hospitalLatitude !== null && $hospitalLongitude !== null): ?>
                                        <p class="settings-hospital-location">Coordinates:
                                            <?= htmlspecialchars((string) $hospitalLatitude) ?>,
                                            <?= htmlspecialchars((string) $hospitalLongitude) ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>

                            </div>

                        </section>

                    </div>


                    <div class="settings-divider settings-divider-bottom"></div>


                    <!-- ==================================================
                 ACTIONS
                 ================================================== -->

                    <div class="settings-actions">

                        <button type="submit" class="settings-save-button" id="settings-save-button">
                            Save changes
                        </button>


                        <button type="button" class="settings-logout-button" id="settings-logout-button">
                            <span aria-hidden="true">↪</span>
                            Log out
                        </button>

                    </div>

                </form>

            </section>

        </main>

    </div>


    <div class="settings-drawer-overlay" id="settings-photo-drawer" aria-hidden="true">
        <section class="settings-drawer" role="dialog" aria-modal="true" aria-labelledby="settings-photo-title">
            <button type="button" class="settings-drawer-close" data-settings-close aria-label="Close">&#10005;</button>
            <h2 id="settings-photo-title">Update profile photo</h2>
            <p class="settings-drawer-copy">Preview your photo before saving. JPG, PNG, or WebP, up to 5 MB.</p>
            <div class="settings-photo-preview" id="settings-photo-preview">
                <?php if (!empty($coordinatorImage)): ?>
                    <img id="settings-photo-preview-image" src="<?= htmlspecialchars($coordinatorImage) ?>"
                        alt="Profile photo preview">
                <?php else: ?>
                    <span id="settings-photo-preview-initials"><?= htmlspecialchars($coordinatorInitials) ?></span>
                    <img id="settings-photo-preview-image" alt="Profile photo preview" hidden>
                <?php endif; ?>
            </div>
            <input type="file" id="settings-profile-image-input" accept="image/jpeg,image/png,image/webp" hidden>
            <div class="settings-drawer-actions">
                <button type="button" class="settings-secondary-button" id="settings-choose-photo">Choose image</button>
                <button type="button" class="settings-primary-button" id="settings-upload-photo" disabled>Confirm and
                    upload</button>
            </div>
        </section>
    </div>

    <div class="settings-drawer-overlay" id="settings-password-drawer" aria-hidden="true">
        <section class="settings-drawer" role="dialog" aria-modal="true" aria-labelledby="settings-password-title">
            <button type="button" class="settings-drawer-close" data-settings-close aria-label="Close">&#10005;</button>
            <h2 id="settings-password-title">Change password</h2>
            <form id="settings-password-form" method="post"
                action="<?= htmlspecialchars(assetUrl('app/controllers/settingsController.php')) ?>" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action" value="change_password">
                <label class="settings-drawer-label" for="settings-current-password">Current password</label>
                <input class="settings-drawer-input" type="password" id="settings-current-password"
                    name="current_password" required autocomplete="current-password">
                <label class="settings-drawer-label" for="settings-new-password">New password</label>
                <input class="settings-drawer-input" type="password" id="settings-new-password" name="new_password"
                    minlength="8" required autocomplete="new-password">
                <label class="settings-drawer-label" for="settings-confirm-password">Confirm new password</label>
                <input class="settings-drawer-input" type="password" id="settings-confirm-password"
                    name="confirm_password" minlength="8" required autocomplete="new-password">
                <button type="submit" class="settings-primary-button settings-password-submit">Update password</button>
            </form>
        </section>
    </div>

    <div class="settings-drawer-overlay" id="settings-location-drawer" aria-hidden="true">
        <section class="settings-drawer settings-location-drawer" role="dialog" aria-modal="true"
            aria-labelledby="settings-location-title">
            <button type="button" class="settings-drawer-close" data-settings-close aria-label="Close">&#10005;</button>
            <h2 id="settings-location-title">Pin hospital location</h2>
            <p class="settings-drawer-copy">Select the hospital on the map, or use this device's location.</p>
            <div id="settings-location-map" class="settings-location-map" role="application"
                aria-label="Hospital location map"></div>
            <button type="button" class="settings-secondary-button settings-geolocate-button"
                id="settings-use-location">Use my location</button>
            <div class="settings-drawer-actions">
                <button type="button" class="settings-secondary-button" data-settings-close>Cancel</button>
                <button type="button" class="settings-primary-button" id="settings-confirm-location">Use this
                    pin</button>
            </div>
        </section>
    </div>

    <div class="settings-toast" id="settings-toast" role="status" aria-live="polite"
        data-message="<?= htmlspecialchars((string) ($settingsMessage ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        data-kind="<?= htmlspecialchars((string) ($settingsMessageType ?? 'success'), ENT_QUOTES, 'UTF-8') ?>"></div>


    <div class="settings-logout-overlay" id="settings-logout-overlay" aria-hidden="true">

        <section class="settings-logout-drawer" role="dialog" aria-modal="true" aria-labelledby="settings-logout-title"
            aria-describedby="settings-logout-message">

            <h2 id="settings-logout-title">Log out of Pulse Alert?</h2>

            <p id="settings-logout-message">
                You'll need to sign back in to view dispatches, alerts,<br>
                and staff schedules for DSB District Hospital.
            </p>

            <div class="settings-logout-actions">

                <button type="button" class="settings-logout-cancel" id="settings-logout-cancel">
                    Cancel
                </button>

                <a class="settings-logout-confirm" id="settings-logout-confirm"
                    href="logoutController.php?role=<?= rawurlencode((string) ($_SESSION['role'] ?? '')) ?>">
                    Log out
                </a>

            </div>

        </section>

    </div>


    <script>
        window.APP_BASE_URL =
            <?= json_encode(rtrim(assetUrl(''), '/')) ?>;
    </script>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script
        src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var logoutButton = document.getElementById('settings-logout-button');
            var logoutOverlay = document.getElementById('settings-logout-overlay');
            var logoutCancel = document.getElementById('settings-logout-cancel');

            if (!logoutButton || !logoutOverlay || logoutButton.dataset.drawerBound === 'true') {
                return;
            }

            logoutButton.dataset.drawerBound = 'true';

            function closeLogoutDrawer() {
                logoutOverlay.classList.remove('settings-logout-overlay--open');
                logoutOverlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('settings-logout-open');
                logoutButton.focus();
            }

            logoutButton.addEventListener('click', function () {
                logoutOverlay.classList.add('settings-logout-overlay--open');
                logoutOverlay.setAttribute('aria-hidden', 'false');
                document.body.classList.add('settings-logout-open');
                logoutCancel.focus();
            });

            logoutCancel.addEventListener('click', closeLogoutDrawer);

            logoutOverlay.addEventListener('click', function (event) {
                if (event.target === logoutOverlay) {
                    closeLogoutDrawer();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (
                    event.key === 'Escape' &&
                    logoutOverlay.classList.contains('settings-logout-overlay--open')
                ) {
                    closeLogoutDrawer();
                }
            });
        });
    </script>

</body>

</html>