<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/demographicsModel.php';

requireAuthenticated('hospital_admin');

class DemographicsController
{
    private DemographicsModel $model;

    public function __construct()
    {
        $this->model = new DemographicsModel();
    }

    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $profile = $this->model->getUserProfile((int) ($_SESSION['user_id'] ?? 0)) ?: [];
        $hospitalName = $_SESSION['hospital_name'] ?? 'DSB District Hospital';
        $coordinatorName = $profile['full_name'] ?? ($_SESSION['full_name'] ?? 'Hospital Administrator');
        $coordinatorRole = ucwords(str_replace('_', ' ', $profile['role'] ?? ($_SESSION['role'] ?? 'hospital_admin')));
        $coordinatorInitials = $this->initialsFromName($coordinatorName);
        $coordinatorImage = userProfileImageUrl($profile['profile_image'] ?? ($_SESSION['profile_image'] ?? null));
        $hospitalId = (int) ($_SESSION['hospital_id'] ?? 0);
        [$range, $fromDate, $toDate, $rangeLabel] = $this->resolveDateRange();

        $summary = $this->model->getSummary($fromDate, $toDate, $hospitalId);
        $logs = $this->model->getLogs($fromDate, $toDate, $hospitalId);
        $ageGroups = $this->model->getAgeGroups($fromDate, $toDate, $hospitalId);
        $genderBreakdown = $this->model->getGenderBreakdown($fromDate, $toDate, $hospitalId);
        $topLocations = $this->model->getTopLocations($fromDate, $toDate, $hospitalId);
        $genderGradient = $this->genderGradient($genderBreakdown);

        require __DIR__ . '/../views/demographicsView.php';
    }

    private function initialsFromName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return 'US';
        }

        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        return $initials;
    }

    private function genderGradient(array $genderBreakdown): string
    {
        $total = array_sum(array_map(static function (array $item): int {
            return max(0, (int) ($item['value'] ?? 0));
        }, $genderBreakdown));
        if ($total <= 0) {
            return 'conic-gradient(#e8e2de 0 100%)';
        }

        $stops = [];
        $offset = 0.0;
        foreach ($genderBreakdown as $index => $item) {
            $value = max(0, (int) ($item['value'] ?? 0));
            if ($value <= 0) {
                continue;
            }
            $nextOffset = $offset + ($value / $total * 100);
            $stops[] = htmlspecialchars($item['color'], ENT_QUOTES, 'UTF-8') . ' ' . $offset . '% ' . $nextOffset . '%';
            $offset = $nextOffset;
        }

        return 'conic-gradient(' . implode(', ', $stops) . ')';
    }

    private function resolveDateRange(): array
    {
        $range = $_GET['range'] ?? '30days';
        $today = new DateTimeImmutable('today');
        $fromDate = $today;
        $toDate = $today;
        $rangeLabel = 'Today';

        if (in_array($range, ['7days', '15days', '30days'], true)) {
            $days = (int) str_replace('days', '', $range);
            $fromDate = $today->modify('-' . ($days - 1) . ' days');
            $rangeLabel = 'Last ' . $days . ' days';
        } elseif ($range === 'year') {
            $fromDate = $today->modify('first day of January');
            $rangeLabel = 'This year';
        } elseif ($range === 'custom') {
            $from = DateTimeImmutable::createFromFormat('!Y-m-d', $_GET['from'] ?? '');
            $to = DateTimeImmutable::createFromFormat('!Y-m-d', $_GET['to'] ?? '');

            if ($from && $to && $from <= $to) {
                $fromDate = $from;
                $toDate = $to;
                $rangeLabel = $from->format('M j, Y') . ' - ' . $to->format('M j, Y');
            } else {
                $range = '30days';
                $fromDate = $today->modify('-29 days');
                $rangeLabel = 'Last 30 days';
            }
        } elseif ($range !== 'today') {
            $range = '30days';
            $fromDate = $today->modify('-29 days');
            $rangeLabel = 'Last 30 days';
        }

        return [$range, $fromDate->format('Y-m-d'), $toDate->format('Y-m-d'), $rangeLabel];
    }
}

(new DemographicsController())->index();
