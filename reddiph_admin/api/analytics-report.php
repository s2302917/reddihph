<?php

require_once __DIR__ . '/../config/config.php';
requireAuthenticated('hospital_admin');
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/models/analyticsModel.php';
require_once __DIR__ . '/../app/models/demographicsModel.php';

if (!class_exists('FPDF')) {
    require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php';
}

$hospitalId = (int) ($_SESSION['hospital_id'] ?? 0);
if ($hospitalId <= 0) {
    http_response_code(403);
    exit('Hospital assignment required.');
}

$fromValue = (string) ($_GET['from'] ?? '');
$toValue = (string) ($_GET['to'] ?? '');
$fromDate = DateTimeImmutable::createFromFormat('!Y-m-d', $fromValue);
$fromErrors = DateTimeImmutable::getLastErrors();
$toDate = DateTimeImmutable::createFromFormat('!Y-m-d', $toValue);
$toErrors = DateTimeImmutable::getLastErrors();
if (
    !$fromDate || !$toDate
    || $fromDate->format('Y-m-d') !== $fromValue
    || $toDate->format('Y-m-d') !== $toValue
    || ($fromErrors && ($fromErrors['warning_count'] || $fromErrors['error_count']))
    || ($toErrors && ($toErrors['warning_count'] || $toErrors['error_count']))
    || $fromDate > $toDate
) {
    http_response_code(400);
    exit('A valid analytics date range is required.');
}

$from = $fromDate->format('Y-m-d');
$to = $toDate->format('Y-m-d');
$hospitalName = (string) ($_SESSION['hospital_name'] ?? 'Hospital');
$periodLabel = $fromDate->format('M j, Y') . ' - ' . $toDate->format('M j, Y');
$reportIsDemographics = defined('REDDIPH_DEMOGRAPHICS_REPORT');
$model = new AnalyticsModel();
$demographicsModel = new DemographicsModel();

$incidents = $model->getIncidentReports($from, $to, $hospitalId);
$demographicsSummary = $demographicsModel->getSummary($from, $to, $hospitalId);
$demographicsLogs = $demographicsModel->getLogs($from, $to, $hospitalId);
$ageGroups = $demographicsModel->getAgeGroups($from, $to, $hospitalId);
$genderBreakdown = $demographicsModel->getGenderBreakdown($from, $to, $hospitalId);
$demographicsLocations = $demographicsModel->getTopLocations($from, $to, $hospitalId);
$priorityUpdates = $demographicsModel->getAlerts($from, $to, $hospitalId);
$fleetInventory = $model->getFleetInventory($hospitalId);
$staffSchedules = $model->getStaffSchedules($from, $to, $hospitalId);
$facilityResources = $model->getFacilityResources($hospitalId);
$metrics = $model->getMetrics($from, $to, $hospitalId);
$incidentTypes = $model->getIncidentTypes($from, $to, $hospitalId);
$topLocations = $model->getTopLocations($from, $to, $hospitalId);
$fleetMetrics = $model->getFleetMetrics($hospitalId);
$trend = $model->getResponseTrend($from, $to, $hospitalId);

function analyticsPdfText(string $value): string
{
    $converted = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value);
    return $converted === false ? '' : $converted;
}

function analyticsPdfLine(FPDF $pdf, string $label, $value): void
{
    $text = $value === null || $value === '' ? 'Not recorded' : (string) $value;
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Write(5, analyticsPdfText($label . ': '));
    $pdf->SetFont('Arial', '', 8);
    $pdf->MultiCell(0, 5, analyticsPdfText($text));
}

function analyticsPdfSection(FPDF $pdf, string $title): void
{
    if ($pdf->GetY() > 175) {
        $pdf->AddPage();
    }
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(125, 0, 0);
    $pdf->Cell(0, 8, analyticsPdfText($title), 0, 1);
    $pdf->SetTextColor(25, 25, 25);
}

