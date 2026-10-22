<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/NursePortalModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../services/TokenService.php';

final class NursePortalController extends ApiController
{
    public static function handle(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $nurseId = self::nurseId($data);
        $action = $data['action'] ?? null;

        try {
            switch ($action) {
                case 'dashboard':
                    self::respond(200, ['success' => true] + NursePortalModel::dashboard($nurseId));
                case 'settings':
                    self::respond(200, [
                        'success' => true,
                        'profile' => NursePortalModel::profile($nurseId),
                    ]);
                case 'cases':
                    self::respond(200, [
                        'success' => true,
                        'cases' => NursePortalModel::cases($nurseId),
                    ]);
                case 'case_detail':
                    $case = NursePortalModel::caseDetails(
                        $nurseId,
                        self::positiveId($data['incident_id'] ?? null)
                    );
                    if ($case === null) {
                        self::notFound('This case was not sent to your account or is no longer available.');
                    }
                    self::respond(200, ['success' => true, 'case' => $case]);
                case 'toggle_task':
                    $case = NursePortalModel::toggleTask(
                        $nurseId,
                        self::positiveId($data['incident_id'] ?? null),
                        self::positiveId($data['task_id'] ?? null),
                        self::boolean($data['done'] ?? null, 'task status')
                    );
                    if ($case === null) {
                        self::notFound('This preparation task is no longer available.');
                    }
                    self::respond(200, ['success' => true, 'case' => $case]);
                case 'save_note':
                    $note = $data['note'] ?? null;
                    if (!is_string($note)) {
                        throw new InvalidArgumentException('Enter a valid clinical note.');
                    }
                    $case = NursePortalModel::saveNote(
                        $nurseId,
                        self::positiveId($data['incident_id'] ?? null),
                        trim($note)
                    );
                    if ($case === null) {
                        self::notFound('This case was not sent to your account.');
                    }
                    self::respond(200, ['success' => true, 'case' => $case]);
                case 'beds':
                    self::respond(200, ['success' => true, 'beds' => NursePortalModel::beds($nurseId)]);
                case 'assign_bed':
                    $case = NursePortalModel::assignBed(
                        $nurseId,
                        self::positiveId($data['incident_id'] ?? null),
                        self::positiveId($data['bed_id'] ?? null)
                    );
                    if ($case === null) {
                        self::notFound('This case was not sent to your account.');
                    }
                    self::respond(200, ['success' => true, 'case' => $case]);
                case 'bed_action':
                    $actionName = self::string($data['bed_action'] ?? null, 'bed action');
                    $incidentId = isset($data['incident_id'])
                        ? self::positiveId($data['incident_id'])
                        : null;
                    self::respond(200, [
                        'success' => true,
                        'beds' => NursePortalModel::updateBed(
                            $nurseId,
                            self::positiveId($data['bed_id'] ?? null),
                            $actionName,
                            $incidentId
                        ),
                    ]);
                case 'confirm_arrival':
                    $case = NursePortalModel::confirmArrival(
                        $nurseId,
                        self::positiveId($data['incident_id'] ?? null)
                    );
                    if ($case === null) {
                        self::notFound('This case was not sent to your account.');
                    }
                    self::respond(200, ['success' => true, 'case' => $case]);
                case 'handoffs':
                    self::respond(200, [
                        'success' => true,
                        'handoffs' => NursePortalModel::handoffs($nurseId),
                    ]);
                case 'create_handoff':
                    $handoff = self::handoffData($data);
                    $incidentId = self::positiveId($data['incident_id'] ?? null);
                    NursePortalModel::createHandoff($nurseId, $incidentId, $handoff);
                    self::respond(200, [
                        'success' => true,
                        'message' => 'Patient handoff recorded.',
                        'handoffs' => NursePortalModel::handoffs($nurseId),
                    ]);
                case 'schedule':
                    $month = self::month($data['month'] ?? null);
                    self::respond(200, [
                        'success' => true,
                        'month' => $month,
                        'shifts' => NursePortalModel::schedule($nurseId, $month),
                    ]);
                case 'request_swap':
                    [$date, $shiftId, $message] = self::swapData($data);
                    NursePortalModel::requestShiftSwap($nurseId, $date, $shiftId, $message);
                    self::respond(200, [
                        'success' => true,
                        'message' => 'Shift change request submitted.',
                    ]);
                case 'update_settings':
                    $profile = NursePortalModel::updateSettings(
                        $nurseId,
                        self::boolean($data['is_available'] ?? null, 'duty status'),
                        self::boolean($data['notifications_enabled'] ?? null, 'notification setting'),
                        self::string($data['language'] ?? null, 'language')
                    );
                    self::respond(200, ['success' => true, 'profile' => $profile]);
                default:
                    self::respond(422, [
                        'success' => false,
                        'message' => 'Choose a valid nurse portal action.',
                    ]);
            }
        } catch (InvalidArgumentException $error) {
            self::respond(422, ['success' => false, 'message' => $error->getMessage()]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                self::respond(409, [
                    'success' => false,
                    'message' => 'That record conflicts with an existing assignment.',
                ]);
            }
            self::fail('nurse portal', $error, 'The requested nurse data could not be saved.');
        } catch (Throwable $error) {
            self::fail('nurse portal', $error, 'The requested nurse data could not be loaded.');
        }
    }

