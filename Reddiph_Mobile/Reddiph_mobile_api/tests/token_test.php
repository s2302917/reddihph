<?php
// CLI test for TokenService: php Reddiph_mobile_api/tests/token_test.php
// Uses the DB_PASS environment variable as a stand-in signing secret.

declare(strict_types=1);

putenv('DB_PASS=test-only-secret');
require __DIR__ . '/../app/services/TokenService.php';

$expired = '5.' . (time() - 10);
$cases = [
    'valid signature returns the user id' => TokenService::verifySignature(TokenService::issue(5)) === 5,
    'tampered user id is rejected' => TokenService::verifySignature(preg_replace('/^5\./', '6.', TokenService::issue(5))) === null,
    'tampered signature is rejected' => TokenService::verifySignature(TokenService::issue(5) . 'x') === null,
    'expired token is rejected' => TokenService::verifySignature($expired . '.' . hash_hmac('sha256', $expired, Database::tokenSecret())) === null,
    'garbage is rejected' => TokenService::verifySignature('abc') === null && TokenService::verifySignature(null) === null,
];

$failed = 0;
foreach ($cases as $name => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
exit($failed === 0 ? 0 : 1);