function analyticsPdfBar(FPDF $pdf, string $label, int $value, int $percentage, string $color): void
{
    analyticsPdfLine($pdf, $label, $value . ' (' . $percentage . '%)');
    $hex = ltrim($color, '#');
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        $hex = '8b1c22';
    }
    $pdf->SetFillColor(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    $pdf->SetFillColor(240, 233, 230);
    $pdf->Rect(14, $pdf->GetY(), 260, 3, 'F');
    if ($percentage > 0) {
        $pdf->SetFillColor(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
        $pdf->Rect(14, $pdf->GetY(), 260 * min(100, $percentage) / 100, 3, 'F');
    }
    $pdf->Ln(5);
}

function analyticsPdfDrawTrend(FPDF $pdf, array $rows): void
{
    $chartX = 14;
    $chartY = $pdf->GetY() + 3;
    $chartWidth = 269;
    $chartHeight = 54;
    if ($chartY + $chartHeight > 195) {
        $pdf->AddPage();
        $chartY = $pdf->GetY();
    }

    $pdf->SetDrawColor(220, 214, 210);
    $pdf->Rect($chartX, $chartY, $chartWidth, $chartHeight);
    $values = array_map(static function (array $row): float {
        return (float) ($row['value'] ?? 0);
    }, $rows);
    $maxValue = max(5, (int) ceil((max($values ?: [0]) ?: 0) / 5) * 5);
    $plotLeft = $chartX + 10;
    $plotTop = $chartY + 5;
    $plotWidth = $chartWidth - 16;
    $plotHeight = $chartHeight - 14;

    for ($gridLine = 0; $gridLine <= 4; $gridLine++) {
        $y = $plotTop + ($plotHeight * $gridLine / 4);
        $pdf->SetDrawColor(235, 230, 226);
        $pdf->Line($plotLeft, $y, $plotLeft + $plotWidth, $y);
    }

    if (!$rows) {
        $pdf->SetXY($chartX + 4, $chartY + 22);
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell($chartWidth - 8, 6, 'No response-time data for this date range.', 0, 0, 'C');
        $pdf->SetY($chartY + $chartHeight + 4);
        return;
    }

    $pdf->SetDrawColor(139, 0, 0);
    $pdf->SetFillColor(139, 0, 0);
    $count = count($rows);
    $previousX = null;
    $previousY = null;
    foreach ($rows as $index => $row) {
        $pointX = $plotLeft + ($plotWidth * $index / max(1, $count - 1));
        $pointY = $plotTop + $plotHeight - ((float) ($row['value'] ?? 0) / $maxValue * $plotHeight);
        if ($previousX !== null) {
            $pdf->Line($previousX, $previousY, $pointX, $pointY);
        }
        $pdf->Rect($pointX - .75, $pointY - .75, 1.5, 1.5, 'F');
        if ($index === 0 || $index === $count - 1 || $index === (int) floor(($count - 1) / 2)) {
            $pdf->SetXY($pointX - 8, $chartY + $chartHeight - 7);
            $pdf->SetFont('Arial', '', 6);
            $pdf->Cell(16, 4, analyticsPdfText((string) ($row['label'] ?? '')), 0, 0, 'C');
        }
        $previousX = $pointX;
        $previousY = $pointY;
    }
    $pdf->SetY($chartY + $chartHeight + 4);
}

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(12, 10, 12);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();
$pdf->SetTitle($reportIsDemographics ? 'Hospital Logs and Demographics Report' : 'Hospital Analytics Report');
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 9, analyticsPdfText($hospitalName), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, $reportIsDemographics ? 'Complete Logs, Demographics, and Operations Report' : 'Complete Analytics and Operations Report', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 6, analyticsPdfText('Analytics period: ' . $periodLabel . ' | Current hospital operational snapshots included'), 0, 1, 'C');
$pdf->Cell(0, 6, 'Generated ' . date('M j, Y g:i A'), 0, 1, 'C');

analyticsPdfSection($pdf, 'Demographic Summary - ' . $periodLabel);
analyticsPdfLine($pdf, 'Active alerts', $demographicsSummary['activeAlerts'] ?? 0);
analyticsPdfLine($pdf, 'Resolved in range', $demographicsSummary['resolvedToday'] ?? 0);
analyticsPdfLine($pdf, 'Patients logged', $demographicsSummary['patientsLogged'] ?? 0);
analyticsPdfLine($pdf, 'High-risk cases', $demographicsSummary['highRiskCases'] ?? 0);

analyticsPdfSection($pdf, 'Emergency Logs (' . count($demographicsLogs) . ' records)');
if (!$demographicsLogs) {
    analyticsPdfLine($pdf, 'Logs', 'No incidents were logged in this period.');
}
foreach ($demographicsLogs as $log) {
    analyticsPdfSection($pdf, $log['time'] . ' - ' . $log['type'] . ' (' . $log['status'] . ')');
    analyticsPdfLine($pdf, 'Patient / caller', $log['patient'] . ' / ' . $log['caller']);
    analyticsPdfLine($pdf, 'Location / assigned to', $log['location'] . ' / ' . $log['assigned']);
}

analyticsPdfSection($pdf, 'Priority Updates');
foreach ($priorityUpdates as $update) {
    analyticsPdfLine($pdf, $update['title'] ?? 'Update', $update['message'] ?? '');
}
if (!$priorityUpdates) {
    analyticsPdfLine($pdf, 'Priority updates', 'No priority updates for this period.');
}

analyticsPdfSection($pdf, 'Age Groups');
if (!$ageGroups) {
    analyticsPdfLine($pdf, 'Age groups', 'No age data was recorded in this period.');
}
foreach ($ageGroups as $index => $group) {
    $ageColors = ['#8b1c22', '#b94b4d', '#d39790', '#9bb7bb'];
    analyticsPdfBar($pdf, $group['label'], (int) $group['value'], (int) ($group['percentage'] ?? 0), $ageColors[$index % count($ageColors)]);
}

