<div class="brand"><span>NURSE</span></div>

<nav class="main-nav" aria-label="Nurse navigation">
    <a class="<?= $currentPage === 'notified-cases' ? 'active' : '' ?>"
        href="<?= htmlspecialchars(assetUrl('app/controllers/NurseNotifiedCasesController.php')) ?>"
        <?= $currentPage === 'notified-cases' ? 'aria-current="page"' : '' ?>>
        <span class="nav-icon" aria-hidden="true">⌂</span>
        <span>Notified Cases</span>
    </a>
    <a class="<?= $currentPage === 'case-history' ? 'active' : '' ?>"
        href="<?= htmlspecialchars(assetUrl('app/controllers/NurseCaseHistoryController.php')) ?>"
        <?= $currentPage === 'case-history' ? 'aria-current="page"' : '' ?>>
        <span class="nav-icon" aria-hidden="true">◇</span>
        <span>Case History</span>
    </a>
    <a class="<?= $currentPage === 'schedule' ? 'active' : '' ?>"
        href="<?= htmlspecialchars(assetUrl('app/controllers/NurseScheduleController.php')) ?>"
        <?= $currentPage === 'schedule' ? 'aria-current="page"' : '' ?>>
        <span class="nav-icon" aria-hidden="true">▣</span>
        <span>Schedule</span>
    </a>
    <a class="<?= $currentPage === 'settings' ? 'active' : '' ?>"
        href="<?= htmlspecialchars(assetUrl('app/controllers/NurseSettingsController.php')) ?>"
        <?= $currentPage === 'settings' ? 'aria-current="page"' : '' ?>>
        <span class="nav-icon" aria-hidden="true">⚙</span>
        <span>Settings</span>
    </a>
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
