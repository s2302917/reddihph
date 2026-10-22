<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/staffschedModel.php';

requireAuthenticated('hospital_admin');

class StaffschedController
{
    private StaffschedModel $model;

    public function __construct()
    {
        $this->model = new StaffschedModel();
    }

    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $hospitalId = isset($_SESSION['hospital_id']) ? (int) $_SESSION['hospital_id'] : null;
        $profile = $this->model->getUserProfile((int) ($_SESSION['user_id'] ?? 0)) ?: [];
        $snapshot = $this->scheduleSnapshot($hospitalId);
        $schedules = $snapshot['schedules'];
        $recentStaff = $this->model->getRecentStaff($hospitalId);
        $hospitalName = $_SESSION['hospital_name'] ?? 'DSB District Hospital';
        $coordinatorName = $profile['full_name'] ?? ($_SESSION['full_name'] ?? 'Hospital Administrator');
        $coordinatorRole = $profile['role'] ?? ($_SESSION['role'] ?? 'hospital_admin');
        $coordinatorInitials = $this->initials($coordinatorName);
        $coordinatorImage = userProfileImageUrl($_SESSION['profile_image'] ?? null);
        $onDutyCount = $snapshot['onDutyCount'];
        $doctorCount = $snapshot['doctorCount'];
        $nurseCount = $snapshot['nurseCount'];

        require __DIR__ . '/../views/staffschedView.php';
    }

    public function status(): void
    {
        try {
            $hospitalId = isset($_SESSION['hospital_id']) ? (int) $_SESSION['hospital_id'] : null;
            $this->jsonResponse(['success' => true] + $this->scheduleSnapshot($hospitalId));
        } catch (Throwable $exception) {
            error_log('[StaffschedController] Live schedule refresh failed: ' . $exception->getMessage());
            $this->jsonResponse(['success' => false, 'message' => 'Staff schedule could not be refreshed.'], 500);
        }
    }

    private function scheduleSnapshot(?int $hospitalId): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $today = $now->format('Y-m-d');
        $byStaff = [];
        foreach ($this->model->getSchedules($hospitalId) as $row) {
            if (
                $row['shift_date'] !== null &&
                $row['shift_date'] < $today &&
                !$this->shiftIsActive($row, $now)
            ) {
                continue;
            }
            $schedule = $this->prepareSchedule($row, $now);
            $existing = $byStaff[(int) $row['id']] ?? null;
            if ($existing === null || $schedule['_priority'] > $existing['_priority']) {
                $byStaff[(int) $row['id']] = $schedule;
            }
        }

        $schedules = array_values($byStaff);
        foreach ($schedules as &$schedule) {
            unset($schedule['_priority']);
        }
        unset($schedule);

        $onDuty = array_filter($schedules, static fn(array $schedule): bool => $schedule['status'] === 'on_duty');

        return [
            'schedules' => $schedules,
            'onDutyCount' => count($onDuty),
            'doctorCount' => count(array_filter($onDuty, static fn(array $schedule): bool => $schedule['role'] === 'Doctor')),
            'nurseCount' => count(array_filter($onDuty, static fn(array $schedule): bool => $schedule['role'] === 'Nurse')),
        ];
    }

    private function prepareSchedule(array $schedule, DateTimeImmutable $now): array
    {
        $role = $schedule['role'] === 'doctor'
            ? 'Doctor'
            : ($schedule['role'] === 'nurse' ? 'Nurse' : $schedule['department']);
        $status = $this->getDutyStatus($schedule, $now);
        $online = !empty($schedule['last_login_at']);
        $shiftDate = $schedule['shift_date'] ?? null;
        $priority = 0;
        if ($shiftDate !== null) {
            $priority = $this->shiftIsActive($schedule, $now)
                ? 3
                : ($shiftDate === $now->format('Y-m-d') ? 2 : 1);
        }

        return [
            'name' => (string) $schedule['full_name'],
            'role' => $role,
            'initials' => $this->initials((string) $schedule['full_name']),
            'shift' => $this->formatShift($schedule, $now),
            'status' => $status,
            'statusLabel' => $status === 'on_duty'
                ? 'On duty'
                : ($status === 'starts_later'
                    ? 'Starts at ' . $this->formatDutyTime($schedule['shift_start'] ?? null)
                    : 'Off duty'),
            'online' => $online,
            '_priority' => $priority,
        ];
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/', preg_replace('/^Dr\.\s*/i', '', trim($name)));
        return implode('', array_map(fn(string $word): string => strtoupper(substr($word, 0, 1)), array_slice($words ?: [], 0, 2)));
    }

    private function getDutyStatus(array $schedule, DateTimeImmutable $now): string
    {
        if (empty($schedule['shift_id']) || ($schedule['shift_status'] ?? '') === 'off_duty') {
            return 'off_duty';
        }

        if ($this->shiftIsActive($schedule, $now)) {
            return 'on_duty';
        }

        $start = $this->shiftStart($schedule);
        return $start !== null && $now < $start ? 'starts_later' : 'off_duty';
    }

    private function shiftIsActive(array $schedule, DateTimeImmutable $now): bool
    {
        if (empty($schedule['shift_id'])) {
            return false;
        }
        $start = $this->shiftStart($schedule);
        $end = $this->shiftEnd($schedule, $start);

        return $start !== null && $end !== null && $now >= $start && $now < $end;
    }

    private function shiftStart(array $schedule): ?DateTimeImmutable
    {
        if (empty($schedule['shift_date']) || empty($schedule['shift_start'])) {
            return null;
        }

        return new DateTimeImmutable(
            $schedule['shift_date'] . ' ' . $schedule['shift_start'],
            new DateTimeZone('Asia/Manila')
        );
    }

    private function shiftEnd(array $schedule, ?DateTimeImmutable $start): ?DateTimeImmutable
    {
        if ($start === null || empty($schedule['shift_end'])) {
            return null;
        }
        $end = new DateTimeImmutable(
            $schedule['shift_date'] . ' ' . $schedule['shift_end'],
            $start->getTimezone()
        );

        return $end <= $start ? $end->modify('+1 day') : $end;
    }

    private function timeToMinutes(?string $time): ?int
    {
        if (!$time || !preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($time), $matches)) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];
        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        return $hours * 60 + $minutes;
    }

    private function formatShift(array $schedule, DateTimeImmutable $now): string
    {
        $start = $schedule['shift_start'] ?? null;
        $end = $schedule['shift_end'] ?? null;
        if (empty($schedule['shift_id'])) {
            return 'No shift scheduled';
        }
        if ($this->timeToMinutes($start) === null || $this->timeToMinutes($end) === null) {
            return 'Shift time not set';
        }

        $dateLabel = $schedule['shift_date'] === $now->format('Y-m-d')
            ? ''
            : 'Previous day · ';

        return $dateLabel . $this->formatDutyTime($start) . ' - ' . $this->formatDutyTime($end);
    }

    private function formatDutyTime(?string $time): string
    {
        if ($this->timeToMinutes($time) === null) {
            return '—';
        }

        return (new DateTimeImmutable('today ' . $time, new DateTimeZone('Asia/Manila')))->format('g:i A');
    }

    private function jsonResponse(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit;
    }
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $controller = new StaffschedController();
    if (($_GET['action'] ?? '') === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $controller->status();
    }
    $controller->index();
}