analyticsPdfSection($pdf, 'Gender Distribution');
if (!$genderBreakdown) {
    analyticsPdfLine($pdf, 'Gender', 'No gender data was recorded in this period.');
}
foreach ($genderBreakdown as $gender) {
    $percentage = (int) ($gender['value'] ?? 0);
    analyticsPdfBar($pdf, $gender['label'], $percentage, $percentage, $gender['color'] ?? '#8b1c22');
}

analyticsPdfSection($pdf, 'Top Locations');
if (!$demographicsLocations) {
    analyticsPdfLine($pdf, 'Locations', 'No location data was recorded in this period.');
}
foreach ($demographicsLocations as $location) {
    analyticsPdfBar($pdf, $location['label'], (int) $location['value'], (int) ($location['percentage'] ?? 0), '#8b1c22');
}

analyticsPdfSection($pdf, 'Incident Reports (' . count($incidents) . ')');
if (!$incidents) {
    analyticsPdfLine($pdf, 'Incidents', 'No incidents were reported in this period.');
}
foreach ($incidents as $incident) {
    analyticsPdfSection($pdf, '#' . $incident['incident_id'] . ' - ' . $incident['incident_type']);
    analyticsPdfLine($pdf, 'Status', $incident['status']);
    analyticsPdfLine($pdf, 'Patient', trim(($incident['patient_name'] ?? 'Name not recorded') . ' | ' . ($incident['patient_gender'] ?? 'Unknown') . ' | Age ' . ($incident['patient_age'] ?? 'Unknown')));
    analyticsPdfLine($pdf, 'Location', $incident['location']);
    analyticsPdfLine($pdf, 'Barangay ID / hospital ID', ($incident['barangay_id'] ?? 'Not assigned') . ' / ' . ($incident['hospital_id'] ?? 'Not assigned'));
    analyticsPdfLine($pdf, 'Coordinates', ($incident['latitude'] ?? '') . ', ' . ($incident['longitude'] ?? ''));
    analyticsPdfLine($pdf, 'Hospital', $incident['hospital_name']);
    analyticsPdfLine($pdf, 'Responder', $incident['responder_name']);
    analyticsPdfLine($pdf, 'Transport', trim(($incident['transport_name'] ?? 'Not assigned') . ' | ' . ($incident['transport_type'] ?? '')));
    analyticsPdfLine($pdf, 'Driver', $incident['driver_name']);
    analyticsPdfLine($pdf, 'ETA', $incident['eta_minutes'] === null ? null : $incident['eta_minutes'] . ' minutes');
    analyticsPdfLine($pdf, 'Reported / resolved', $incident['reported_at'] . ' / ' . ($incident['resolved_at'] ?? 'Not resolved'));
    analyticsPdfLine($pdf, 'Created / updated', $incident['created_at'] . ' / ' . $incident['updated_at']);
    analyticsPdfLine($pdf, 'Notes', $incident['notes']);
}

analyticsPdfSection($pdf, 'Ambulance and PTV Fleet (' . count($fleetInventory) . ' vehicles) - Current Snapshot');
if (!$fleetInventory) {
    analyticsPdfLine($pdf, 'Fleet', 'No vehicles registered for this hospital.');
}
foreach ($fleetInventory as $vehicle) {
    analyticsPdfSection($pdf, $vehicle['call_sign'] . ' (' . $vehicle['vehicle_type'] . ') - ID ' . $vehicle['id']);
    analyticsPdfLine($pdf, 'Status / plate', $vehicle['status'] . ' / ' . ($vehicle['plate_number'] ?? 'Not recorded'));
    analyticsPdfLine($pdf, 'Driver / assignment', ($vehicle['driver_name'] ?? 'Not assigned') . ' / ' . ($vehicle['assignment_note'] ?? 'No assignment note'));
    analyticsPdfLine($pdf, 'ETA / arrived', ($vehicle['eta_minutes'] === null ? 'Not recorded' : $vehicle['eta_minutes'] . ' minutes') . ' / ' . ($vehicle['arrived_at'] ?? 'Not recorded'));
    analyticsPdfLine($pdf, 'Coordinates', ($vehicle['latitude'] ?? 'Not recorded') . ', ' . ($vehicle['longitude'] ?? 'Not recorded'));
    analyticsPdfLine($pdf, 'Location recorded / created / updated', ($vehicle['location_recorded_at'] ?? 'Not recorded') . ' / ' . $vehicle['created_at'] . ' / ' . $vehicle['updated_at']);
}

