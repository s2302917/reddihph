<?php

require_once __DIR__ . '/../config/config.php';
requireAuthenticated('hospital_admin');
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/models/HospitalReportModel.php';
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
    exit('A valid report date range is required.');
}

$from = $fromDate->format('Y-m-d');
$to = $toDate->format('Y-m-d');
$hospitalName = (string) ($_SESSION['hospital_name'] ?? 'Hospital');
$periodLabel = $fromDate->format('M j, Y') . ' - ' . $toDate->format('M j, Y');
$model = new HospitalReportModel();
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

function hospitalReportPdfText(string $value): string
{
    $converted = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $value);
    return $converted === false ? '' : $converted;
}

function hospitalReportPdfLine(FPDF $pdf, string $label, $value): void
{
    $text = $value === null || $value === '' ? 'Not recorded' : (string) $value;
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Write(5, hospitalReportPdfText($label . ': '));
    $pdf->SetFont('Arial', '', 8);
    $pdf->MultiCell(0, 5, hospitalReportPdfText($text));
}

function hospitalReportPdfSection(FPDF $pdf, string $title): void
{
    if ($pdf->GetY() > 175) {
        $pdf->AddPage();
    }
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(125, 0, 0);
    $pdf->Cell(0, 8, hospitalReportPdfText($title), 0, 1);
    $pdf->SetTextColor(25, 25, 25);
}

function hospitalReportPdfBar(FPDF $pdf, string $label, int $value, int $percentage, string $color): void
{
    hospitalReportPdfLine($pdf, $label, $value . ' (' . $percentage . '%)');
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

function hospitalReportPdfDrawTrend(FPDF $pdf, array $rows): void
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
            $pdf->Cell(16, 4, hospitalReportPdfText((string) ($row['label'] ?? '')), 0, 0, 'C');
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
$pdf->SetTitle('Hospital Logs and Demographics Report');
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 9, hospitalReportPdfText($hospitalName), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 7, 'Complete Logs, Demographics, and Operations Report', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 6, hospitalReportPdfText('Report period: ' . $periodLabel . ' | Current hospital operational snapshots included'), 0, 1, 'C');
$pdf->Cell(0, 6, 'Generated ' . date('M j, Y g:i A'), 0, 1, 'C');

hospitalReportPdfSection($pdf, 'Demographic Summary - ' . $periodLabel);
hospitalReportPdfLine($pdf, 'Active alerts', $demographicsSummary['activeAlerts'] ?? 0);
hospitalReportPdfLine($pdf, 'Resolved in range', $demographicsSummary['resolvedToday'] ?? 0);
hospitalReportPdfLine($pdf, 'Patients logged', $demographicsSummary['patientsLogged'] ?? 0);
hospitalReportPdfLine($pdf, 'High-risk cases', $demographicsSummary['highRiskCases'] ?? 0);

hospitalReportPdfSection($pdf, 'Emergency Logs (' . count($demographicsLogs) . ' records)');
if (!$demographicsLogs) {
    hospitalReportPdfLine($pdf, 'Logs', 'No incidents were logged in this period.');
}
foreach ($demographicsLogs as $log) {
    hospitalReportPdfSection($pdf, '#' . $log['incidentId'] . ' - ' . $log['time'] . ' - ' . $log['type'] . ' (' . $log['status'] . ')');
    hospitalReportPdfLine($pdf, 'Patient name', $log['patient']);
    hospitalReportPdfLine($pdf, 'Caller', $log['caller']);
    hospitalReportPdfLine($pdf, 'Last updated', $log['updatedAt']);
    hospitalReportPdfLine($pdf, 'Location / assigned to', $log['location'] . ' / ' . $log['assigned']);
}

hospitalReportPdfSection($pdf, 'Priority Updates');
foreach ($priorityUpdates as $update) {
    hospitalReportPdfLine($pdf, $update['title'] ?? 'Update', $update['message'] ?? '');
}
if (!$priorityUpdates) {
    hospitalReportPdfLine($pdf, 'Priority updates', 'No priority updates for this period.');
}

hospitalReportPdfSection($pdf, 'Age Groups');
if (!$ageGroups) {
    hospitalReportPdfLine($pdf, 'Age groups', 'No age data was recorded in this period.');
}
foreach ($ageGroups as $index => $group) {
    $ageColors = ['#8b1c22', '#b94b4d', '#d39790', '#9bb7bb'];
    hospitalReportPdfBar($pdf, $group['label'], (int) $group['value'], (int) ($group['percentage'] ?? 0), $ageColors[$index % count($ageColors)]);
}

