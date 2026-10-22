<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/NurseSettingsModel.php';
require_once __DIR__ . '/../models/NurseNotifiedCasesModel.php';

requireAuthenticated('nurse');

class NurseSettingsController
{
    public function index(): void
    {
        $nurseId = (int) $_SESSION['user_id'];
        $pageTitle = 'Settings';
        $currentPage = 'settings';
        $nurse = NurseSettingsModel::profile($nurseId);
        if (!$nurse) {
            http_response_code(404);
            exit('Nurse account not found.');
        }
        $sidebarNurse = NurseNotifiedCasesModel::profile($nurseId);
        if (!$sidebarNurse) {
            http_response_code(404);
            exit('Nurse profile not found.');
        }
        $_SESSION['nurse_settings_csrf_token'] ??= bin2hex(random_bytes(32));

        require __DIR__ . '/../views/NurseSettingsView.php';
    }

    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
        }

        $token = (string) ($_POST['csrf_token'] ?? '');
        if (
            empty($_SESSION['nurse_settings_csrf_token']) ||
            !hash_equals((string) $_SESSION['nurse_settings_csrf_token'], $token)
        ) {
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
        }

        $nurseId = (int) $_SESSION['user_id'];
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'upload_avatar') {
            $this->uploadNurseAvatar($nurseId);
        }
        if ($action === 'change_password') {
            $this->changeNursePassword($nurseId);
        }
        if ($action !== 'save_settings') {
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'Invalid settings action.'], 422);
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'Enter a valid email address (up to 150 characters).'], 422);
        }
        if ($phoneNumber !== '' && !preg_match('/^[0-9+().\s-]{7,32}$/', $phoneNumber)) {
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'Enter a valid phone number using 7 to 32 digits or standard phone punctuation.'], 422);
        }

        try {
            NurseSettingsModel::updateContact($nurseId, $email, $phoneNumber !== '' ? $phoneNumber : null);
            $_SESSION['work_email'] = $email;
            $this->nurseSettingsJsonResponse(['success' => true, 'message' => 'Settings saved successfully.']);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'That email address is already assigned to another account.'], 409);
            }
            error_log('[NurseSettingsController] Profile update failed: ' . $exception->getMessage());
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'Settings could not be saved. Please try again.'], 500);
        } catch (Throwable $exception) {
            error_log('[NurseSettingsController] Profile update failed: ' . $exception->getMessage());
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'Settings could not be saved. Please try again.'], 500);
        }
    }

    private function changeNursePassword(int $nurseId): void
    {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['confirm_password'] ?? '');
        if ($currentPassword === '' || strlen($newPassword) < 8 || $newPassword !== $confirmation) {
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'Enter your current password, use at least 8 characters, and make both new passwords match.'], 422);
        }

        try {
            if (!NurseSettingsModel::changePassword($nurseId, $currentPassword, $newPassword)) {
                $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'The current password is incorrect.'], 422);
            }
            session_regenerate_id(true);
            $_SESSION['must_change_password'] = false;
            $this->nurseSettingsJsonResponse(['success' => true, 'message' => 'Password changed successfully.']);
        } catch (Throwable $exception) {
            error_log('[NurseSettingsController] Password change failed: ' . $exception->getMessage());
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'Password could not be changed. Please try again.'], 500);
        }
    }

    private function uploadNurseAvatar(int $nurseId): void
    {
        $upload = $_FILES['profile_image'] ?? null;
        if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 5 * 1024 * 1024) {
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'Choose an image smaller than 5 MB.'], 422);
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mimeType]) || !is_uploaded_file($upload['tmp_name'])) {
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'Use a valid JPG, PNG, or WebP image.'], 422);
        }

        $directory = dirname(__DIR__, 2) . '/assets/images/profiles';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'The profile image folder is unavailable.'], 500);
        }

        $fileName = 'profile-nurse-' . $nurseId . '-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
        $target = $directory . '/' . $fileName;
        if (!move_uploaded_file($upload['tmp_name'], $target)) {
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'The image could not be saved.'], 500);
        }

        try {
            $previousImage = NurseSettingsModel::updateProfileImage($nurseId, $fileName);
            $_SESSION['profile_image'] = $fileName;
            if ($previousImage && preg_match('/^profile-nurse-' . $nurseId . '-[a-f0-9]{32}\.(jpg|png|webp)$/', basename($previousImage))) {
                $previousPath = $directory . '/' . basename($previousImage);
                if (is_file($previousPath)) {
                    unlink($previousPath);
                }
            }
            $this->nurseSettingsJsonResponse([
                'success' => true,
                'message' => 'Profile photo updated.',
                'imageUrl' => userProfileImageUrl($fileName) . '?v=' . time(),
            ]);
        } catch (Throwable $exception) {
            if (is_file($target)) {
                unlink($target);
            }
            error_log('[NurseSettingsController] Profile photo update failed: ' . $exception->getMessage());
            $this->nurseSettingsJsonResponse(['success' => false, 'message' => 'The profile photo could not be updated. Please try again.'], 500);
        }
    }

    private function nurseSettingsJsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $controller = new NurseSettingsController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->update();
    }
    $controller->index();
}
