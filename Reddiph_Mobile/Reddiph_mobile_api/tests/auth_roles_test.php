<?php
// CLI test for mobile authentication roles.

declare(strict_types=1);

require __DIR__ . '/../app/models/UserModel.php';

$cases = [
    'barangay_admin is an allowed mobile login role' => in_array('barangay_admin', UserModel::ROLES, true),
    'hospital_admin remains a distinct unsupported mobile role' => !in_array('hospital_admin', UserModel::ROLES, true),
    'unsupported mobile role is rejected before database access' =>
        UserModel::authenticate('admin@example.com', 'password', 'hospital_admin') === null,
];

$failed = 0;
foreach ($cases as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
exit($failed === 0 ? 0 : 1);
