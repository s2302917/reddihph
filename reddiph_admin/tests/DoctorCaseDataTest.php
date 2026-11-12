<?php

require_once __DIR__ . '/../app/models/DoctorCaseHistoryModel.php';
require_once __DIR__ . '/../app/models/DoctorNotifiedCasesModel.php';

final class DoctorDataFakePdo extends PDO
{
    public array $executed = [];
    private int $statementIndex = 0;

    public function __construct(private array $results)
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $result = $this->results[$this->statementIndex++] ?? [];
        return new DoctorDataFakeStatement($this, $query, $result);
    }
}

final class DoctorDataFakeStatement extends PDOStatement
{
    public function __construct(
        private DoctorDataFakePdo $connection,
        private string $query,
        private array $result
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $this->connection->executed[] = ['query' => $this->query, 'params' => $params ?? []];
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->result['rows'] ?? [];
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->result['row'] ?? false;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->result['column'] ?? false;
    }
}

function assertDoctorCaseData(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function setDoctorDataConnection(DoctorDataFakePdo $connection): void
{
    $property = new ReflectionProperty(Database::class, 'connection');
    $property->setValue(null, $connection);
}

$activeRows = [];
foreach ([
    'emergent',
    'emergent',
    'urgent',
    'non_urgent',
    'expectant',
    null,
    null,
] as $index => $category) {
    $activeRows[] = [
        'incident_id' => $index + 1,
        'incident_type' => 'Incident ' . ($index + 1),
        'status' => 'open',
        'triage_category' => $category,
        'location' => 'Test location',
        'patient_name' => '',
        'patient_gender' => 'unknown',
        'patient_age' => null,
        'eta_minutes' => null,
        'responder_name' => '',
        'hospital_name' => 'Test Hospital',
        'reported_at' => '2026-11-08 08:00:00',
        'resolved_at' => null,
        'notes' => '',
        'registered_hospital' => 'Test Hospital',
    ];
}

$activeConnection = new DoctorDataFakePdo([['rows' => $activeRows]]);
setDoctorDataConnection($activeConnection);
$activeCases = DoctorNotifiedCasesModel::cases(44);
$activeSummary = DoctorNotifiedCasesModel::summary(44, $activeCases);
$activeQuery = $activeConnection->executed[0];
assertDoctorCaseData(count($activeCases) === 7, 'Every active notified case should be returned.');
assertDoctorCaseData($activeSummary === [
    'all' => 7,
    'emergent' => 2,
    'urgent' => 1,
    'non_urgent' => 1,
    'expectant' => 1,
], 'Active summary counts should be derived from the complete active case list.');
assertDoctorCaseData(count($activeConnection->executed) === 1, 'Summary should not issue a second query when cases are already loaded.');
assertDoctorCaseData(str_contains($activeQuery['query'], "i.status IN ('open', 'dispatched', 'referred')"), 'Active cases must exclude resolved incidents.');
assertDoctorCaseData(str_contains($activeQuery['query'], 'alert.recipient_user_id = doctor.id'), 'Active cases must be scoped to the logged-in Doctor alert recipient.');
assertDoctorCaseData(str_contains($activeQuery['query'], 'doctor.hospital_id = i.hospital_id'), 'Active cases must enforce the Doctor hospital boundary.');
assertDoctorCaseData(!str_contains($activeQuery['query'], 'incident_patients'), 'Active cases must not require a separate patient child row.');

$activeCountConnection = new DoctorDataFakePdo([['column' => 7]]);
setDoctorDataConnection($activeCountConnection);
assertDoctorCaseData(DoctorNotifiedCasesModel::activeCount(44) === 7, 'Active total should count distinct active notified incidents.');
$activeCountQuery = $activeCountConnection->executed[0];
assertDoctorCaseData(str_contains($activeCountQuery['query'], 'COUNT(DISTINCT i.incident_id)'), 'Active totals must count unique incidents.');
assertDoctorCaseData(str_contains($activeCountQuery['query'], "i.status IN ('open', 'dispatched', 'referred')"), 'Active totals must use the Notified Cases status scope.');
assertDoctorCaseData(str_contains($activeCountQuery['query'], 'alert.recipient_user_id = doctor.id'), 'Active totals must use the Doctor notification relationship.');
assertDoctorCaseData(str_contains($activeCountQuery['query'], 'doctor.hospital_id = i.hospital_id'), 'Active totals must enforce the Doctor hospital boundary.');
assertDoctorCaseData($activeCountQuery['params']['doctor_id'] === 44, 'Active totals must bind the authenticated Doctor id.');

$historyConnection = new DoctorDataFakePdo([
    ['rows' => [[
        'incident_id' => 51,
        'incident_type' => 'Test incident',
        'status' => 'resolved',
        'triage_category' => 'urgent',
        'patient_name' => 'Test patient',
        'notified_at' => '2026-11-08 09:00:00',
    ]]],
]);
setDoctorDataConnection($historyConnection);
$history = DoctorCaseHistoryModel::history(44, 'week');
$historyQuery = $historyConnection->executed[0];
assertDoctorCaseData($history[0]['date'] === 'Nov 8, 2026 9:00 AM', 'History dates should display the actual notification timestamp.');
assertDoctorCaseData($history[0]['status'] === 'resolved', 'Historical resolved cases should remain in the history list.');
assertDoctorCaseData(str_contains($historyQuery['query'], 'MIN(alert.created_at) AS notified_at'), 'History should deduplicate notification rows using the earliest notification timestamp.');
assertDoctorCaseData(str_contains($historyQuery['query'], 'GROUP BY alert.incident_id'), 'History should return one row per notified incident.');
assertDoctorCaseData(str_contains($historyQuery['query'], 'notification.notified_at >= :period_start'), 'History period filters should use notification time.');
assertDoctorCaseData(!str_contains($historyQuery['query'], 'i.reported_at'), 'History should not substitute the incident report time for notification time.');
assertDoctorCaseData($historyQuery['params']['doctor_id'] === 44, 'History must bind the authenticated Doctor id.');
assertDoctorCaseData($historyQuery['params']['notification_doctor_id'] === 44, 'Notification lookup must bind the authenticated Doctor id.');
assertDoctorCaseData(str_contains($historyQuery['query'], 'doctor.hospital_id = i.hospital_id'), 'History must enforce the Doctor hospital boundary.');

$statsConnection = new DoctorDataFakePdo([
    ['row' => ['received' => 5, 'resolved' => 1]],
]);
setDoctorDataConnection($statsConnection);
$stats = DoctorCaseHistoryModel::stats(44, 'month', 7);
assertDoctorCaseData($stats['totalNotified'] === 7, 'History active notified total should match the active Notified Cases scope.');
assertDoctorCaseData($stats['received'] === 5 && $stats['resolved'] === 1, 'Period history statistics should retain their status-aware totals.');
assertDoctorCaseData(count($statsConnection->executed) === 1, 'History stats should not issue a redundant total-count query.');
assertDoctorCaseData(!str_contains($statsConnection->executed[0]['query'], "i.status IN ('open', 'dispatched', 'referred')"), 'Historical received and resolved statistics should continue including all incident statuses.');

$view = file_get_contents(__DIR__ . '/../app/views/DoctorCaseHistoryView.php');
$script = file_get_contents(__DIR__ . '/../public/js/script.js');
$controller = file_get_contents(__DIR__ . '/../app/controllers/DoctorCaseHistoryController.php');
assertDoctorCaseData(str_contains($view, 'DATE NOTIFIED'), 'The history table should label its timestamp Date Notified.');
assertDoctorCaseData(str_contains($view, 'Active notified cases'), 'The Case History total should clearly identify its active-case scope.');
assertDoctorCaseData(!str_contains($view, '<select'), 'History category and lifecycle values should be display-only.');
assertDoctorCaseData(!str_contains($script, '.history-status-select') && !str_contains($script, '.history-category-select'), 'History refresh should not recreate editable dropdowns.');
assertDoctorCaseData(!str_contains($controller, 'updateStatus'), 'The display-only history values should not expose an update action.');

echo "DoctorCaseDataTest passed.\n";
