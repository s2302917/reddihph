<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../models/analyticsModel.php';

requireAuthenticated('hospital_admin');

class AnalyticsController
{
    private AnalyticsModel $model;

    public function __construct()
    {
        $this->model = new AnalyticsModel();
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
        $coordinatorImage = userProfileImageUrl($profile['profile_image'] ?? ($_SESSION['profile_image'] ?? null));
        $coordinatorInitials = $this->initialsFromName($coordinatorName);
        $hospitalId = (int) ($_SESSION['hospital_id'] ?? 0);
        [$range, $fromDate, $toDate, $rangeLabel] = $this->resolveDateRange();

        $metrics = $this->model->getMetrics($fromDate, $toDate, $hospitalId);
        $incidentTypes = $this->model->getIncidentTypes($fromDate, $toDate, $hospitalId);
        $topLocations = $this->model->getTopLocations($fromDate, $toDate, $hospitalId);
        $fleet = $this->model->getFleetMetrics($hospitalId);
        $trend = $this->model->getResponseTrend($fromDate, $toDate, $hospitalId);

        require __DIR__ . '/../views/analyticsView.php';
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

(new AnalyticsController())->index();