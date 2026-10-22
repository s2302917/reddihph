<?php

$_SESSION['staff_alert_csrf_token'] ??= bin2hex(random_bytes(32));
?>
<link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/StaffIncomingAlerts.css')) ?>?v=<?= filemtime(__DIR__ . '/../../../public/css/StaffIncomingAlerts.css') ?>">
<div class="staff-alert-backdrop" id="staff-alert-backdrop" hidden>
    <section class="staff-alert-dialog" role="alertdialog" aria-modal="true"
        aria-labelledby="staff-alert-heading" aria-describedby="staff-alert-message">
        <span class="staff-alert-kicker">New notification</span>
        <h2 id="staff-alert-heading">Incoming incident</h2>
        <p class="staff-alert-incident" id="staff-alert-incident"></p>
        <dl class="staff-alert-details">
            <div><dt>Status</dt><dd id="staff-alert-status"></dd></div>
            <div><dt>Patient</dt><dd id="staff-alert-patient"></dd></div>
            <div><dt>Location</dt><dd id="staff-alert-location"></dd></div>
            <div><dt>Hospital</dt><dd id="staff-alert-hospital"></dd></div>
            <div><dt>Reported</dt><dd id="staff-alert-reported"></dd></div>
        </dl>
        <p class="staff-alert-message" id="staff-alert-message">Review the incoming incident details.</p>
        <p class="staff-alert-error" id="staff-alert-error" role="alert" hidden></p>
        <button type="button" id="staff-alert-close">Close</button>
    </section>
</div>
<script>
    window.staffIncomingAlertsEndpoint = <?= json_encode(assetUrl('app/controllers/StaffIncomingAlertsController.php?role=' . rawurlencode($staffAlertRole))) ?>;
    window.staffIncomingAlertsCsrfToken = <?= json_encode($_SESSION['staff_alert_csrf_token']) ?>;
</script>
