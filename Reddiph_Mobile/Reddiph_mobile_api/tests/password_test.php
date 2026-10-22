<?php
// CLI test for PasswordModel validation: php Reddiph_mobile_api/tests/password_test.php

declare(strict_types=1);

require __DIR__ . '/../app/models/PasswordModel.php';

$generate = new ReflectionMethod(PasswordModel::class, 'generateTemporaryPassword');
$temporary = $generate->invoke(null);

$cases = [
    'short password is rejected' => PasswordModel::validateNewPassword('short', 'short') !== [],
    'over-long password is rejected' => PasswordModel::validateNewPassword(str_repeat('a', 73), str_repeat('a', 73)) !== [],
    'mismatched confirmation is rejected' => PasswordModel::validateNewPassword('Longenough!1', 'Different!1') !== [],
    'valid complex password passes' => PasswordModel::validateNewPassword('Longenough!1', 'Longenough!1') === [],
    'missing uppercase is rejected' => PasswordModel::validateNewPassword('longenough!1', 'longenough!1') !== [],
    'missing lowercase is rejected' => PasswordModel::validateNewPassword('LONGENOUGH!1', 'LONGENOUGH!1') !== [],
    'missing special character is rejected' => PasswordModel::validateNewPassword('Longenough11', 'Longenough11') !== [],
    'temporary password is 12 characters' => strlen($temporary) === 12,
    'temporary password passes its own validation' => PasswordModel::validateNewPassword($temporary, $temporary) === [],
    'temporary passwords differ' => $temporary !== $generate->invoke(null),
];

$failed = 0;
foreach ($cases as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
exit($failed === 0 ? 0 : 1);