hospitalReportPdfSection($pdf, 'Gender Distribution');
if (!$genderBreakdown) {
    hospitalReportPdfLine($pdf, 'Gender', 'No gender data was recorded in this period.');
}
foreach ($genderBreakdown as $gender) {
    $percentage = (int) ($gender['value'] ?? 0);
    hospitalReportPdfBar($pdf, $gender['label'], $percentage, $percentage, $gender['color'] ?? '#8b1c22');
}

hospitalReportPdfSection($pdf, 'Top Locations');
if (!$demographicsLocations) {
    hospitalReportPdfLine($pdf, 'Locations', 'No location data was recorded in this period.');
}
foreach ($demographicsLocations as $location) {
    hospitalReportPdfBar($pdf, $location['label'], (int) $location['value'], (int) ($location['percentage'] ?? 0), '#8b1c22');
}

hospitalReportPdfSection($pdf, 'Incident Reports (' . count($incidents) . ')');
if (!$incidents) {
    hospitalReportPdfLine($pdf, 'Incidents', 'No incidents were reported in this period.');
}
foreach ($incidents as $incident) {
    hospitalReportPdfSection($pdf, '#' . $incident['incident_id'] . ' - ' . $incident['incident_type']);
    hospitalReportPdfLine($pdf, 'Status', $incident['status']);
    hospitalReportPdfLine($pdf, 'Patient', trim(($incident['patient_name'] ?? 'Name not recorded') . ' | ' . ($incident['patient_gender'] ?? 'Unknown') . ' | Age ' . ($incident['patient_age'] ?? 'Unknown')));
    hospitalReportPdfLine($pdf, 'Location', $incident['location']);
    hospitalReportPdfLine($pdf, 'Barangay ID / hospital ID', ($incident['barangay_id'] ?? 'Not assigned') . ' / ' . ($incident['hospital_id'] ?? 'Not assigned'));
    hospitalReportPdfLine($pdf, 'Coordinates', ($incident['latitude'] ?? '') . ', ' . ($incident['longitude'] ?? ''));
    hospitalReportPdfLine($pdf, 'Hospital', $incident['hospital_name']);
    hospitalReportPdfLine($pdf, 'Responder', $incident['responder_name']);
    hospitalReportPdfLine($pdf, 'Transport', trim(($incident['transport_name'] ?? 'Not assigned') . ' | ' . ($incident['transport_type'] ?? '')));
    hospitalReportPdfLine($pdf, 'Driver', $incident['driver_name']);
    hospitalReportPdfLine($pdf, 'ETA', $incident['eta_minutes'] === null ? null : $incident['eta_minutes'] . ' minutes');
    hospitalReportPdfLine($pdf, 'Reported / resolved', $incident['reported_at'] . ' / ' . ($incident['resolved_at'] ?? 'Not resolved'));
    hospitalReportPdfLine($pdf, 'Created / updated', $incident['created_at'] . ' / ' . $incident['updated_at']);
    hospitalReportPdfLine($pdf, 'Notes', $incident['notes']);
}

hospitalReportPdfSection($pdf, 'Ambulance and PTV Fleet (' . count($fleetInventory) . ' vehicles) - Current Snapshot');
if (!$fleetInventory) {
    hospitalReportPdfLine($pdf, 'Fleet', 'No vehicles registered for this hospital.');
}
foreach ($fleetInventory as $vehicle) {
    hospitalReportPdfSection($pdf, $vehicle['call_sign'] . ' (' . $vehicle['vehicle_type'] . ') - ID ' . $vehicle['id']);
    hospitalReportPdfLine($pdf, 'Status / plate', $vehicle['status'] . ' / ' . ($vehicle['plate_number'] ?? 'Not recorded'));
    hospitalReportPdfLine($pdf, 'Driver / assignment', ($vehicle['driver_name'] ?? 'Not assigned') . ' / ' . ($vehicle['assignment_note'] ?? 'No assignment note'));
    hospitalReportPdfLine($pdf, 'ETA / arrived', ($vehicle['eta_minutes'] === null ? 'Not recorded' : $vehicle['eta_minutes'] . ' minutes') . ' / ' . ($vehicle['arrived_at'] ?? 'Not recorded'));
    hospitalReportPdfLine($pdf, 'Coordinates', ($vehicle['latitude'] ?? 'Not recorded') . ', ' . ($vehicle['longitude'] ?? 'Not recorded'));
    hospitalReportPdfLine($pdf, 'Location recorded / created / updated', ($vehicle['location_recorded_at'] ?? 'Not recorded') . ' / ' . $vehicle['created_at'] . ' / ' . $vehicle['updated_at']);
}

