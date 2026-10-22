<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/LocationModel.php';
require_once __DIR__ . '/../models/SignupModel.php';
require_once __DIR__ . '/../models/UserTypeModel.php';

/**
 * JSON controller for the signup options and the signup of every role
 * (resident, responder, doctor, nurse, volunteer, barangay admin).
 */
final class SignupController extends ApiController
{
    public static function options(): void
    {
        self::requireMethod('GET');

        try {
            self::respond(200, [
                'success' => true,
                'hospitals' => LocationModel::hospitalsWithBarangays(),
                'genders' => UserTypeModel::genderOptions(),
            ]);
        } catch (Throwable $e) {
            self::fail('signup_options', $e, 'Unable to load options.');
        }
    }

    public static function signup(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $role = (string) ($data['role'] ?? '');

        try {
            if ($role === 'barangay_admin') {
                $configuredCode = Database::barangayAdminSignupCode();
                $submittedCode = $data['signup_code'] ?? null;
                if ($configuredCode === null || strlen($configuredCode) < 32) {
                    error_log('Barangay Admin signup is disabled because its server code is not configured.');
                    self::respond(503, [
                        'success' => false,
                        'message' => 'Barangay Admin signup is not configured. Contact the system administrator.',
                    ]);
                }
                $ipHash = hash_hmac(
                    'sha256',
                    (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
                    Database::tokenSecret()
                );
                if (SignupModel::adminSignupLocked($ipHash)) {
                    self::respond(429, [
                        'success' => false,
                        'message' => 'Too many signup code attempts. Try again in 15 minutes.',
                    ]);
                }
                if (!SignupModel::adminSignupCodeMatches($submittedCode, $configuredCode)) {
                    SignupModel::recordAdminSignupFailure($ipHash);
                    self::respond(403, ['success' => false, 'message' => 'The signup code is invalid.']);
                }
                SignupModel::clearAdminSignupAttempts($ipHash);
            }

            $errors = SignupModel::validate($data, $role, UserTypeModel::genderOptions());
            if ($errors !== []) {
                self::respond(422, ['success' => false, 'message' => $errors[0]]);
            }

            $hospitalName = LocationModel::hospitalNameForBarangay((int) $data['hospital_id'], (int) $data['barangay_id']);
            if ($hospitalName === null) {
                self::respond(422, ['success' => false, 'message' => 'Barangay does not belong to the selected hospital.']);
            }
            if (UserTypeModel::emailExists(strtolower(trim((string) $data['work_email'])))) {
                self::respond(409, ['success' => false, 'message' => 'That email is already registered.']);
            }
            if ($role === 'doctor' && UserTypeModel::licenseExists(trim((string) $data['license_number']))) {
                self::respond(409, ['success' => false, 'message' => 'That doctor license number is already registered.']);
            }

            self::respond(201, ['success' => true, 'user_id' => SignupModel::create($data, $role, $hospitalName)]);
        } catch (Throwable $e) {
            if ($e instanceof PDOException && in_array($e->getCode(), ['42S02', '42S22'], true)) {
                error_log('Barangay Admin portal migration is missing during signup: ' . $e->getMessage());
                self::respond(503, [
                    'success' => false,
                    'message' => 'Signup approval is not set up. Apply the Barangay Admin portal database migration.',
                ]);
            }
            self::fail('signup', $e, 'Unable to create the account.');
        }
    }
}
