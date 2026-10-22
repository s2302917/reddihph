<?php
// CLI test for false-report warning escalation policy.

declare(strict_types=1);

require __DIR__ . '/../app/models/FalseReportModel.php';

$firstWarning = FalseReportPolicy::confirmed(0, false, null);
$firstWarningWithBan = FalseReportPolicy::confirmed(0, true, 12);
$secondWarning = FalseReportPolicy::confirmed(1, false, 4);
$thirdWarning = FalseReportPolicy::confirmed(2, false, 30);
$missingDurationRejected = false;
try {
    FalseReportPolicy::confirmed(1, false, null);
} catch (InvalidArgumentException) {
    $missingDurationRejected = true;
}
$outOfRangeDurationRejected = false;
try {
    FalseReportPolicy::confirmed(0, true, 366);
} catch (InvalidArgumentException) {
    $outOfRangeDurationRejected = true;
}

$cases = [
    'first confirmed warning does not ban by default' =>
        $firstWarning === ['warning_number' => 1, 'ban_days' => null, 'automatic_ban' => false],
    'admin can choose a first-warning ban duration' =>
        $firstWarningWithBan === ['warning_number' => 1, 'ban_days' => 12, 'automatic_ban' => false],
    'later confirmed reports require and use the chosen ban duration' =>
        $secondWarning === ['warning_number' => 2, 'ban_days' => 4, 'automatic_ban' => true],
    'duration is selected separately for each later ban' =>
        $thirdWarning === ['warning_number' => 3, 'ban_days' => 30, 'automatic_ban' => true],
    'later warning requires a ban duration' => $missingDurationRejected,
    'ban duration is limited to 365 days' => $outOfRangeDurationRejected,
];

$failed = 0;
foreach ($cases as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
exit($failed === 0 ? 0 : 1);
