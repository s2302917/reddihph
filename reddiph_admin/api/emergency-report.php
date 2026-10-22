<?php

require_once __DIR__ . '/../config/config.php';
requireAuthenticated('hospital_admin');
require_once __DIR__ . '/../vendor/autoload.php';

if (!class_exists('FPDF')) {
    require_once __DIR__ . '/../vendor/setasign/fpdf/fpdf.php';
}

$range = $_GET['range'] ?? 'today';
$days = $range === 'today' ? 1 : max(1, min(365, (int) $range));
$fromDate = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
$hospitalId = (int) ($_SESSION['hospital_id'] ?? 0);
$hospitalName = (string) ($_SESSION['hospital_name'] ?? 'ReddiPH Hospital');
$facilityLabels = [
    'bed_capacity' => 'Available Beds',
    'ambulance_bay' => 'Ambulance 1',
    'ptv' => 'PTV',
];

$db = Database::connection();

// Report only normalized operational data already defined in reddiph_schema.sql.
$incidentQuery = $db->prepare("SELECT incident_id, incident_type, status, location, latitude, longitude,
        patient_name, patient_gender, patient_age, eta_minutes,
        transport_name, transport_type, responder_name, driver_name, hospital_name, notes, reported_at
    FROM incidents
    WHERE reported_at >= :from_date
      AND (:hospital_id = 0 OR hospital_id = :incident_hospital_id)
    ORDER BY reported_at DESC");
$incidentQuery->execute([
    ':from_date' => $fromDate,
    ':hospital_id' => $hospitalId,
    ':incident_hospital_id' => $hospitalId,
]);
$incidents = $incidentQuery->fetchAll();

$facilityQuery = $db->prepare("SELECT 'bed_capacity' AS metric_key,
        available_beds AS current_value, total_beds AS max_value, 1 AS sort_order
    FROM hospitals
    WHERE id = :bed_hospital_id
    UNION ALL
    SELECT 'ambulance_bay',
        COALESCE(SUM(vehicle_type = 'Ambulance' AND status = 'available'), 0),
        COUNT(CASE WHEN vehicle_type = 'Ambulance' THEN 1 END), 2
    FROM ambulances
    WHERE hospital_id = :ambulance_hospital_id
    UNION ALL
    SELECT 'ptv',
        COALESCE(SUM(vehicle_type = 'PTV' AND status = 'available'), 0),
        COUNT(CASE WHEN vehicle_type = 'PTV' THEN 1 END), 3
    FROM ambulances
    WHERE hospital_id = :ptv_hospital_id
    ORDER BY sort_order");
$facilityQuery->execute([
    ':bed_hospital_id' => $hospitalId,
    ':ambulance_hospital_id' => $hospitalId,
    ':ptv_hospital_id' => $hospitalId,
]);
$facilities = $facilityQuery->fetchAll();

function fitPdfText(FPDF $pdf, string $value, float $width): string
{
    $value = trim($value);
    if ($pdf->GetStringWidth($value) <= $width - 3) {
        return $value;
    }

    $suffix = '...';
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    for ($index = $length; $index > 0; $index--) {
        $part = function_exists('mb_substr')
            ? mb_substr($value, 0, $index)
            : substr($value, 0, $index);
        if ($pdf->GetStringWidth($part . $suffix) <= $width - 3) {
            return $part . $suffix;
        }
    }

    return $suffix;
}

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();
$pdf->SetTitle('ReddiPH Emergency Report');
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, iconv('UTF-8', 'windows-1252//TRANSLIT', $hospitalName), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, 'Emergency Dispatch Report', 0, 1, 'C');
$pdf->SetFont('Arial', '', 9);
$recordCount = count($incidents);
$recordLabel = $recordCount === 1 ? 'record' : 'records';
$pdf->Cell(0, 7, 'Period starting ' . date('F j, Y', strtotime($fromDate)) . ' | ' . $recordCount . ' ' . $recordLabel, 0, 1, 'C');
$pdf->Ln(5);

$columnWidths = [
    'Incident' => 42,
    'Status' => 22,
    'Location' => 45,
    'Patient' => 34,
    'Hospital' => 45,
    'Transport' => 35,
    'ETA' => 18,
    'Reported' => 27,
];

$pdf->SetFont('Arial', 'B', 8);
foreach (array_keys($columnWidths) as $heading) {
    $pdf->Cell($columnWidths[$heading], 7, $heading, 1, 0, 'C');
}
$pdf->Ln();
$pdf->SetFont('Arial', '', 8);
foreach ($incidents as $incident) {
    $patient = trim(($incident['patient_name'] ?? 'Unknown') . ' ' . ($incident['patient_gender'] ?? '') . ' ' . ($incident['patient_age'] ?? ''));
    $transportName = trim((string) ($incident['transport_name'] ?? ''));
    $transportType = trim((string) ($incident['transport_type'] ?? ''));
    $transport = $transportName && $transportType
        ? $transportName . ' (' . $transportType . ')'
        : ($transportName ?: $transportType);
    $transportResponder = trim(
        $transport
        . (!empty($incident['driver_name']) ? ' / Driver: ' . $incident['driver_name'] : '')
        . (!empty($incident['responder_name']) ? ' / Responder: ' . $incident['responder_name'] : '')
    ) ?: 'Unassigned';
    $values = [
        'Incident' => '#' . (int) $incident['incident_id'] . ' ' . $incident['incident_type'],
        'Status' => $incident['status'],
        'Location' => $incident['location'],
        'Patient' => $patient,
        'Hospital' => $incident['hospital_name'] ?: 'Unassigned',
        'Transport' => $transportResponder,
        'ETA' => $incident['eta_minutes'] !== null ? $incident['eta_minutes'] . ' min' : '-',
        'Reported' => date('M j H:i', strtotime($incident['reported_at'])),
    ];

    foreach ($values as $heading => $value) {
        $pdf->Cell($columnWidths[$heading], 8, iconv('UTF-8', 'windows-1252//TRANSLIT', fitPdfText($pdf, (string) $value, $columnWidths[$heading])), 1, 0, $heading === 'ETA' ? 'C' : 'L');
    }
    $pdf->Ln();

    $coordinates = ($incident['latitude'] !== null && $incident['longitude'] !== null)
        ? sprintf('%.8f, %.8f', $incident['latitude'], $incident['longitude'])
        : 'No coordinates';
    $pdf->SetFont('Arial', 'I', 7);
    $pdf->Cell(0, 5, iconv('UTF-8', 'windows-1252//TRANSLIT', 'Coordinates: ' . $coordinates), 0, 1, 'L');
    $details = 'Hospital: ' . ($incident['hospital_name'] ?: 'Unassigned')
        . ' | Transport: ' . ($transport ?: 'Unassigned')
        . ' | Driver: ' . ($incident['driver_name'] ?: 'Unassigned')
        . ' | Responder: ' . ($incident['responder_name'] ?: 'Unassigned');
    $pdf->SetFont('Arial', '', 7);
    $pdf->MultiCell(0, 4, iconv('UTF-8', 'windows-1252//TRANSLIT', $details), 0, 'L');
    $pdf->SetFont('Arial', '', 8);
}

$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, 'Dispatch notes', 0, 1);
$pdf->SetFont('Arial', '', 8);
foreach ($incidents as $incident) {
    if (!empty($incident['notes'])) {
        $pdf->MultiCell(0, 6, iconv('UTF-8', 'windows-1252//TRANSLIT', $incident['incident_type'] . ': ' . $incident['notes']), 0, 'L');
    }
}

$pdf->Ln(7);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 8, 'Facility Status', 0, 1);
$pdf->SetFont('Arial', '', 9);
foreach ($facilities as $facility) {
    $label = $facilityLabels[$facility['metric_key']] ?? ucwords(str_replace('_', ' ', $facility['metric_key']));
    $pdf->Cell(0, 7, $label . ': ' . $facility['current_value'] . '/' . $facility['max_value'], 0, 1);
}

$pdf->Output('D', 'reddiph-emergency-report-' . date('Y-m-d') . '.pdf');
exit;