analyticsPdfSection($pdf, 'Doctor and Nurse Schedules (' . count($staffSchedules) . ' rows)');
if (!$staffSchedules) {
    analyticsPdfLine($pdf, 'Staff', 'No doctors or nurses are registered for this hospital.');
}
foreach ($staffSchedules as $schedule) {
    analyticsPdfSection($pdf, ucfirst($schedule['role']) . ' - ' . $schedule['full_name'] . ' (User #' . $schedule['user_id'] . ')');
    analyticsPdfLine($pdf, 'Staff record ID', $schedule['staff_id'] ?? 'No linked staff record');
    analyticsPdfLine($pdf, 'Department / availability', ($schedule['department'] ?? ucfirst($schedule['role'])) . ' / ' . (isset($schedule['is_available']) ? ((int) $schedule['is_available'] ? 'Available' : 'Off duty') : 'Rostered'));
    analyticsPdfLine($pdf, 'Email / license', $schedule['work_email'] . ' / ' . ($schedule['license_number'] ?? 'Not recorded'));
    analyticsPdfLine($pdf, 'Regular duty / timezone', trim(($schedule['duty_start'] ?? 'Not set') . ' - ' . ($schedule['duty_end'] ?? 'Not set') . ' / ' . ($schedule['duty_timezone'] ?? 'Not set')));
    analyticsPdfLine($pdf, 'Dated shift', empty($schedule['shift_id']) ? 'No dated shift in selected period' : $schedule['shift_date'] . ' ' . ($schedule['shift_start'] ?? 'Not set') . ' - ' . ($schedule['shift_end'] ?? 'Not set') . ' (' . $schedule['shift_status'] . ')');
}

analyticsPdfSection($pdf, 'Facility Resources - Current Snapshot');
if (!$facilityResources) {
    analyticsPdfLine($pdf, 'Resources', 'No facility resource metrics are configured.');
}
foreach ($facilityResources as $resource) {
    analyticsPdfSection($pdf, ucwords(str_replace('_', ' ', $resource['metric_key'])));
    analyticsPdfLine($pdf, 'Available / maximum', $resource['current_value'] . ' / ' . $resource['max_value']);
    analyticsPdfLine($pdf, 'Status / last updated', ($resource['status_label'] ?? 'Not labeled') . ' / ' . $resource['updated_at']);
}

analyticsPdfSection($pdf, 'Analytics Summary - ' . $periodLabel);
analyticsPdfLine($pdf, 'Total incidents', $metrics['totalIncidents'] ?? 0);
analyticsPdfLine($pdf, 'Average response time', number_format((float) ($metrics['responseTime'] ?? 0), 1) . ' minutes');
analyticsPdfLine($pdf, 'Referral rate', (int) ($metrics['referralRate'] ?? 0) . '%');
analyticsPdfLine($pdf, 'Average bed occupancy', (int) ($metrics['bedOccupancy'] ?? 0) . '%');

analyticsPdfSection($pdf, 'Incidents by Type');
$incidentTotal = array_sum(array_map(static function (array $item): int {
    return (int) ($item['total'] ?? 0);
}, $incidentTypes));
foreach ($incidentTypes as $type) {
    $share = $incidentTotal > 0 ? round(((int) $type['total'] / $incidentTotal) * 100) : 0;
    analyticsPdfLine($pdf, $type['label'], $type['total'] . ' incidents (' . $share . '%)');
}
if (!$incidentTypes) {
    analyticsPdfLine($pdf, 'Incident types', 'No incidents in this period.');
}

analyticsPdfSection($pdf, 'Top Barangays by Volume');
foreach ($topLocations as $location) {
    analyticsPdfLine($pdf, $location['label'], $location['total'] . ' cases');
}
if (!$topLocations) {
    analyticsPdfLine($pdf, 'Locations', 'No incidents in this period.');
}

analyticsPdfSection($pdf, 'Fleet Utilization');
analyticsPdfLine($pdf, 'Average dispatch time', number_format((float) ($fleetMetrics['avgDispatchTime'] ?? 0), 1) . ' minutes');
analyticsPdfLine($pdf, 'Average transit time', number_format((float) ($fleetMetrics['avgTransitTime'] ?? ($fleetMetrics['avgDispatchTime'] ?? 0)), 1) . ' minutes');
analyticsPdfLine($pdf, 'Units active today', (int) ($fleetMetrics['activeUnits'] ?? 0) . ' / ' . (int) ($fleetMetrics['totalUnits'] ?? 0));

analyticsPdfSection($pdf, 'Response Performance Trend');
analyticsPdfLine($pdf, 'Measure', 'Average incident ETA by reported date (minutes)');
analyticsPdfDrawTrend($pdf, $trend);

$reportName = $reportIsDemographics ? 'reddiph-demographics-report-' : 'reddiph-analytics-report-';
$pdf->Output('D', $reportName . $from . '-to-' . $to . '.pdf');
exit;