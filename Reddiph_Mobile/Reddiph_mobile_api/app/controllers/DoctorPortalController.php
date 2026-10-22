<?php

declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../models/DoctorPortalModel.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../services/TokenService.php';

final class DoctorPortalController extends ApiController
{
    public static function handle(): void
    {
        self::requireMethod('POST');
        $data = self::readJsonBody();
        $doctorId = self::doctorId($data);
        $action = $data['action'] ?? null;

        try {
            switch ($action) {
                case 'dashboard':
                    self::respond(200, [
                        'success' => true,
                        'profile' => DoctorPortalModel::profile($doctorId),
                        'cases' => DoctorPortalModel::cases($doctorId, 'incoming'),
                        'review_cases' => DoctorPortalModel::cases($doctorId, 'in_er'),
                    ]);
                case 'cases':
                    $filter = self::filter($data['filter'] ?? 'all', [
                        'all',
                        'incoming',
                        'in_er',
                        'done',
                    ]);
                    self::respond(200, [
                        'success' => true,
                        'cases' => DoctorPortalModel::cases($doctorId, $filter),
                    ]);
                case 'case_detail':
                    $incidentId = self::positiveId($data['incident_id'] ?? null);
                    $case = DoctorPortalModel::caseDetails($doctorId, $incidentId);
                    if ($case === null) {
                        self::respond(404, [
                            'success' => false,
                            'message' => 'This case was not sent to your account or is no longer available.',
                        ]);
                    }
                    self::respond(200, ['success' => true, 'case' => $case]);
                case 'save_review':
                    $incidentId = self::positiveId($data['incident_id'] ?? null);
                    $note = $data['clinical_note'] ?? '';
                    $confirmArrival = $data['confirm_arrival'] ?? false;
                    if (!is_string($note) || strlen(trim($note)) > 2000
                        || !is_bool($confirmArrival)) {
                        self::respond(422, [
                            'success' => false,
                            'message' => 'Enter a clinical note of no more than 2000 characters.',
                        ]);
                    }
                    $case = DoctorPortalModel::saveCaseReview(
                        $doctorId,
                        $incidentId,
                        trim($note),
                        $confirmArrival
                    );
                    if ($case === null) {
                        self::respond(404, [
                            'success' => false,
                            'message' => 'This case was not sent to your account.',
                        ]);
                    }
                    self::respond(200, [
                        'success' => true,
                        'message' => $confirmArrival
                            ? 'Arrival confirmed and clinical note saved.'
                            : 'Clinical note saved.',
                        'case' => $case,
                    ]);
                case 'history':
                    $period = self::filter($data['period'] ?? 'week', [
                        'week',
                        'month',
                        'all',
                    ]);
                    self::respond(
                        200,
                        ['success' => true] + DoctorPortalModel::history($doctorId, $period)
                    );
                case 'schedule':
                    $month = self::month($data['month'] ?? null);
                    self::respond(200, [
                        'success' => true,
                        'month' => $month,
                        'shifts' => DoctorPortalModel::schedule($doctorId, $month),
                    ]);
                case 'save_schedule':
                    $schedule = self::scheduleData($data);
                    $scheduleId = isset($data['schedule_id'])
                        ? self::positiveId($data['schedule_id'])
                        : null;
                    $savedId = DoctorPortalModel::saveSchedule(
                        $doctorId,
                        $scheduleId,
                        $schedule
                    );
                    if ($savedId === false) {
                        self::respond(404, [
                            'success' => false,
                            'message' => 'This schedule could not be found.',
                        ]);
                    }
                    self::respond(200, [
                        'success' => true,
                        'schedule_id' => $savedId,
                        'message' => 'Schedule saved.',
                    ]);
                case 'delete_schedule':
                    $scheduleId = self::positiveId($data['schedule_id'] ?? null);
                    if (!DoctorPortalModel::deleteSchedule($doctorId, $scheduleId)) {
                        self::respond(404, [
                            'success' => false,
                            'message' => 'This schedule could not be found.',
                        ]);
                    }
                    self::respond(200, [
                        'success' => true,
                        'message' => 'Schedule removed.',
                    ]);
                default:
                    self::respond(422, [
                        'success' => false,
                        'message' => 'Choose a valid doctor portal action.',
                    ]);
            }
        } catch (InvalidArgumentException $error) {
            self::respond(422, ['success' => false, 'message' => $error->getMessage()]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                self::respond(409, [
                    'success' => false,
                    'message' => 'A schedule already exists for that date.',
                ]);
            }
            self::fail('doctor portal', $error, 'The requested doctor data could not be loaded.');
        } catch (Throwable $error) {
            self::fail('doctor portal', $error, 'The requested doctor data could not be loaded.');
        }
    }

    private static function doctorId(array $data): int
    {
        $userId = TokenService::verify($data['token'] ?? null);
        if ($userId === null) {
            self::respond(401, [
                'success' => false,
                'message' => 'Your session expired. Please sign in again.',
            ]);
        }
        $profile = UserModel::findReporter($userId);
        if ($profile === null || $profile['role'] !== 'doctor') {
            self::respond(403, [
                'success' => false,
                'message' => 'This account is not a doctor.',
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

    private static function filter(mixed $value, array $allowed): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('Choose a valid filter.');
        }

        return $value;
    }

    private static function month(mixed $value): string
    {
        if (!is_string($value)
            || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            throw new InvalidArgumentException('Choose a valid calendar month.');
        }

        return $value;
    }

    private static function scheduleData(array $data): array
    {
        $date = $data['date'] ?? null;
        $parsedDate = is_string($date)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $date)
            : false;
        if (!is_string($date)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            || $parsedDate === false
            || $parsedDate->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Choose a valid schedule date.');
        }
        $type = $data['type'] ?? null;
        if (!is_string($type)
            || !in_array($type, ['morning', 'evening', 'oncall', 'leave'], true)) {
            throw new InvalidArgumentException('Choose a valid shift type.');
        }
        $start = $data['start'] ?? '';
        $end = $data['end'] ?? '';
        if (!is_string($start) || !is_string($end)) {
            throw new InvalidArgumentException('Choose valid shift times.');
        }
        if (in_array($type, ['oncall', 'leave'], true)) {
            $start = '';
            $end = '';
        } elseif (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start)
            || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)
            || $start === $end) {
            throw new InvalidArgumentException('Enter valid start and end times.');
        }

        return ['date' => $date, 'type' => $type, 'start' => $start, 'end' => $end];
    }
}
