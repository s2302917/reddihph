<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Admin Login — Reddi PH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900;1,400&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('public/css/login.css')); ?>">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/forgotpass.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/forgotpass.css'); ?>">
</head>

<body>
    <div class="hospitaladmin-login-container">
        <!-- Left Panel: Role Identity & Description -->
        <aside class="hospitaladmin-login-panel">
            <div class="hospitaladmin-login-panel__top">
                <button type="button" class="hospitaladmin-login-back-btn" id="hospitaladminBackBtn"
                    aria-label="Go back">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19 12H5M5 12L12 19M5 12L12 5" stroke="currentColor" stroke-width="2.2"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>

            <div class="hospitaladmin-login-panel__bottom">
                <!-- User / Admin outline badge icon -->
                <div class="hospitaladmin-login-panel__icon" aria-hidden="true">
                    <svg viewBox="2 2 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ffffff"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path
                            d="M12 4C9.79 4 8 5.79 8 8C8 10.21 9.79 12 12 12C14.21 12 16 10.21 16 8C16 5.79 14.21 4 12 4Z"
                            fill="none"></path>
                        <path d="M4 18C4 15.34 9.33 14 12 14C14.67 14 20 15.34 20 18V20H4V18Z" fill="none"></path>
                    </svg>
                </div>

                <h1 class="hospitaladmin-login-panel__title">Hospital Admin</h1>
                <p class="hospitaladmin-login-panel__subtitle">Manage beds and see incoming patients in real time.</p>
            </div>
        </aside>

        <!-- Right Panel: Login Form -->
        <main class="hospitaladmin-login-content">
            <div class="hospitaladmin-login-form-wrapper">
                <header class="hospitaladmin-login-header">
                    <span class="hospitaladmin-login-eyebrow">WELCOME BACK</span>
                    <h2 class="hospitaladmin-login-main-title">Log in to your account</h2>
                </header>

                <?php if (!empty($errors)): ?>
                    <div class="hospitaladmin-login-errors" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form class="hospitaladmin-login-form" id="hospitaladminLoginForm"
                    action="../controllers/hospital_admin_loginController.php" method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="admin_login">
                    <div class="hospitaladmin-form-group">
                        <label class="hospitaladmin-form-label" for="hospitaladmin-email">Work email</label>
                        <input type="email" id="hospitaladmin-email" name="work_email" class="hospitaladmin-form-input"
                            placeholder="name@hospital.gov.ph" required autocomplete="off">
                    </div>

                    <div class="hospitaladmin-form-group">
                        <label class="hospitaladmin-form-label" for="hospitaladmin-password">Password</label>
                        <input type="password" id="hospitaladmin-password" name="password"
                            class="hospitaladmin-form-input" placeholder="Enter your password" required
                            autocomplete="new-password">
                    </div>

                    <button type="submit" class="hospitaladmin-login-submit-btn">Log in</button>

                    <div class="hospitaladmin-forgot-password-wrap">
                        <a href="javascript:void(0)" class="hospitaladmin-forgot-password-link"
                            id="hospitaladminForgotPassLink">Forgot password?</a>
                    </div>
                </form>

                <footer class="hospitaladmin-login-footer">
                    <span>Not registered? </span><a href="hospital_admin_signupController.php"
                        class="hospitaladmin-signup-link">Sign up here</a>
                </footer>
            </div>
        </main>
    </div>

    <!-- =========================================================
         ADMIN FORGOT PASSWORD POPUP MODAL (Glassmorphic)
         ========================================================= -->
    <div class="adminforgotpass-overlay<?php echo !empty($resetStatus) ? ' is-open' : ''; ?>" id="adminForgotPassModal"
        aria-hidden="<?php echo !empty($resetStatus) ? 'false' : 'true'; ?>" role="dialog" aria-modal="true"
        aria-labelledby="adminForgotPassTitle">
        <div class="adminforgotpass-card">
            <button type="button" class="adminforgotpass-back-btn" id="adminForgotPassBackBtn"
                aria-label="Close forgot password modal">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 12H5M5 12L12 19M5 12L12 5" stroke="currentColor" stroke-width="2.2"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <div class="adminforgotpass-logo">
                <img src="<?php echo htmlspecialchars(assetUrl('assets/images/logo.png')); ?>" alt="ReddiPH">
            </div>

            <span class="adminforgotpass-eyebrow">RESET ACCESS</span>
            <h3 class="adminforgotpass-title" id="adminForgotPassTitle">Forgot password</h3>
            <p class="adminforgotpass-subtitle">Enter the email tied to your hospital admin account and we'll send a
                temporary password.</p>

            <?php if (!empty($resetStatus)): ?>
                <p class="forgotpass-status" role="alert">
                    <?php echo htmlspecialchars($resetStatus['message']); ?>
                </p>
            <?php endif; ?>

            <form class="adminforgotpass-form" id="adminForgotPassForm"
                action="../controllers/hospital_admin_loginController.php" method="POST">
                <input type="hidden" name="action" value="admin_forgot_password">

                <div class="adminforgotpass-form-group">
                    <label class="adminforgotpass-form-label" for="adminforgotpass-email">Work email</label>
                    <input type="email" id="adminforgotpass-email" name="email" class="adminforgotpass-form-input"
                        placeholder="name@hospital.gov.ph" required autocomplete="email">
                </div>

                <button type="submit" class="adminforgotpass-submit-btn">Send</button>
            </form>
        </div>
    </div>

    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>