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
                case 'case_history':
                    self::respond(200, [
                        'success' => true,
                        'history' => NursePortalModel::caseHistory($nurseId),
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
                case 'schedule':
                    $month = self::month($data['month'] ?? null);
                    self::respond(200, [
                        'success' => true,
                        'month' => $month,
                        'shifts' => NursePortalModel::schedule($nurseId, $month),
                    ]);
                case 'save_schedule':
                    $schedule = self::scheduleData($data);
                    $scheduleId = isset($data['schedule_id'])
                        ? self::positiveId($data['schedule_id'])
                        : null;
                    if ($scheduleId === null) {
                        $dates = self::scheduleDates($data, $schedule['date']);
                        $created = NursePortalModel::saveSchedules($nurseId, $dates, $schedule);
                        self::respond(200, [
                            'success' => true,
                            'created' => $created,
                            'message' => $created > 0
                                ? 'Schedule dates saved. Existing dates were left unchanged.'
                                : 'No schedules added; all selected dates already have schedules.',
                        ]);
                    }
                    if (!NursePortalModel::saveSchedule($nurseId, $scheduleId, $schedule)) {
                        self::notFound('This schedule could not be found.');
                    }
                    self::respond(200, [
                        'success' => true,
                        'schedule_id' => $scheduleId,
                        'message' => 'Schedule saved.',
                    ]);
                case 'delete_schedule':
                    $scheduleId = self::positiveId($data['schedule_id'] ?? null);
                    if (!NursePortalModel::deleteSchedule($nurseId, $scheduleId)) {
                        self::notFound('This schedule could not be found.');
                    }
                    self::respond(200, ['success' => true, 'message' => 'Schedule removed.']);
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

    private static function scheduleData(array $data): array
    {
        $date = $data['date'] ?? null;
        $parsed = is_string($date)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $date)
            : false;
        if (!is_string($date)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || $parsed === false
            || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Choose a valid schedule date.');
        }
        $type = $data['type'] ?? null;
        $types = [
            'day' => 'morning',
            'night' => 'evening',
            'on_call' => 'oncall',
            'off' => 'leave',
        ];
        if (!is_string($type) || !isset($types[$type])) {
            throw new InvalidArgumentException('Choose a valid shift type.');
        }
        $start = $data['start'] ?? '';
        $end = $data['end'] ?? '';
        if (!is_string($start) || !is_string($end)) {
            throw new InvalidArgumentException('Choose valid shift times.');
        }
        if (in_array($type, ['on_call', 'off'], true)) {
            $start = '';
            $end = '';
        } elseif (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start)
            || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)
            || $start === $end) {
            throw new InvalidArgumentException('Enter valid start and end times.');
        }

        return [
            'date' => $date,
            'type' => $types[$type],
            'start' => $start,
            'end' => $end,
        ];
    }

    private static function scheduleDates(array $data, string $singleDate): array
    {
        $dates = $data['dates'] ?? [$singleDate];
        if (!is_array($dates) || $dates === [] || count($dates) > 366) {
            throw new InvalidArgumentException('Choose between 1 and 366 schedule dates.');
        }
        foreach ($dates as $date) {
            $parsed = is_string($date)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $date)
                : false;
            if (!is_string($date)
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                || $parsed === false
                || $parsed->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Choose valid schedule dates.');
            }
        }
        $dates = array_values(array_unique($dates));
        sort($dates);

        return $dates;
    }

    private static function notFound(string $message): never
    {
        self::respond(404, ['success' => false, 'message' => $message]);
    }
}
