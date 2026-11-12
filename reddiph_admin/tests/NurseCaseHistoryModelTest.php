<?php

require_once __DIR__ . '/../app/models/NurseCaseHistoryModel.php';
require_once __DIR__ . '/../app/models/NurseNotifiedCasesModel.php';

final class NurseHistoryFakePdo extends PDO
{
    public array $executed = [];
    private int $statementIndex = 0;
    private bool $transaction = false;

    public function __construct(private array $results)
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $result = $this->results[$this->statementIndex++] ?? [];
        return new NurseHistoryFakeStatement($this, $query, $result);
    }

    public function beginTransaction(): bool
    {
        $this->transaction = true;
        return true;
    }

    public function commit(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }
}

final class NurseHistoryFakeStatement extends PDOStatement
{
    public function __construct(
        private NurseHistoryFakePdo $connection,
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

function assertNurseHistory(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function setNurseHistoryConnection(NurseHistoryFakePdo $connection): void
{
    $property = new ReflectionProperty(Database::class, 'connection');
    $property->setValue(null, $connection);
}

$historyConnection = new NurseHistoryFakePdo([
    [
        'rows' => [[
            'incident_id' => 42,
            'incident_type' => 'Medical',
            'status' => 'resolved',
            'triage_category' => 'urgent',
            'patient_name' => 'Test Patient',
            'notified_at' => '2026-11-08 09:00:00',
        ]],
    ],
]);
setNurseHistoryConnection($historyConnection);
$history = NurseCaseHistoryModel::history(17, 'week');
assertNurseHistory(count($history) === 1, 'Expected one authorized nurse history case.');
assertNurseHistory($history[0]['id'] === 42, 'Incident identifier should be mapped.');
assertNurseHistory($history[0]['triageCategoryLabel'] === 'Urgent', 'Triage category should use the shared label.');
$historyQuery = $historyConnection->executed[0];
assertNurseHistory($historyQuery['params']['nurse_id'] === 17, 'History must bind the logged-in nurse id.');
assertNurseHistory(isset($historyQuery['params']['period_start'], $historyQuery['params']['period_end']), 'History filters should be parameterized.');
assertNurseHistory(str_contains($historyQuery['query'], "nurse.role = 'nurse'"), 'History must verify the nurse role.');
assertNurseHistory(str_contains($historyQuery['query'], "alert.recipient_role = 'nurse'"), 'History must restrict alerts to the nurse role.');
assertNurseHistory(str_contains($historyQuery['query'], 'alert.recipient_user_id = :nurse_id'), 'History must be scoped to this nurse recipient.');
assertNurseHistory(str_contains($historyQuery['query'], 'nurse.hospital_id = i.hospital_id'), 'History must enforce the nurse hospital boundary.');

$emptyConnection = new NurseHistoryFakePdo([['rows' => []]]);
setNurseHistoryConnection($emptyConnection);
assertNurseHistory(NurseCaseHistoryModel::history(18) === [], 'An account with no matching nurse notifications must see no cases.');

$activeCountConnection = new NurseHistoryFakePdo([['column' => 4]]);
setNurseHistoryConnection($activeCountConnection);
assertNurseHistory(
    NurseNotifiedCasesModel::activeCount(17) === 4,
    'Active notified count should be returned for the authenticated nurse.'
);
$activeCountQuery = $activeCountConnection->executed[0];
assertNurseHistory(str_contains($activeCountQuery['query'], 'COUNT(DISTINCT i.incident_id)'), 'Active totals must count unique incidents.');
assertNurseHistory(str_contains($activeCountQuery['query'], "i.status IN ('open', 'dispatched', 'referred')"), 'Active totals must exclude resolved incidents.');
assertNurseHistory(str_contains($activeCountQuery['query'], "nurse.role = 'nurse'"), 'Active totals must verify the Nurse role.');
assertNurseHistory(str_contains($activeCountQuery['query'], 'alert.recipient_user_id = nurse.id'), 'Active totals must use the Nurse notification relationship.');
assertNurseHistory(str_contains($activeCountQuery['query'], "alert.recipient_role = 'nurse'"), 'Active totals must restrict alerts to the Nurse role.');
assertNurseHistory(str_contains($activeCountQuery['query'], "alert.channel = 'admin'"), 'Active totals must count admin notifications only.');
assertNurseHistory(str_contains($activeCountQuery['query'], 'nurse.hospital_id = i.hospital_id'), 'Active totals must enforce the Nurse hospital boundary.');
assertNurseHistory(str_contains($activeCountQuery['query'], '(i.hospital_id IS NULL AND nurse.hospital_name = i.hospital_name)'), 'Active totals must preserve the Nurse hospital-name fallback.');
assertNurseHistory($activeCountQuery['params']['nurse_id'] === 17, 'Active totals must bind the authenticated Nurse id.');
assertNurseHistory(!str_contains($activeCountQuery['query'], 'doctor.'), 'Nurse active totals must not use Doctor-specific conditions.');
assertNurseHistory(!str_contains(strtoupper($activeCountQuery['query']), 'LIMIT'), 'Active totals must not be restricted by case-history pagination.');

$statsConnection = new NurseHistoryFakePdo([
    ['row' => ['received' => 2, 'resolved' => 1]],
]);
setNurseHistoryConnection($statsConnection);
$stats = NurseCaseHistoryModel::stats(17, 'month', 4);
assertNurseHistory($stats['totalNotified'] === 4 && $stats['received'] === 2, 'Active summary should use the all-status-independent active count.');
assertNurseHistory($stats['resolvedPercent'] === '50%', 'Resolved percentage should use the selected period.');
assertNurseHistory($statsConnection->executed[0]['params']['nurse_id'] === 17, 'Summary queries must bind the logged-in nurse id.');
assertNurseHistory(count($statsConnection->executed) === 1, 'Summary statistics should not repeat the active-count query.');

$nurseHistoryView = file_get_contents(__DIR__ . '/../app/views/NurseCaseHistoryView.php');
$nurseHistoryController = file_get_contents(__DIR__ . '/../app/controllers/NurseCaseHistoryController.php');
$nurseHistoryScript = file_get_contents(__DIR__ . '/../public/js/NurseCaseHistory.js');
assertNurseHistory(str_contains($nurseHistoryView, 'Active Notified Cases'), 'The Nurse history summary should identify active notified cases.');
assertNurseHistory(str_contains($nurseHistoryController, 'NurseNotifiedCasesModel::activeCount($nurseId)'), 'Both rendered and refreshed summaries should query the active Nurse count.');
assertNurseHistory(str_contains($nurseHistoryScript, 'result.stats.totalNotified'), 'The Nurse history script should display the backend active-count field.');

$clinicalConnection = new NurseHistoryFakePdo([
    ['row' => ['incident_id' => 42]],
    [],
    [],
]);
setNurseHistoryConnection($clinicalConnection);
assertNurseHistory(
    NurseNotifiedCasesModel::updateClinicalRecord(17, 42, [
        'heartRate' => '88',
        'bloodPressure' => '120/80',
        'spo2' => '98',
        'gcs' => '15',
        'nurseNote' => 'Patient is alert.',
    ]),
    'An incident notified to the nurse should accept a clinical record update.'
);
$clinicalQuery = $clinicalConnection->executed[1]['query'];
assertNurseHistory(str_contains($clinicalQuery, 'i.nurse_heart_rate = :heart_rate'), 'Clinical updates should retain vital signs.');
assertNurseHistory(str_contains($clinicalQuery, 'i.nurse_notes = :nurse_notes'), 'Clinical updates should retain nurse notes.');
assertNurseHistory(!str_contains($clinicalQuery, 'nurse_bed_id'), 'Clinical updates must not change Nurse bed assignments.');
assertNurseHistory(!str_contains($clinicalQuery, 'hospital_beds'), 'Clinical updates must not change bed inventory status.');
assertNurseHistory(!str_contains($clinicalQuery, 'nurse_prep_completed'), 'Clinical updates must not update checklist completion fields.');
assertNurseHistory(!str_contains($clinicalConnection->executed[0]['query'], 'nurse_bed_id'), 'Clinical authorization must not depend on bed assignment.');
assertNurseHistory(str_contains($clinicalConnection->executed[2]['query'], 'audit_logs'), 'Clinical updates should retain audit logging.');

$nurseCasesView = file_get_contents(__DIR__ . '/../app/views/NurseNotifiedCasesView.php');
$nurseCasesController = file_get_contents(__DIR__ . '/../app/controllers/NurseNotifiedCasesController.php');
$nurseCasesModel = file_get_contents(__DIR__ . '/../app/models/NurseNotifiedCasesModel.php');
assertNurseHistory(!str_contains($nurseCasesView, 'bed_id'), 'Notified Cases UI must not expose Nurse bed assignment controls.');
assertNurseHistory(!str_contains($nurseCasesView, 'nurseBeds'), 'Notified Cases UI must not load Nurse bed-status data.');
assertNurseHistory(!str_contains($nurseCasesController, 'bedAvailability'), 'Notified Cases API must not serve Nurse bed availability.');
assertNurseHistory(!str_contains($nurseCasesController, 'bed_id'), 'Notified Cases API must not accept bed assignment input.');
assertNurseHistory(!str_contains($nurseCasesModel, 'nurse_bed_id'), 'Notified Cases model must not read or write Nurse bed assignments.');
assertNurseHistory(!str_contains($nurseCasesModel, 'hospital_beds'), 'Notified Cases model must not manage bed inventory status.');

echo "NurseCaseHistoryModelTest passed.\n";
