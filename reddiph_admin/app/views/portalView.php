<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Coordination Portal — Reddiph</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,900;1,400&family=Libre+Caslon+Text:ital@0;1&display=swap"
        rel="stylesheet">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/portal.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/portal.css'); ?>">
</head>

<body>

    <div class="portal">

        <header class="portal__topbar">
            <div class="brand-logo" aria-label="Reddi PH">
                <img src="<?php echo htmlspecialchars(assetUrl('assets/images/logo.png')); ?>" alt="Reddi PH">
            </div>
        </header>

        <main class="portal__main">
            <h1 class="portal__title">Emergency Coordination Portal</h1>
            <?php if (($_GET['reason'] ?? '') === 'account_suspended'): ?>
                <p class="portal__notice" role="alert">
                    Your account was suspended by an administrator. You have been logged out.
                </p>
            <?php elseif (($_GET['reason'] ?? '') === 'account_unavailable'): ?>
                <p class="portal__notice" role="alert">
                    Your account is no longer available. Please contact an administrator.
                </p>
            <?php endif; ?>

            <div class="role-grid">
                <?php foreach ($roles as $role): ?>
                    <a href="<?php echo htmlspecialchars($role['href']); ?>"
                        class="role-card <?php echo htmlspecialchars($role['cardClass']); ?>"
                        data-role-id="<?php echo htmlspecialchars($role['id']); ?>">
                        <img class="role-card__icon"
                            src="<?php echo htmlspecialchars(assetUrl($role['icon'])); ?>?v=<?php echo filemtime(__DIR__ . '/../../' . $role['icon']); ?>"
                            alt="" aria-hidden="true">
                        <div class="role-card__body">
                            <h2 class="role-card__title"><?php echo htmlspecialchars($role['title']); ?></h2>
                            <p class="role-card__subtitle"><?php echo htmlspecialchars($role['subtitle']); ?></p>
                        </div>
                        <span class="role-card__arrow" aria-hidden="true">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 12H19M19 12L12 5M19 12L12 19" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </main>

    </div>

    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>