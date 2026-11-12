<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/ResponderProfileModel.php';
require_once __DIR__ . '/../models/ResponderDashboardModel.php';
require_once __DIR__ . '/../services/TokenService.php';

final class ResponderProfileController extends ApiController
{
    private const MAX_PROFILE_IMAGE_BYTES = 5242880;
    private const PROFILE_IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public static function handle(): void
    {
        self::requireMethod('POST');
        $data = isset($_FILES['photo']) ? $_POST : self::readJsonBody();
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, [
                'success' => false,
                'message' => 'Your session expired. Please sign in again.',
            ]);
        }

        try {
            $action = $data['action'] ?? 'profile';
            if ($action === 'upload_profile_image') {
                self::uploadProfileImage($userId, $_FILES['photo'] ?? null);
                return;
            } elseif ($action === 'reset_profile_image') {
                $previousImage = ResponderProfileModel::saveProfileImage($userId, null);
                self::deleteProfileImage($userId, $previousImage);
            } elseif ($action === 'save_vehicle') {
                self::saveVehicle($userId, $data);
            } elseif ($action === 'save_schedule') {
                self::saveSchedule($userId, $data);
            } elseif ($action !== 'profile') {
                self::respond(422, [
                    'success' => false,
                    'message' => 'Unknown responder profile action.',
                ]);
            }

            $profile = ResponderProfileModel::profile($userId);
            if ($profile === null) {
                self::respond(403, [
                    'success' => false,
                    'message' => 'This account is not a responder.',
                ]);
            }
            $counts = ResponderDashboardModel::monthlyCounts($userId);
            self::respond(200, [
                'success' => true,
                'profile' => $profile,
                'monthly_dispatch_count' => (int) $counts['dispatches'],
                'hospital_arrivals_this_month' => (int) $counts['hospital_arrivals'],
            ]);
        } catch (InvalidArgumentException $error) {
            self::respond(422, ['success' => false, 'message' => $error->getMessage()]);
        } catch (Throwable $error) {
            self::fail('responder profile', $error, 'Unable to update your profile.');
        }
    }

    private static function saveVehicle(int $userId, array $data): void
    {
        $enabled = $data['driver_responder_enabled'] ?? null;
        if (!is_bool($enabled)) {
            throw new InvalidArgumentException('Choose whether Driver Responder is enabled.');
        }
        $hasVehicleData = array_key_exists('vehicle_type', $data)
            || array_key_exists('call_sign', $data)
            || array_key_exists('plate_number', $data);
        if (!$enabled && !$hasVehicleData) {
            ResponderProfileModel::saveVehicle($userId, false);
            return;
        }

        $vehicleType = self::normalizeVehicleType($data['vehicle_type'] ?? null);
        $callSign = self::requiredText($data['call_sign'] ?? null, 'Call sign', 30);
        $plateNumber = self::requiredText($data['plate_number'] ?? null, 'Plate number', 30);
        ResponderProfileModel::saveVehicle(
            $userId,
            $enabled,
            $vehicleType,
            $callSign,
            $plateNumber
        );
    }

    private static function normalizeVehicleType(mixed $value): string
    {
        $normalized = self::requiredText($value, 'Vehicle type', 40);
        foreach (['Ambulance', 'PTV'] as $allowed) {
            if (strcasecmp($normalized, $allowed) === 0) {
                return $allowed;
            }
        }

        throw new InvalidArgumentException('Choose Ambulance or PTV.');
    }

    private static function saveSchedule(int $userId, array $data): void
    {
        $enabled = $data['availability_enabled'] ?? null;
        $periods = $data['schedules'] ?? null;
        if (!is_bool($enabled) || !is_array($periods)) {
            throw new InvalidArgumentException('Choose a valid availability schedule.');
        }
        if (
            ($enabled || $periods !== [])
            && !ResponderAvailability::arePeriodsValid($periods)
        ) {
            throw new InvalidArgumentException(
                'Choose valid days and start/end times for each duty period.'
            );
        }

        $seen = [];
        foreach ($periods as $period) {
            $key = implode('|', [
                $period['day_of_week'],
                $period['start_time'],
                $period['end_time'],
            ]);
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Remove duplicate duty periods.');
            }
            $seen[$key] = true;
        }

        ResponderProfileModel::saveSchedule($userId, $periods, $enabled);
    }

    private static function requiredText(mixed $value, string $label, int $maxLength): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException("Enter a valid {$label}.");
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxLength) {
            throw new InvalidArgumentException("{$label} must be 1 to {$maxLength} characters.");
        }

        return $value;
    }

    private static function uploadProfileImage(int $userId, ?array $photo): void
    {
        if (
            $photo === null
            || ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !isset($photo['tmp_name'], $photo['size'])
            || (int) $photo['size'] > self::MAX_PROFILE_IMAGE_BYTES
            || !is_uploaded_file($photo['tmp_name'])
        ) {
            self::respond(422, [
                'success' => false,
                'message' => 'Choose a valid image smaller than 5 MB.',
            ]);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($photo['tmp_name']);
        if (!isset(self::PROFILE_IMAGE_MIMES[$mime]) || getimagesize($photo['tmp_name']) === false) {
            self::respond(422, [
                'success' => false,
                'message' => 'Use a valid JPG, PNG, or WebP image.',
            ]);
        }

        $directory = self::profileImageDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('The profile image folder is unavailable.');
        }

        $filename = 'profile-' . $userId . '-' . bin2hex(random_bytes(16))
            . '.' . self::PROFILE_IMAGE_MIMES[$mime];
        $target = $directory . '/' . $filename;
        if (!move_uploaded_file($photo['tmp_name'], $target)) {
            throw new RuntimeException('The profile image could not be stored.');
        }

        try {
            $previousImage = ResponderProfileModel::saveProfileImage($userId, $filename);
        } catch (Throwable $error) {
            if (is_file($target)) {
                unlink($target);
            }
            throw $error;
        }

        self::deleteProfileImage($userId, $previousImage);
        $profile = ResponderProfileModel::profile($userId);
        self::respond(200, [
            'success' => true,
            'profile_image' => $profile['profile_image'] ?? null,
            'profile_image_url' => $profile['profile_image_url'] ?? null,
        ]);
    }

    private static function deleteProfileImage(int $userId, ?string $image): void
    {
        if ($image === null) {
            return;
        }

        $filename = basename(str_replace('\\', '/', $image));
        $pattern = '/\\Aprofile-' . preg_quote((string) $userId, '/')
            . '-[a-f0-9]{32}\\.(jpg|png|webp)\\z/';
        if (preg_match($pattern, $filename) !== 1) {
            return;
        }

        $path = self::profileImageDirectory() . '/' . $filename;
        if (is_file($path) && !unlink($path)) {
            error_log('Mobile API: unable to remove an old responder profile image.');
        }
    }

    private static function profileImageDirectory(): string
    {
        $directory = __DIR__;
        while (($parent = dirname($directory)) !== $directory) {
            $directory = $parent;
            if (is_dir($directory . '/reddiph_admin')) {
                return $directory . '/reddiph_admin/assets/images/profiles';
            }
        }

        throw new RuntimeException('The shared profile image folder is unavailable.');
    }
}