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
            sent<br><small><?php echo htmlspecialchars($referral['receiving_hospital']); ?> has been notified</small>
        </div>
        <h1>Hospital Network</h1>
        <p>Real-time capacity across partner hospitals</p>
        <section class="hospitalnetwork-status-card"><span>Active Referral</span>
            <h2><?php echo htmlspecialchars($referral['incident_type']); ?><?php if ($referral['patient_gender'] && $referral['patient_age']): ?>
                    - <?php echo htmlspecialchars(ucfirst($referral['patient_gender'])); ?>,
                    <?php echo (int) $referral['patient_age']; ?><?php endif; ?></h2>
            <small><?php echo htmlspecialchars($referral['sending_hospital']); ?> &rarr;
                <?php echo htmlspecialchars($referral['receiving_hospital']); ?></small>
            <div class="hospitalnetwork-status-steps"><b>✓</b><i></i><b
                    class="hospitalnetwork-status-steps-active">◷</b><i></i><b>▣</b><i></i><b>⚑</b></div>
            <div class="hospitalnetwork-status-note">Waiting for
                <?php echo htmlspecialchars($referral['receiving_hospital']); ?> to confirm bed and doctor availability
                <strong>Sent just now</strong></div>
        </section>
    </main>
</body>

</html>