hospitalReportPdfSection($pdf, 'Doctor and Nurse Schedules (' . count($staffSchedules) . ' rows)');
if (!$staffSchedules) {
    hospitalReportPdfLine($pdf, 'Staff', 'No doctors or nurses are registered for this hospital.');
}
foreach ($staffSchedules as $schedule) {
    hospitalReportPdfSection($pdf, ucfirst($schedule['role']) . ' - ' . $schedule['full_name'] . ' (User #' . $schedule['user_id'] . ')');
    hospitalReportPdfLine($pdf, 'Staff record ID', $schedule['staff_id'] ?? 'No linked staff record');
    hospitalReportPdfLine($pdf, 'Department / availability', ($schedule['department'] ?? ucfirst($schedule['role'])) . ' / ' . (isset($schedule['is_available']) ? ((int) $schedule['is_available'] ? 'Available' : 'Off duty') : 'Rostered'));
    hospitalReportPdfLine($pdf, 'Email / license', $schedule['work_email'] . ' / ' . ($schedule['license_number'] ?? 'Not recorded'));
    hospitalReportPdfLine($pdf, 'Regular duty / timezone', trim(($schedule['duty_start'] ?? 'Not set') . ' - ' . ($schedule['duty_end'] ?? 'Not set') . ' / ' . ($schedule['duty_timezone'] ?? 'Not set')));
    hospitalReportPdfLine($pdf, 'Dated shift', empty($schedule['shift_id']) ? 'No dated shift in selected period' : $schedule['shift_date'] . ' ' . ($schedule['shift_start'] ?? 'Not set') . ' - ' . ($schedule['shift_end'] ?? 'Not set') . ' (' . $schedule['shift_status'] . ')');
}

hospitalReportPdfSection($pdf, 'Facility Resources - Current Snapshot');
if (!$facilityResources) {
    hospitalReportPdfLine($pdf, 'Resources', 'No facility resource metrics are configured.');
}
foreach ($facilityResources as $resource) {
    hospitalReportPdfSection($pdf, ucwords(str_replace('_', ' ', $resource['metric_key'])));
    hospitalReportPdfLine($pdf, 'Available / maximum', $resource['current_value'] . ' / ' . $resource['max_value']);
    hospitalReportPdfLine($pdf, 'Status / last updated', ($resource['status_label'] ?? 'Not labeled') . ' / ' . $resource['updated_at']);
}

hospitalReportPdfSection($pdf, 'Operations Summary - ' . $periodLabel);
hospitalReportPdfLine($pdf, 'Total incidents', $metrics['totalIncidents'] ?? 0);
hospitalReportPdfLine($pdf, 'Average response time', number_format((float) ($metrics['responseTime'] ?? 0), 1) . ' minutes');
hospitalReportPdfLine($pdf, 'Referral rate', (int) ($metrics['referralRate'] ?? 0) . '%');
hospitalReportPdfLine($pdf, 'Average bed occupancy', (int) ($metrics['bedOccupancy'] ?? 0) . '%');

hospitalReportPdfSection($pdf, 'Incidents by Type');
$incidentTotal = array_sum(array_map(static function (array $item): int {
    return (int) ($item['total'] ?? 0);
}, $incidentTypes));
foreach ($incidentTypes as $type) {
    $share = $incidentTotal > 0 ? round(((int) $type['total'] / $incidentTotal) * 100) : 0;
    hospitalReportPdfLine($pdf, $type['label'], $type['total'] . ' incidents (' . $share . '%)');
}
if (!$incidentTypes) {
    hospitalReportPdfLine($pdf, 'Incident types', 'No incidents in this period.');
}

hospitalReportPdfSection($pdf, 'Top Barangays by Volume');
foreach ($topLocations as $location) {
    hospitalReportPdfLine($pdf, $location['label'], $location['total'] . ' cases');
}
if (!$topLocations) {
    hospitalReportPdfLine($pdf, 'Locations', 'No incidents in this period.');
}

hospitalReportPdfSection($pdf, 'Fleet Utilization');
hospitalReportPdfLine($pdf, 'Average dispatch time', number_format((float) ($fleetMetrics['avgDispatchTime'] ?? 0), 1) . ' minutes');
hospitalReportPdfLine($pdf, 'Average transit time', number_format((float) ($fleetMetrics['avgTransitTime'] ?? ($fleetMetrics['avgDispatchTime'] ?? 0)), 1) . ' minutes');
hospitalReportPdfLine($pdf, 'Units active today', (int) ($fleetMetrics['activeUnits'] ?? 0) . ' / ' . (int) ($fleetMetrics['totalUnits'] ?? 0));

hospitalReportPdfSection($pdf, 'Response Performance Trend');
hospitalReportPdfLine($pdf, 'Measure', 'Average incident ETA by reported date (minutes)');
hospitalReportPdfDrawTrend($pdf, $trend);

$pdf->Output('D', 'reddiph-demographics-report-' . $from . '-to-' . $to . '.pdf');
exit;