    private static function nurseId(array $data): int
    {
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, [
                'success' => false,
                'message' => 'Your session expired. Please sign in again.',
            ]);
        }
        $profile = UserModel::findReporter($userId);
        if ($profile === null || $profile['role'] !== 'nurse') {
            self::respond(403, [
                'success' => false,
                'message' => 'This account is not a nurse.',
            ]);
        }
        return $userId;
    }

    private static function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($id === false) {
            throw new InvalidArgumentException('Choose a valid record.');
        }
        return $id;
    }

    private static function string(mixed $value, string $label): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Enter a valid ' . $label . '.');
        }
        return trim($value);
    }

    private static function boolean(mixed $value, string $label): bool
    {
        if (!is_bool($value)) {
            throw new InvalidArgumentException('Choose a valid ' . $label . '.');
        }
        return $value;
    }

    private static function month(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            throw new InvalidArgumentException('Choose a valid calendar month.');
        }
        return $value;
    }

    private static function handoffData(array $data): array
    {
        $pulse = self::optionalVital($data['pulse'] ?? null, 3, 220, 'pulse');
        $spo2 = self::optionalVital($data['spo2'] ?? null, 0, 100, 'SpO2');
        $bloodPressure = $data['blood_pressure'] ?? '';
        $notes = $data['notes'] ?? '';
        if (!is_string($bloodPressure) || strlen($bloodPressure) > 15
            || !is_string($notes) || strlen($notes) > 2000) {
            throw new InvalidArgumentException('Enter valid handoff details.');
        }
        if ($bloodPressure !== '' && !preg_match('/^\d{2,3}\/\d{2,3}$/', $bloodPressure)) {
            throw new InvalidArgumentException('Enter blood pressure as systolic/diastolic.');
        }
        return [
            'pulse' => $pulse,
            'spo2' => $spo2,
            'blood_pressure' => $bloodPressure !== '' ? $bloodPressure : null,
            'notes' => trim($notes),
        ];
    }

    private static function optionalVital(mixed $value, int $min, int $max, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < $min || $number > $max) {
            throw new InvalidArgumentException('Enter a valid ' . $label . '.');
        }
        return (string) $number;
    }

    private static function swapData(array $data): array
    {
        $date = $data['date'] ?? null;
        $parsed = is_string($date)
            ? DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $date,
                new DateTimeZone('Asia/Manila')
            )
            : false;
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || $parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Choose a valid shift date.');
        }
        $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
        if ($parsed < $today) {
            throw new InvalidArgumentException('A shift change cannot be requested for a past date.');
        }
        $shiftId = isset($data['shift_id']) ? self::positiveId($data['shift_id']) : null;
        $message = $data['message'] ?? '';
        if (!is_string($message) || strlen($message) > 500) {
            throw new InvalidArgumentException('Keep the shift request message within 500 characters.');
        }
        return [$date, $shiftId, trim($message)];
    }

    private static function notFound(string $message): never
    {
        self::respond(404, ['success' => false, 'message' => $message]);
    }
}
