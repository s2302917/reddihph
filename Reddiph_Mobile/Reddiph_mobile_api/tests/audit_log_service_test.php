<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/services/AuditLogService.php';
require_once __DIR__ . '/../app/services/AuditActionCatalog.php';

final class AuditLogTestStatement extends PDOStatement
{
    public static array $executedParameters = [];

    public function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        self::$executedParameters = $params ?? [];
        return true;
    }
}

final class AuditLogTestPdo extends PDO
{
    public string $preparedSql = '';

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $this->preparedSql = $query;
        return new AuditLogTestStatement();
    }
}

$failures = 0;
$check = static function (string $name, bool $ok) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    $failures += $ok ? 0 : 1;
};

$pdo = new AuditLogTestPdo();
AuditLogService::record(
    $pdo,
    42,
    'incident.created',
    'incident',
    '314',
    ['source' => 'mobile_test']
);
$check(
    'writes are fixed to the mobile platform',
    str_contains($pdo->preparedSql, "'mobile', 'mobile'")
);
$check(
    'description and safe event fields are passed to the shared insert',
    AuditLogTestStatement::$executedParameters[0] === 42
        && AuditLogTestStatement::$executedParameters[1] === 'incident.created'
        && AuditLogTestStatement::$executedParameters[2] === 'Create Incident Report'
        && AuditLogTestStatement::$executedParameters[4] === 'incident'
        && AuditLogTestStatement::$executedParameters[5] === '314'
);

$invalidActionRejected = false;
try {
    AuditLogService::record($pdo, 42, 'not.allowed');
} catch (InvalidArgumentException) {
    $invalidActionRejected = true;
}
$check('unknown action codes are rejected', $invalidActionRejected);

$allDescriptionsValid = true;
foreach (\Reddiph\Shared\AuditActionCatalog::supportedActions() as $actionCode => $description) {
    try {
        $allDescriptionsValid = $allDescriptionsValid
            && \Reddiph\Shared\AuditActionCatalog::describe($actionCode) === $description
            && \Reddiph\Shared\AuditActionCatalog::isValidDescription($description);
    } catch (InvalidArgumentException) {
        $allDescriptionsValid = false;
    }
}
$check('every catalog action has exactly three ASCII-letter words', $allDescriptionsValid);

exit($failures === 0 ? 0 : 1);
