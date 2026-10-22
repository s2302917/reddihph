<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/NurseHandoffLog.css')) ?>?v=<?= filemtime(__DIR__ . '/../../public/css/NurseHandoffLog.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar nurse-sidebar">
        <?php require __DIR__ . '/partials/nurseSidebarView.php'; ?>
    </aside>
    <main class="content handoff-page">
        <header class="handoff-heading">
            <div class="handoff-heading-copy">
                <p class="handoff-hospital-name"><?= htmlspecialchars($hospitalName) ?></p>
                <h1>Handoff Log</h1>
            </div>
        </header>

        <section class="handoff-stats">
            <div><span>Total handoffs</span><strong><?= (int) $summary['totalHandoffs'] ?></strong></div>
            <div><span>Avg. prep time</span><strong><?= htmlspecialchars($summary['averagePrepTime']) ?></strong></div>
            <div><span>Beds ready</span><strong class="green-text"><?= (int) $summary['bedsReady'] ?></strong></div>
        </section>

        <div class="handoff-table" role="table" aria-label="Incident handoff log">
            <div class="handoff-row handoff-head" role="row">
                <b role="columnheader">TIME</b><b role="columnheader">PATIENT / CASE</b><b role="columnheader">BED</b><b role="columnheader">PREP TIME</b><b role="columnheader">STATUS</b>
            </div>
            <?php if (!$rows): ?>
                <div class="handoff-empty" role="row">No incidents have been sent to your account while on duty.</div>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <div class="handoff-row" role="row">
                    <span role="cell"><?= htmlspecialchars($row['time']) ?></span>
                    <div role="cell"><strong><?= htmlspecialchars($row['patient']) ?></strong><small><?= htmlspecialchars($row['case']) ?></small></div>
                    <span role="cell"><?= htmlspecialchars($row['bed']) ?></span>
                    <strong class="prep-time" role="cell"><?= htmlspecialchars($row['prep']) ?></strong>
                    <strong class="<?= $row['handedOff'] ? 'handed-off' : 'not-handed-off' ?>" role="cell"><?= $row['handedOff'] ? 'Handed off' : 'Not handed off' ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </main>
</div>
<?php $staffAlertRole = 'nurse'; require __DIR__ . '/partials/staffIncomingAlertView.php'; ?>
<script src="<?= htmlspecialchars(assetUrl('public/js/script.js')) ?>?v=<?= filemtime(__DIR__ . '/../../public/js/script.js') ?>"></script>
</body>
</html>
