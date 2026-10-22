<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Nurse Sign Up — Reddi PH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('public/css/signup.css')); ?>">
</head>

<body class="nurse-signup-page">
    <div class="nurse-signup-shell" aria-label="Nurse signup page">
        <aside class="nurse-signup-brand" aria-label="Nurse sign up branding">
            <div class="nurse-signup-icon" aria-hidden="true">
                <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 24L7.2 10.8C7.8 7.7 10.2 6 16 6C21.8 6 24.2 7.7 24.8 10.8L27 24C23.1 22.4 9 22.4 5 24Z"
                        fill="none" />
                    <path d="M16 11.5V18.5M12.5 15H19.5" />
                </svg>
            </div>

            <div class="nurse-signup-copy">
                <h2>Nurse</h2>
                <p>Join your hospital's team<br>on Pulse Alert.</p>
            </div>
        </aside>

        <main class="nurse-signup-form-wrap">
            <div class="nurse-signup-form-box">
                <span class="nurse-signup-kicker">Get started</span>
                <h1 class="nurse-signup-title">Create your account</h1>

                <?php if (!empty($errors)): ?>
                    <div class="nurse-signup-status error" role="alert">
                        <?php foreach ($errors as $message): ?>
                            <div><?php echo htmlspecialchars($message); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (!empty($successMessage)): ?>
                    <div class="nurse-signup-status success" role="status">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <form class="nurse-signup-form" id="nurseSignupForm" method="POST" action="nurse_signupController.php"
                    novalidate>
                    <div class="nurse-signup-row">
                        <div class="nurse-signup-field">
                            <label for="full_name">Full name</label>
                            <input type="text" id="full_name" name="full_name" placeholder="Jane Doe"
                                value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>" required>
                        </div>

                        <div class="nurse-signup-field">
                            <label for="hospital_name">Hospital name</label>
                            <select id="hospital_name" name="hospital_name" required>
                                <option value="" disabled <?php echo empty($formData['hospital_name']) ? 'selected' : ''; ?>>Select your hospital</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?php echo htmlspecialchars($hospital['name']); ?>" <?php echo ($formData['hospital_name'] ?? '') === $hospital['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($hospital['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="nurse-signup-field">
                        <label for="work_email">Work Email</label>
                        <input type="email" id="work_email" name="work_email" placeholder="name@hospital.gov.ph"
                            value="<?php echo htmlspecialchars($formData['work_email'] ?? ''); ?>" required>
                    </div>

                    <div class="nurse-signup-field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>

                    <div class="nurse-signup-field">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                            placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="nurse-signup-submit">Create account</button>
                </form>

                <div class="nurse-signup-footer">
                    <span>Already have an account? <a href="nurse_loginController.php">Log In Here</a></span>
                </div>
            </div>
        </main>
    </div>

    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>