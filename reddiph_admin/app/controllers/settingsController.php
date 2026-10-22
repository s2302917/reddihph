<?php
/**
 * ============================================================
 * settingsController.php
 * ------------------------------------------------------------
 * REDDI PH / PULSE ALERT
 *
 * CONTROLLER
 *
 * Responsibilities:
 * - Load Settings Model
 * - Determine administrator
 * - Retrieve settings data
 * - Process settings updates
 * - Load Settings View
 *
 * No HTML layout logic.
 * No CSS.
 * No JavaScript.
 * No direct SQL queries.
 * ============================================================
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/settingsModel.php';

requireAuthenticated('hospital_admin');


class settingsController
{
    private settingsModel $model;


    /**
     * --------------------------------------------------------
     * Constructor
     * --------------------------------------------------------
     */
    public function __construct()
    {
        $this->model = new settingsModel();
    }


    /**
     * --------------------------------------------------------
     * Display Settings page
     * --------------------------------------------------------
     */
    public function index(): void
    {
        /*
         * ----------------------------------------------------
         * TEMPORARY ADMIN ID
         * ----------------------------------------------------
         *
         * Replace this with the authenticated admin ID from
         * your existing login/session system.
         *
         * Example:
         *
         * $adminId = (int) $_SESSION['admin_id'];
         *
         * Do NOT leave a hardcoded ID in the final production
         * authentication flow.
         */

        $adminId = $this->getLoggedInUserId();


        $admin = $this->model->getAdmin($adminId);


        /*
         * ----------------------------------------------------
         * If administrator does not exist
         * ----------------------------------------------------
         */

        if (!$admin) {

            http_response_code(404);

            exit('Administrator account not found.');

        }


        /*
         * ----------------------------------------------------
         * Hospital
         * ----------------------------------------------------
         */

        $hospitalId = (int) ($admin['hospital_id'] ?? 0);
        $hospital = $hospitalId > 0 ? $this->model->getHospital($hospitalId) : null;
        $canManageHospital = ($admin['role'] ?? '') === 'hospital_admin';

        if (!$hospital && $canManageHospital) {
            http_response_code(404);
            exit('Hospital profile not found.');
        }
        $hospital = $hospital ?: [
            'hospital_name' => $admin['hospital_name'] ?? 'Hospital profile unavailable',
            'address' => null,
            'latitude' => null,
            'longitude' => null,
        ];


        /*
         * ----------------------------------------------------
         * Values passed to the View
         * ----------------------------------------------------
         */

        $coordinatorName =
            $admin['full_name'];

        $coordinatorRole =
            ucwords(str_replace('_', ' ', $admin['role'] ?? 'hospital_admin'));

        $coordinatorInitials = $this->initialsFromName($coordinatorName);
        $coordinatorImage = userProfileImageUrl($admin['profile_image'] ?? null);
        $_SESSION['profile_image'] = $admin['profile_image'] ?? null;

        $accountEmail =
            $admin['work_email'];

        $accountPhone =
            $admin['phone_number'] ?? '';

        $hospitalName =
            $hospital['hospital_name'];

        $hospitalLocation =
            $hospital['address'] ?? 'Location not provided';

        $hospitalLatitude = $hospital['latitude'] ?? null;
        $hospitalLongitude = $hospital['longitude'] ?? null;
        $csrfToken = $_SESSION['settings_csrf_token'] ??= bin2hex(random_bytes(32));
        $settingsMessage = $_SESSION['settings_message'] ?? null;
        $settingsMessageType = $_SESSION['settings_message_type'] ?? 'success';
        unset($_SESSION['settings_message'], $_SESSION['settings_message_type']);


        /*
         * ----------------------------------------------------
         * Load View
         * ----------------------------------------------------
         */

        require __DIR__ . '/../views/settingsView.php';
    }


    /**
     * --------------------------------------------------------
     * Update Settings
     * --------------------------------------------------------
     */
    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . assetUrl('app/controllers/settingsController.php'), true, 303);
            exit;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['settings_csrf_token']) || !hash_equals((string) $_SESSION['settings_csrf_token'], (string) ($_POST['csrf_token'] ?? ''))) {
            $this->jsonResponse(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
        }

        $adminId = $this->getLoggedInUserId();
        $action = (string) ($_POST['action'] ?? 'save_settings');
        if ($action === 'upload_avatar') {
            $this->uploadAvatar($adminId);
        }
        if ($action === 'change_password') {
            $this->changePassword($adminId);
        }
        if ($action !== 'save_settings') {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid settings action.'], 422);
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
        $hospitalName = trim((string) ($_POST['hospital_name'] ?? ''));
        $latitudeValue = trim((string) ($_POST['latitude'] ?? ''));
        $longitudeValue = trim((string) ($_POST['longitude'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $this->redirectWithMessage('Enter a valid email address (up to 150 characters).', 'error');
        }
        if ($phoneNumber !== '' && !preg_match('/^[0-9+().\s-]{7,32}$/', $phoneNumber)) {
            $this->redirectWithMessage('Enter a valid phone number using 7 to 32 digits or standard phone punctuation.', 'error');
        }

        $admin = $this->model->getAdmin($adminId);
        if (!$admin) {
            http_response_code(404);
            exit('Account not found.');
        }

        $canManageHospital = $admin['role'] === 'hospital_admin';
        $latitude = null;
        $longitude = null;
        if ($canManageHospital) {
            if ($hospitalName === '' || strlen($hospitalName) > 150) {
                $this->redirectWithMessage('Hospital name is required and must be 150 characters or fewer.', 'error');
            }
            if ($latitudeValue !== '' || $longitudeValue !== '') {
                if (!is_numeric($latitudeValue) || !is_numeric($longitudeValue)) {
                    $this->redirectWithMessage('Enter both latitude and longitude, or leave both blank.', 'error');
                }
                $latitude = (float) $latitudeValue;
                $longitude = (float) $longitudeValue;
                if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                    $this->redirectWithMessage('Coordinates are outside the valid latitude/longitude range.', 'error');
                }
            }
        } else {
            $hospitalName = null;
        }

        try {
            $this->model->updateSettings(
                $adminId,
                $email,
                $phoneNumber !== '' ? $phoneNumber : null,
                $hospitalName,
                $latitude,
                $longitude
            );
            $_SESSION['work_email'] = $email;
            if ($hospitalName !== null) {
                $_SESSION['hospital_name'] = $hospitalName;
            }
            $this->redirectWithMessage('Settings saved successfully.', 'success');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $this->redirectWithMessage('That email address is already assigned to another account.', 'error');
            }
            error_log('[SettingsController] Profile update failed: ' . $exception->getMessage());
            $this->redirectWithMessage('Settings could not be saved. Please try again.', 'error');
        } catch (Throwable $exception) {
            error_log('[SettingsController] Profile update failed: ' . $exception->getMessage());
            $this->redirectWithMessage('Settings could not be saved. Please try again.', 'error');
        }
    }


    /**
     * --------------------------------------------------------
     * Get currently logged-in administrator
     * --------------------------------------------------------
     *
     * This method is intentionally isolated so your existing
     * authentication/session implementation can be connected
     * here without changing the Model or View.
     */
    private function getLoggedInUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || !in_array($_SESSION['role'] ?? null, ['hospital_admin', 'doctor', 'nurse'], true)) {
            http_response_code(403);
            exit('Authenticated hospital staff account required.');
        }
        return $userId;
    }

    private function changePassword(int $adminId): void
    {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['confirm_password'] ?? '');
        if ($currentPassword === '' || strlen($newPassword) < 8 || $newPassword !== $confirmation) {
            $this->jsonResponse(['success' => false, 'message' => 'Enter your current password, use at least 8 characters, and make both new passwords match.'], 422);
        }

        try {
            if (!$this->model->changePassword($adminId, $currentPassword, $newPassword)) {
                $this->jsonResponse(['success' => false, 'message' => 'The current password is incorrect.'], 422);
            }
            session_regenerate_id(true);
            $_SESSION['must_change_password'] = false;
            $this->jsonResponse(['success' => true, 'message' => 'Password changed successfully.']);
        } catch (Throwable $exception) {
            error_log('[SettingsController] Password change failed: ' . $exception->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Password could not be changed. Please try again.'], 500);
        }
    }

    private function uploadAvatar(int $adminId): void
    {
        $upload = $_FILES['profile_image'] ?? null;
        if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
            $this->jsonResponse(['success' => false, 'message' => 'Choose an image smaller than 5 MB.'], 422);
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mimeType]) || !is_uploaded_file($upload['tmp_name'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Use a valid JPG, PNG, or WebP image.'], 422);
        }

        $directory = dirname(__DIR__, 2) . '/assets/images/profiles';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $this->jsonResponse(['success' => false, 'message' => 'The profile image folder is unavailable.'], 500);
        }

        try {
            $fileName = 'profile-' . $adminId . '-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
            $target = $directory . '/' . $fileName;
            if (!move_uploaded_file($upload['tmp_name'], $target)) {
                $this->jsonResponse(['success' => false, 'message' => 'The image could not be saved.'], 500);
            }

            $previousImage = $this->model->updateProfileImage($adminId, $fileName);
            $_SESSION['profile_image'] = $fileName;
            if ($previousImage && preg_match('/^profile-' . $adminId . '-[a-f0-9]{32}\.(jpg|png|webp)$/', basename($previousImage))) {
                $previousPath = $directory . '/' . basename($previousImage);
                if (is_file($previousPath)) {
                    unlink($previousPath);
                }
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Profile photo updated.',
                'imageUrl' => userProfileImageUrl($fileName),
            ]);
        } catch (Throwable $exception) {
            if (isset($target) && is_file($target)) {
                unlink($target);
            }
            error_log('[SettingsController] Profile photo update failed: ' . $exception->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Profile photo could not be saved. Please try again.'], 500);
        }
    }

    private function redirectWithMessage(string $message, string $type): void
    {
        $_SESSION['settings_message'] = $message;
        $_SESSION['settings_message_type'] = $type;
        header('Location: ' . assetUrl('app/controllers/settingsController.php'), true, 303);
        exit;
    }

    private function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }

    private function initialsFromName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return 'HA';
        }

        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        return $initials;
    }
}


/**
 * ============================================================
 * REQUEST HANDLER
 * ============================================================
 */

$controller = new settingsController();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $controller->update();

} else {

    $controller->index();
}