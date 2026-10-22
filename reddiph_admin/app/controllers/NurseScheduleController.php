<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/NurseScheduleModel.php';
require_once __DIR__ . '/../models/NurseNotifiedCasesModel.php';

requireAuthenticated('nurse');

class NurseScheduleController
{
    public function index(): void
    {
        $nurseId = (int) $_SESSION['user_id'];
        $sidebarNurse = NurseNotifiedCasesModel::profile($nurseId);
        if (!$sidebarNurse) {
            http_response_code(404);
            exit('Nurse account not found.');
        }

        $_SESSION['nurse_schedule_csrf_token'] ??= bin2hex(random_bytes(32));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleNurseScheduleRequest($nurseId);
        }

        $month = $this->requestedNurseScheduleMonth();
        $shifts = array_map(
            fn (array $shift): array => $shift + ['label' => $this->nurseScheduleLabel($shift)],
            NurseScheduleModel::forMonth($nurseId, $month)
        );
        $tomorrowShifts = array_map(
            fn (array $shift): array => $shift + ['label' => $this->nurseScheduleLabel($shift)],
            NurseScheduleModel::tomorrow($nurseId)
        );

        $pageTitle = 'Schedule';
        $currentPage = 'schedule';
        $calendar = $this->buildNurseScheduleCalendar($month, $shifts);
        $tomorrowLabel = (new DateTimeImmutable('tomorrow', new DateTimeZone('Asia/Manila')))->format('l, F j');
        $message = isset($_GET['message']) ? (string) $_GET['message'] : null;
        $messageType = ($_GET['type'] ?? '') === 'error' ? 'error' : 'success';

        require __DIR__ . '/../views/NurseScheduleView.php';
    }

    private function handleNurseScheduleRequest(int $nurseId): never
    {
        $month = $this->requestedNurseScheduleMonth();
        $token = (string) ($_POST['csrf_token'] ?? '');
        if (
            empty($_SESSION['nurse_schedule_csrf_token']) ||
            !hash_equals((string) $_SESSION['nurse_schedule_csrf_token'], $token)
        ) {
            $this->redirectNurseSchedule($month, 'Your session expired. Refresh and try again.', 'error');
        }

        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'delete') {
                $scheduleId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$scheduleId || $scheduleId < 1 || !NurseScheduleModel::delete($nurseId, $scheduleId)) {
                    $this->redirectNurseSchedule($month, 'Schedule could not be found.', 'error');
                }
                $this->redirectNurseSchedule($month, 'Schedule removed.', 'success');
            }

            if (!in_array($action, ['add', 'update'], true)) {
                $this->redirectNurseSchedule($month, 'Choose a valid schedule action.', 'error');
            }

            $data = $this->nurseScheduleData();
            if ($action === 'add') {
                NurseScheduleModel::create($nurseId, $data);
                $this->redirectNurseSchedule(substr($data['date'], 0, 7), 'Schedule added.', 'success');
            }

            $scheduleId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$scheduleId || $scheduleId < 1 || !NurseScheduleModel::update($nurseId, $scheduleId, $data)) {
                $this->redirectNurseSchedule(substr($data['date'], 0, 7), 'Schedule could not be found.', 'error');
            }
            $this->redirectNurseSchedule(substr($data['date'], 0, 7), 'Schedule updated.', 'success');
        } catch (InvalidArgumentException $exception) {
            $this->redirectNurseSchedule($month, $exception->getMessage(), 'error');
        } catch (PDOException $exception) {
            error_log(sprintf(
                '[NurseScheduleController] Schedule action %s failed for nurse %d: %s',
                $action,
                $nurseId,
                $exception->getMessage()
            ));
            $message = $exception->getCode() === '23000'
                ? 'A schedule already exists for that date. Select its shift to edit it.'
                : 'Schedule could not be saved. Please try again.';
            $this->redirectNurseSchedule($month, $message, 'error');
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[NurseScheduleController] Schedule action %s failed for nurse %d: %s',
                $action,
                $nurseId,
                $exception->getMessage()
            ));
            $this->redirectNurseSchedule($month, 'Schedule could not be saved. Please try again.', 'error');
        }
    }

    private function nurseScheduleData(): array
    {
        $date = trim((string) ($_POST['date'] ?? ''));
        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Choose a valid calendar date.');
        }

        $type = (string) ($_POST['type'] ?? '');
        if (!in_array($type, ['morning', 'evening', 'oncall', 'leave'], true)) {
            throw new InvalidArgumentException('Choose a valid schedule type.');
        }

        if (in_array($type, ['oncall', 'leave'], true)) {
            return ['date' => $date, 'type' => $type, 'start' => '', 'end' => ''];
        }

        $start = trim((string) ($_POST['start'] ?? ''));
        $end = trim((string) ($_POST['end'] ?? ''));
        if (
            !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) ||
            !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) ||
            $start === $end
        ) {
            throw new InvalidArgumentException('Enter valid start and end times. For an overnight shift, set the end time earlier than the start time.');
        }

        return ['date' => $date, 'type' => $type, 'start' => $start, 'end' => $end];
    }

    private function requestedNurseScheduleMonth(): string
    {
        $month = (string) ($_GET['month'] ?? '');
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
            if ($date && $date->format('Y-m') === $month) {
                return $month;
            }
        }

        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m');
    }

    private function buildNurseScheduleCalendar(string $month, array $shifts): array
    {
        $monthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
        $shiftsByDay = [];
        foreach ($shifts as $shift) {
            $shiftsByDay[(int) substr($shift['date'], 8, 2)][] = $shift;
        }

        $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
        $days = [];
        for ($day = 1, $lastDay = (int) $monthDate->format('t'); $day <= $lastDay; $day++) {
            $date = $month . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $days[] = [
                'number' => $day,
                'date' => $date,
                'isToday' => $date === $today,
                'shifts' => $shiftsByDay[$day] ?? [],
            ];
        }

        return [
            'label' => $monthDate->format('F Y'),
            'previous' => $monthDate->modify('-1 month')->format('Y-m'),
            'next' => $monthDate->modify('+1 month')->format('Y-m'),
            'leadingBlanks' => (int) $monthDate->format('w'),
            'days' => $days,
        ];
    }

    private function nurseScheduleLabel(array $shift): string
    {
        if ($shift['start'] !== '' && $shift['end'] !== '') {
            return date('g:i A', strtotime($shift['start'])) . '–' . date('g:i A', strtotime($shift['end']));
        }

        return match ($shift['type']) {
            'oncall' => 'On-call',
            'leave' => 'Leave',
            default => 'Schedule',
        };
    }

    private function redirectNurseSchedule(string $month, string $message, string $type): never
    {
        header('Location: ' . assetUrl('app/controllers/NurseScheduleController.php') . '?' . http_build_query([
            'month' => $month,
            'message' => $message,
            'type' => $type,
        ]));
        exit;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    (new NurseScheduleController())->index();
}
