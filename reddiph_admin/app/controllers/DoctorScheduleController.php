<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/DoctorScheduleModel.php';
require_once __DIR__ . '/../models/DoctorNotifiedCasesModel.php';

requireAuthenticated('doctor');

class DoctorScheduleController
{
    public function index(): void
    {
        $doctorId = (int) $_SESSION['user_id'];
        $sidebarDoctor = DoctorNotifiedCasesModel::sidebarProfile($doctorId);
        if (!$sidebarDoctor) {
            http_response_code(404);
            exit('Doctor account not found.');
        }

        $_SESSION['doctor_schedule_csrf_token'] ??= bin2hex(random_bytes(32));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleRequest($doctorId);
        }

        $month = $this->requestedMonth();
        $shifts = array_map(
            fn (array $shift): array => $shift + ['label' => $this->shiftLabel($shift)],
            DoctorScheduleModel::forMonth($doctorId, $month)
        );
        $tomorrowShifts = array_map(
            fn (array $shift): array => $shift + ['label' => $this->shiftLabel($shift)],
            DoctorScheduleModel::tomorrow($doctorId)
        );

        $pageTitle = 'Schedule';
        $currentPage = 'schedule';
        $calendar = $this->buildCalendar($month, $shifts);
        $tomorrowLabel = (new DateTimeImmutable('tomorrow', new DateTimeZone('Asia/Manila')))->format('l, F j');
        $message = isset($_GET['message']) ? (string) $_GET['message'] : null;
        $messageType = ($_GET['type'] ?? '') === 'error' ? 'error' : 'success';

        require __DIR__ . '/../views/DoctorScheduleView.php';
    }

    private function handleRequest(int $doctorId): never
    {
        $token = (string) ($_POST['csrf_token'] ?? '');
        if (
            empty($_SESSION['doctor_schedule_csrf_token']) ||
            !hash_equals((string) $_SESSION['doctor_schedule_csrf_token'], $token)
        ) {
            $this->redirectWithMessage($this->requestedMonth(), 'Your session expired. Refresh and try again.', 'error');
        }

        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'delete') {
                $scheduleId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
                if (!$scheduleId || $scheduleId < 1 || !DoctorScheduleModel::delete($doctorId, $scheduleId)) {
                    $this->redirectWithMessage($this->requestedMonth(), 'Schedule could not be found.', 'error');
                }
                $this->redirectWithMessage($this->requestedMonth(), 'Schedule removed.', 'success');
            }

            if (!in_array($action, ['add', 'update'], true)) {
                $this->redirectWithMessage($this->requestedMonth(), 'Choose a valid schedule action.', 'error');
            }

            $data = $this->scheduleData();
            if ($action === 'add') {
                DoctorScheduleModel::create($doctorId, $data);
                $this->redirectWithMessage(substr($data['date'], 0, 7), 'Schedule added.', 'success');
            }

            $scheduleId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if (!$scheduleId || $scheduleId < 1 || !DoctorScheduleModel::update($doctorId, $scheduleId, $data)) {
                $this->redirectWithMessage(substr($data['date'], 0, 7), 'Schedule could not be found.', 'error');
            }
            $this->redirectWithMessage(substr($data['date'], 0, 7), 'Schedule updated.', 'success');
        } catch (InvalidArgumentException $exception) {
            $this->redirectWithMessage($this->requestedMonth(), $exception->getMessage(), 'error');
        } catch (PDOException $exception) {
            error_log(sprintf(
                '[DoctorScheduleController] Schedule action %s failed for doctor %d: %s',
                $action,
                $doctorId,
                $exception->getMessage()
            ));
            $message = $exception->getCode() === '23000'
                ? 'A schedule already exists for that date. Select its shift to edit it.'
                : 'Schedule could not be saved. Please try again.';
            $this->redirectWithMessage($this->requestedMonth(), $message, 'error');
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[DoctorScheduleController] Schedule action %s failed for doctor %d: %s',
                $action,
                $doctorId,
                $exception->getMessage()
            ));
            $this->redirectWithMessage($this->requestedMonth(), 'Schedule could not be saved. Please try again.', 'error');
        }
    }

    private function scheduleData(): array
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

    private function requestedMonth(): string
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

    private function buildCalendar(string $month, array $shifts): array
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

    private function shiftLabel(array $shift): string
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

    private function redirectWithMessage(string $month, string $message, string $type): never
    {
        header('Location: ' . assetUrl('app/controllers/DoctorScheduleController.php') . '?' . http_build_query([
            'month' => $month,
            'message' => $message,
            'type' => $type,
        ]));
        exit;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    (new DoctorScheduleController())->index();
}
