<?php
$statusMessages = [
    'pending' => 'The receiving hospital has been notified and is reviewing this referral.',
    'accepted' => 'The receiving hospital accepted this referral.',
    'declined' => 'The receiving hospital declined this referral.',
    'completed' => 'This referral has been completed.',
];
$statusMessage = $statusMessages[$referral['status']] ?? 'Referral status recorded.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Status - Pulse Alert</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/hospitalnetworkView.css')); ?>?v=20260916-6">
</head>

<body class="hospitalnetwork-status-body">
    <main class="hospitalnetwork-status-main">
        <a class="hospitalnetwork-status-back" href="hospitalnetworkController.php"
            aria-label="Back to Hospital Network">&#8249;</a>
        <div class="hospitalnetwork-status-toast">Referral
            <?= htmlspecialchars(ucfirst((string) $referral['status'])) ?><br>
            <small><?= htmlspecialchars($referral['receiving_hospital']) ?></small>
        </div>
        <h1>Hospital Network</h1>
        <p>Real-time capacity across partner hospitals</p>
        <section class="hospitalnetwork-status-card"><span>Referral Record #<?= (int) $referral['id'] ?></span>
            <h2><?php echo htmlspecialchars($referral['incident_type']); ?><?php if ($referral['patient_gender'] && $referral['patient_age']): ?>
                    - <?php echo htmlspecialchars(ucfirst($referral['patient_gender'])); ?>,
                    <?php echo (int) $referral['patient_age']; ?><?php endif; ?></h2>
            <small><?php echo htmlspecialchars($referral['sending_hospital']); ?> &rarr;
                <?php echo htmlspecialchars($referral['receiving_hospital']); ?></small>
            <p><?= htmlspecialchars((string) ($referral['patient_name'] ?: 'Patient details unavailable')) ?></p>
            <p><?= htmlspecialchars((string) $referral['referral_reason']) ?></p>
            <p>Referred <?= htmlspecialchars((string) $referral['created_at']) ?></p>
            <p>Incident #<?= (int) $referral['incident_id'] ?> · <?= htmlspecialchars((string) $referral['incident_status']) ?></p>
            <div class="hospitalnetwork-status-steps"><b>✓</b><i></i><b
                    class="hospitalnetwork-status-steps-active">◷</b><i></i><b>▣</b><i></i><b>⚑</b></div>
            <div class="hospitalnetwork-status-note">
                <?= htmlspecialchars($statusMessage) ?>
                <strong><?= htmlspecialchars((string) $referral['updated_at']) ?></strong></div>
        </section>
    </main>
</body>

</html>