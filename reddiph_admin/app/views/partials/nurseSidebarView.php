<div class="brand"><span>NURSE</span></div>

<nav class="main-nav" aria-label="Nurse navigation">
    <a class="<?= $currentPage === 'notified-cases' ? 'active' : '' ?>"
        href="<?= htmlspecialchars(assetUrl('app/controllers/NurseNotifiedCasesController.php')) ?>"
        <?= $currentPage === 'notified-cases' ? 'aria-current="page"' : '' ?>>Prep task</a>
    <a class="<?= $currentPage === 'bed-status' ? 'active' : '' ?>"
        href="<?= htmlspecialchars(assetUrl('app/controllers/NurseBedStatusController.php')) ?>"
        <?= $currentPage === 'bed-status' ? 'aria-current="page"' : '' ?>>Bed Status</a>
    <a class="<?= $currentPage === 'handoff-log' ? 'active' : '' ?>"
        href="<?= htmlspecialchars(assetUrl('app/controllers/NurseHandoffLogController.php')) ?>"
        <?= $currentPage === 'handoff-log' ? 'aria-current="page"' : '' ?>>Handoff Log</a>
    <a class="<?= $currentPage === 'schedule' ? 'active' : '' ?>"
        href="<?= htmlspecialchars(assetUrl('app/controllers/NurseScheduleController.php')) ?>"
        <?= $currentPage === 'schedule' ? 'aria-current="page"' : '' ?>>Schedule</a>
    <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>"
        href="<?= htmlspecialchars(assetUrl('app/controllers/NurseSettingsController.php')) ?>"
        <?= $currentPage === 'settings' ? 'aria-current="page"' : '' ?>>Settings</a>
</nav>

<div class="nurse-sidebar-profile">
    <span class="nurse-sidebar-avatar" aria-hidden="true">
        <?php if (!empty($sidebarNurse['profile_image_url'])): ?>
            <img src="<?= htmlspecialchars($sidebarNurse['profile_image_url']) ?>" alt="">
        <?php else: ?>
            <?= htmlspecialchars($sidebarNurse['initials']) ?>
        <?php endif; ?>
    </span>
    <span class="nurse-sidebar-copy">
        <strong><?= htmlspecialchars($sidebarNurse['full_name']) ?></strong>
        <span>Nurse</span>
    </span>
</div>
