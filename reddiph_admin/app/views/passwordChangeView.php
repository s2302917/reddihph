<?php

$displayMessage = $message ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create a new ReddiPH password</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/forgotpass.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('public/css/passwordChange.css')) ?>">
</head>

<body class="password-change-body">
    <main class="password-change-page">
        <section class="password-change-overlay" aria-hidden="false">
            <div class="password-change-drawer" role="dialog" aria-modal="true" aria-labelledby="password-change-title">
                <span class="password-change-eyebrow">SECURE YOUR ACCOUNT</span>
                <h1 id="password-change-title">Create a new password</h1>
                <p class="password-change-copy">Your temporary password worked. Set a new password to continue to
                    ReddiPH.</p>

                <?php if ($displayMessage !== ''): ?>
                    <p class="password-change-message" role="alert">
                        <?= htmlspecialchars($displayMessage) ?>
                    </p>
                <?php endif; ?>

                <form method="post"
                    action="passwordChangeController.php?role=<?= rawurlencode((string) ($_SESSION['role'] ?? '')) ?>"
                    class="password-change-form" autocomplete="off">
                    <label for="new-password">New password</label>
                    <input id="new-password" name="new_password" type="password" minlength="8"
                        autocomplete="new-password" required>

                    <label for="confirm-password">Confirm new password</label>
                    <input id="confirm-password" name="confirm_password" type="password" minlength="8"
                        autocomplete="new-password" required>

                    <button type="submit">Submit</button>
                </form>
            </div>
        </section>
    </main>
</body>

</html>