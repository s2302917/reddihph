<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Hospital Admin Sign Up — Reddi PH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('public/css/signup.css')); ?>">
</head>

<body class="hospitaladmin-signup-page">
    <div class="hospitaladmin-signup-shell" aria-label="Hospital admin signup page">
        <aside class="hospitaladmin-signup-brand" aria-label="Hospital admin sign up branding">
            <div class="hospitaladmin-signup-icon" aria-hidden="true">
                <svg viewBox="2 2 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ffffff"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 4C9.79 4 8 5.79 8 8C8 10.21 9.79 12 12 12C14.21 12 16 10.21 16 8C16 5.79 14.21 4 12 4Z"
                        fill="none"></path>
                    <path d="M4 18C4 15.34 9.33 14 12 14C14.67 14 20 15.34 20 18V20H4V18Z" fill="none"></path>
                </svg>
            </div>

            <div class="hospitaladmin-signup-copy">
                <h2>Hospital<br>Admin</h2>
                <p>Register your hospital and<br>manage your team.</p>
            </div>
        </aside>

        <main class="hospitaladmin-signup-form-wrap">
            <div class="hospitaladmin-signup-form-box">
                <span class="hospitaladmin-signup-kicker">Get started</span>
                <h1 class="hospitaladmin-signup-title">Create your account</h1>

                <?php if (!empty($errors)): ?>
                    <div class="hospitaladmin-signup-status error" role="alert">
                        <?php foreach ($errors as $message): ?>
                            <div><?php echo htmlspecialchars($message); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (!empty($successMessage)): ?>
                    <div class="hospitaladmin-signup-status success" role="status">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <form class="hospitaladmin-signup-form" id="hospitaladminSignupForm" method="POST"
                    action="hospital_admin_signupController.php" novalidate>
                    <div class="hospitaladmin-signup-row">
                        <div class="hospitaladmin-signup-field">
                            <label for="admin_full_name">Full name</label>
                            <input type="text" id="admin_full_name" name="full_name" placeholder="Jane Doe"
                                value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>" required>
                        </div>

                        <div class="hospitaladmin-signup-field">
                            <label for="admin_hospital_name">Hospital name</label>
                            <select id="admin_hospital_name" name="hospital_name" required>
                                <option value="" disabled <?php echo empty($formData['hospital_name']) ? 'selected' : ''; ?>>Select your hospital</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?php echo htmlspecialchars($hospital['name']); ?>" <?php echo ($formData['hospital_name'] ?? '') === $hospital['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($hospital['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="hospitaladmin-signup-field">
                        <label for="admin_work_email">Work Email</label>
                        <input type="email" id="admin_work_email" name="work_email" placeholder="name@hospital.gov.ph"
                            value="<?php echo htmlspecialchars($formData['work_email'] ?? ''); ?>" required>
                    </div>

                    <div class="hospitaladmin-signup-field">
                        <label for="admin_password">Password</label>
                        <input type="password" id="admin_password" name="password" placeholder="Enter your password"
                            required>
                    </div>

                    <div class="hospitaladmin-signup-field">
                        <label for="admin_confirm_password">Confirm Password</label>
                        <input type="password" id="admin_confirm_password" name="confirm_password"
                            placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="hospitaladmin-signup-submit">Create account</button>
                </form>

                <div class="hospitaladmin-signup-footer">
                    <span>Already have an account? <a href="hospital_admin_loginController.php">Log In Here</a></span>
                </div>
            </div>
        </main>
    </div>

    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>