<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts — Reddiph</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/incomingdispatch.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/incomingdispatch.css'); ?>">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/manageAccounts.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/manageAccounts.css'); ?>">
</head>

<body class="incomingdispatch-body manageaccounts-body">
    <div class="incomingdispatch-shell manageaccounts-shell">
        <aside class="incomingdispatch-sidebar" id="incomingdispatch-sidebar">
            <div class="incomingdispatch-brand">
                <span class="incomingdispatch-brand-tag">ADMIN</span>
            </div>

            <nav class="incomingdispatch-nav" aria-label="Primary">
                <a href="dispatchController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-dispatches">Dashboard</a>
                <a href="livemappingController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-mapping">Live Mapping</a>
                <a href="emergency_alertController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-alerts">Emergency Cases</a>
                <details class="admin-resources-menu">
                    <summary class="incomingdispatch-nav-item" id="incomingdispatch-nav-resources">Resources</summary>
                    <div class="admin-resources-subnav">
                        <a href="resourcesController.php?section=bed-capacity">Bed Capacity</a>
                        <a href="resourcesController.php?section=ambulance">Ambulance</a>
                        <a href="resourcesController.php?section=ptv">PTV</a>
                    </div>
                </details>
                <a href="staffschedController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-schedule">Staff Schedule</a>
                <a href="hospitalnetworkController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-network">Referral</a>
                <a href="demographicsController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-logs">Logs &amp; Demographics</a>
                <a href="manageAccountsController.php" class="incomingdispatch-nav-item incomingdispatch-nav-item--active" id="incomingdispatch-nav-manage-accounts">Manage Accounts</a>
                <a href="settingsController.php" class="incomingdispatch-nav-item" id="incomingdispatch-nav-settings">Settings</a>
            </nav>

        </aside>

        <main class="incomingdispatch-main manageaccounts-main">
            <header class="incomingdispatch-header manageaccounts-header">
                <div class="incomingdispatch-header-copy">
                    <h1 class="manageaccounts-page-title" id="manageaccounts-title">Staff Accounts</h1>
                    <p class="manageaccounts-page-subtitle">You can create new staff accounts for your doctors and nurses</p>
                </div>
            </header>

            <section class="manageaccounts-panel" aria-labelledby="manageaccounts-title">
                <p class="manageaccounts-notice">This page is for managing doctor and nurse accounts assigned to your hospital.</p>
                <div class="manageaccounts-feedback" id="manageaccounts-feedback" role="status" aria-live="polite" hidden>
                    <span class="manageaccounts-feedback-icon" aria-hidden="true"></span>
                    <div class="manageaccounts-feedback-copy">
                        <strong class="manageaccounts-feedback-title"></strong>
                        <p class="manageaccounts-feedback-message"></p>
                    </div>
                    <button type="button" class="manageaccounts-feedback-close" aria-label="Dismiss notification">×</button>
                </div>
                <div class="manageaccounts-toolbar">
                    <label class="manageaccounts-search" aria-label="Search accounts">
                        <span class="manageaccounts-search-icon" aria-hidden="true"></span>
                        <input id="manageaccounts-search" type="search" placeholder="Search by name or work email" autocomplete="off">
                    </label>

                    <div class="manageaccounts-toolbar-controls">
                        <div class="manageaccounts-role-filters" role="group" aria-label="Filter accounts by role">
                            <button type="button" class="manageaccounts-role-filter manageaccounts-role-filter--active" data-role-filter="all" aria-pressed="true">All</button>
                            <button type="button" class="manageaccounts-role-filter" data-role-filter="doctor" aria-pressed="false">Doctors</button>
                            <button type="button" class="manageaccounts-role-filter" data-role-filter="nurse" aria-pressed="false">Nurses</button>
                        </div>
                        <button type="button" class="manageaccounts-create-btn" id="manageaccounts-create-btn">Create New Account</button>
                    </div>
                </div>

                <div class="manageaccounts-table-wrap">
                    <table class="manageaccounts-table">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Work Email</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="manageaccounts-table-body">
                        </tbody>
                    </table>
                    <div id="manageaccounts-empty-state" class="manageaccounts-empty-state" hidden>
                        No matching accounts found.
                    </div>
                </div>
                <div class="manageaccounts-pagination" aria-live="polite">
                    <button type="button" id="manageaccounts-prev" class="manageaccounts-pagination-btn" aria-label="Previous page">Previous</button>
                    <span id="manageaccounts-page-info" class="manageaccounts-page-info">Page 1</span>
                    <button type="button" id="manageaccounts-next" class="manageaccounts-pagination-btn" aria-label="Next page">Next</button>
                </div>
            </section>
        </main>
    </div>

    <div class="manageaccounts-modal-backdrop" id="manageaccounts-modal-backdrop" aria-hidden="true">
        <div class="manageaccounts-modal" role="dialog" aria-modal="true" aria-labelledby="manageaccounts-modal-title">
            <button type="button" class="manageaccounts-modal-close" id="manageaccounts-modal-close" aria-label="Close modal">×</button>
            <h3 id="manageaccounts-modal-title">Create staff account</h3>
            <form id="manageaccounts-form" novalidate>
                <div class="manageaccounts-form-grid">
                    <label class="manageaccounts-field manageaccounts-field--full">
                        <span>Full Name</span>
                        <input name="full_name" type="text" placeholder="Enter full name" required>
                        <small class="manageaccounts-field-error" data-error-for="full_name"></small>
                    </label>

                    <label class="manageaccounts-field">
                        <span>Role</span>
                        <select name="role" required>
                            <option value="" selected disabled>Select role</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                        </select>
                        <small class="manageaccounts-field-error" data-error-for="role"></small>
                    </label>

                    <label class="manageaccounts-field">
                        <span>Specialty</span>
                        <input name="specialty" type="text" placeholder="Enter specialty" required>
                        <small class="manageaccounts-field-error" data-error-for="specialty"></small>
                    </label>

                    <label class="manageaccounts-field manageaccounts-field--full">
                        <span>Work Email</span>
                        <input name="work_email" type="email" placeholder="name@hospital.com" required>
                        <small class="manageaccounts-field-error" data-error-for="work_email"></small>
                    </label>

                    <label class="manageaccounts-field manageaccounts-field--full">
                        <span>License Number</span>
                        <input name="license_number" type="text" placeholder="Enter license number">
                        <small class="manageaccounts-field-hint">Required for doctors; optional for nurses.</small>
                        <small class="manageaccounts-field-error" data-error-for="license_number"></small>
                    </label>
                </div>

                <div class="manageaccounts-form-actions">
                    <button type="button" class="manageaccounts-cancel-btn" id="manageaccounts-cancel-btn">Cancel</button>
                    <button type="submit" class="manageaccounts-submit-btn">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <div class="manageaccounts-confirmation-backdrop" id="manageaccounts-confirmation-backdrop" aria-hidden="true" hidden>
        <section class="manageaccounts-confirmation" role="dialog" aria-modal="true"
            aria-labelledby="manageaccounts-confirmation-title" aria-describedby="manageaccounts-confirmation-message">
            <span class="manageaccounts-confirmation-icon" aria-hidden="true">✓</span>
            <h2 id="manageaccounts-confirmation-title"></h2>
            <p id="manageaccounts-confirmation-message"></p>
            <button type="button" id="manageaccounts-confirmation-close" class="manageaccounts-confirmation-button">OK</button>
        </section>
    </div>

    <script>
        window.manageAccountsConfig = {
            endpoint: 'manageAccountsController.php',
            csrfToken: <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
            initialResult: <?= json_encode($result, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
        };
    </script>
    <script src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
</body>
</html>
