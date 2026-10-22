<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nurse Login — Reddi PH</title>
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
    <div class="nurse-login-container">
        <!-- Left Panel: Role Identity & Description -->
        <aside class="nurse-login-panel">
            <div class="nurse-login-panel__top">
                <button type="button" class="nurse-login-back-btn" id="nurseBackBtn" aria-label="Go back">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19 12H5M5 12L12 19M5 12L12 5" stroke="currentColor" stroke-width="2.2"
                            stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>

            <div class="nurse-login-panel__bottom">
                <!-- Nurse cap badge icon -->
                <div class="nurse-login-panel__icon" aria-hidden="true">
                    <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ffffff"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <!-- Nurse Cap Outline -->
                        <path d="M5 24L7 11C7.5 8 10 7 16 7C22 7 24.5 8 25 11L27 24C23 22.5 9 22.5 5 24Z" fill="none" />
                        <!-- Medical Cross inside Cap -->
                        <path d="M16 11V17M13 14H19" stroke-width="2" />
                    </svg>
                </div>

                <h1 class="nurse-login-panel__title">Nurse</h1>
                <p class="nurse-login-panel__subtitle">Help prep beds and coordinate patient handoffs.</p>
            </div>
        </aside>

        <!-- Right Panel: Login Form -->
        <main class="nurse-login-content">
            <div class="nurse-login-form-wrapper">
                <header class="nurse-login-header">
                    <span class="nurse-login-eyebrow">WELCOME BACK</span>
                    <h2 class="nurse-login-main-title">Log in to your account</h2>
                </header>

                <form class="nurse-login-form" id="nurseLoginForm" action="../controllers/nurse_loginController.php"
                    method="POST" autocomplete="off">
                    <input type="hidden" name="action" value="nurse_login">
                    <div class="nurse-form-group">
                        <label class="nurse-form-label" for="nurse-email">Nurse ID / Email</label>
                        <input type="text" id="nurse-email" name="work_email" class="nurse-form-input"
                            placeholder="name@hospital.gov.ph" required autocomplete="off">
                    </div>

                    <div class="nurse-form-group">
                        <label class="nurse-form-label" for="nurse-password">Password</label>
                        <input type="password" id="nurse-password" name="password" class="nurse-form-input"
                            placeholder="Enter your password" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="nurse-login-submit-btn">Log in</button>

                    <div class="nurse-forgot-password-wrap">
                        <a href="javascript:void(0)" class="nurse-forgot-password-link" id="nurseForgotPassLink">Forgot
                            password?</a>
                    </div>
                </form>

                <footer class="nurse-login-footer">
                    <span>Not registered? </span><a href="../controllers/nurse_signupController.php"
                        class="nurse-signup-link">Sign up here</a>
                </footer>
            </div>
        </main>
    </div>

    <div class="nurseforgotpass-overlay<?php echo !empty($resetStatus) ? ' is-open' : ''; ?>" id="nurseForgotPassModal"
        aria-hidden="<?php echo !empty($resetStatus) ? 'false' : 'true'; ?>" role="dialog" aria-modal="true"
        aria-labelledby="nurseForgotPassTitle">
        <div class="nurseforgotpass-card">
            <button type="button" class="nurseforgotpass-back-btn" id="nurseForgotPassBackBtn"
                aria-label="Close forgot password modal">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19 12H5M5 12L12 19M5 12L12 5" stroke="currentColor" stroke-width="2.2"
                        stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>

            <div class="nurseforgotpass-logo">
                <img src="<?php echo htmlspecialchars(assetUrl('assets/images/logo.png')); ?>" alt="ReddiPH">
            </div>

            <span class="nurseforgotpass-eyebrow">RESET ACCESS</span>
            <h3 class="nurseforgotpass-title" id="nurseForgotPassTitle">Forgot password</h3>
            <p class="nurseforgotpass-subtitle">Enter the email tied to your nurse account and we'll send a reset link.
            </p>

            <?php if (!empty($resetStatus)): ?>
                <p class="forgotpass-status" role="alert">
                    <?php echo htmlspecialchars($resetStatus['message']); ?>
                </p>
            <?php endif; ?>

            <form class="nurseforgotpass-form" id="nurseForgotPassForm"
                action="../controllers/nurse_loginController.php" method="POST">
                <input type="hidden" name="action" value="nurse_forgot_password">

                <div class="nurseforgotpass-form-group">
                    <label class="nurseforgotpass-form-label" for="nurseforgotpass-email">Work email</label>
                    <input type="email" id="nurseforgotpass-email" name="email" class="nurseforgotpass-form-input"
                        placeholder="name@hospital.gov.ph" required autocomplete="email">
                </div>

                <button type="submit" class="nurseforgotpass-submit-btn">Send</button>
            </form>
        </div>
    </div>

    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>