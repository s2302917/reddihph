<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Doctor Sign Up — Reddi PH</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;0,800;0,900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet"
        href="<?php echo htmlspecialchars(assetUrl('public/css/signup.css')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/css/signup.css'); ?>">
</head>

<body class="doctor-signup-page">
    <div class="doctor-signup-shell" aria-label="Doctor signup page">
        <aside class="doctor-signup-brand" aria-label="Doctor sign up branding">
            <div class="doctor-signup-icon" aria-hidden="true">
                <svg viewBox="-1.5 0 24 24" fill="#ffffff" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="m6.53 8.098.14-.012c.053-.006.101-.025.141-.053l-.001.001c.134.462.298.948.503 1.457.263.666.522 1.213.812 1.741l-.04-.08c-.024.364-.053.738-.091 1.1-.018.223-.062.431-.129.627l.005-.018c-.012.005-.029 2.08-.029 2.08.001 1.353.938 2.486 2.198 2.787l.02.004c.057-.145.195-.246.357-.246h.574c.161.002.299.102.356.243l.001.003c1.283-.302 2.224-1.435 2.229-2.789v-.001s-.035-2.066-.053-2.08c-.055-.175-.099-.381-.122-.593l-.001-.015c-.035-.364-.058-.729-.091-1.1.247-.446.506-.992.734-1.555l.038-.106c.205-.509.364-.994.503-1.457.039.028.087.047.139.053h.001l.141.012c.17.018.32-.122.334-.339l.152-1.931c0-.001 0-.002 0-.002 0-.163-.122-.297-.279-.317h-.002-.017c.039-.281.061-.605.061-.934 0-.718-.106-1.412-.303-2.065l.013.051c-.577-1.266-1.721-2.185-3.099-2.442l-.026-.004c-.296-.061-.641-.102-.993-.112h-.009-.012c-.359.007-.704.047-1.038.118l.036-.006c-1.402.264-2.544 1.183-3.114 2.419l-.011.027c-.186.6-.293 1.29-.293 2.004 0 .333.023.661.068.981l-.004-.037c-.159.018-.282.151-.282.313v.007l.152 1.931c.014.222.166.356.33.338z" />
                    <path
                        d="m21.416 20.878c-.07-3.04-.374-3.728-.538-4.194-.065-.187-.118-1.451-2.206-2.271-1.28-.504-2.932-.514-4.33-1.105v1.644c-.003 1.768-1.269 3.239-2.944 3.56l-.023.004c-.031.182-.187.318-.374.32h-.018v1.24c0 1.212.982 2.194 2.194 2.194s2.194-.982 2.194-2.194v-.866c-.608-.091-1.069-.609-1.069-1.235 0-.689.559-1.248 1.248-1.248s1.248.559 1.248 1.248c0 .546-.351 1.01-.839 1.18l-.009.003v.918.047c0 1.532-1.242 2.774-2.774 2.774s-2.774-1.242-2.774-2.774c0-.017 0-.033 0-.05v.002-1.251c-.178-.012-.322-.146-.35-.318v-.002c-1.69-.329-2.95-1.795-2.954-3.556v-1.657c-1.404.603-3.066.615-4.353 1.12-2.094.819-2.142 2.08-2.206 2.27-.16.468-.468 1.153-.538 4.195-.012.4 0 1.013 1.206 1.549 2.626 1.03 6.009 1.35 9.344 1.58h.32c3.342-.228 6.72-.547 9.344-1.58 1.201-.533 1.212-1.142 1.201-1.546zm-14.681-1.24h-1.246v1.251h-.89v-1.247h-1.246v-.89h1.246v-1.246h.89v1.246h1.246z" />
                    <path
                        d="m16.225 17.965v-.001c0-.372-.301-.673-.673-.673s-.673.301-.673.673.301.673.673.673c.371 0 .672-.301.673-.672z" />
                </svg>
            </div>

            <div class="doctor-signup-copy">
                <h2>Doctor</h2>
                <p>Join the network and start<br>receiving case alerts.</p>
            </div>
        </aside>

        <main class="doctor-signup-form-wrap">
            <div class="doctor-signup-form-box">
                <span class="doctor-signup-kicker">Get started</span>
                <h1 class="doctor-signup-title">Create your account</h1>

                <?php if (!empty($errors)): ?>
                    <div class="doctor-signup-status error" role="alert">
                        <?php foreach ($errors as $message): ?>
                            <div><?php echo htmlspecialchars($message); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif (!empty($successMessage)): ?>
                    <div class="doctor-signup-status success" role="status">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <form class="doctor-signup-form" id="doctorSignupForm" method="POST"
                    action="doctor_signupController.php" novalidate>
                    <div class="doctor-signup-row">
                        <div class="doctor-signup-field">
                            <label for="doctor_full_name">Full name</label>
                            <input type="text" id="doctor_full_name" name="full_name" placeholder="Jane Doe"
                                value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>" required>
                        </div>

                        <div class="doctor-signup-field">
                            <label for="doctor_hospital_name">Hospital name</label>
                            <select id="doctor_hospital_name" name="hospital_name" required>
                                <option value="" disabled <?php echo empty($formData['hospital_name']) ? 'selected' : ''; ?>>Select your hospital</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?php echo htmlspecialchars($hospital['name']); ?>" <?php echo ($formData['hospital_name'] ?? '') === $hospital['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($hospital['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="doctor-signup-row">
                        <div class="doctor-signup-field">
                            <label for="doctor_work_email">Work Email</label>
                            <input type="email" id="doctor_work_email" name="work_email"
                                placeholder="name@hospital.gov.ph"
                                value="<?php echo htmlspecialchars($formData['work_email'] ?? ''); ?>" required>
                        </div>

                        <div class="doctor-signup-field">
                            <label for="doctor_license_number">License number</label>
                            <input type="text" id="doctor_license_number" name="license_number"
                                placeholder="License number"
                                value="<?php echo htmlspecialchars($formData['license_number'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="doctor-signup-field">
                        <label for="doctor_password">Password</label>
                        <input type="password" id="doctor_password" name="password" placeholder="Enter your password"
                            required>
                    </div>

                    <div class="doctor-signup-field">
                        <label for="doctor_confirm_password">Confirm Password</label>
                        <input type="password" id="doctor_confirm_password" name="confirm_password"
                            placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="doctor-signup-submit">Create account</button>
                </form>

                <div class="doctor-signup-footer">
                    <span>Already have an account? <a href="doctor_loginController.php">Log In Here</a></span>
                </div>
            </div>
        </main>
    </div>

    <script>window.APP_BASE_URL = <?php echo json_encode(rtrim(assetUrl(''), '/')); ?>;</script>
    <script
        src="<?php echo htmlspecialchars(assetUrl('public/js/script.js')); ?>?v=<?php echo filemtime(__DIR__ . '/../../public/js/script.js'); ?>"></script>
</body>

</html>