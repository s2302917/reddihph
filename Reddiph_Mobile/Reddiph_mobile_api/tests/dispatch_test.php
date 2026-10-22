<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/models/DispatchModel.php';

$failures = 0;
$check = static function (string $name, bool $ok) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    $failures += $ok ? 0 : 1;
};

$check('same point gives the 1 minute minimum', DispatchModel::etaMinutes(10.0, 123.0, 10.0, 123.0) === 1);
// 0.1 degree of latitude is about 11.12 km: ceil(11.12 * 1.3 / 35 * 60) = 25.
$check('about 11 km gives 25 minutes', DispatchModel::etaMinutes(10.0, 123.0, 10.1, 123.0) === 25);
$check('eta is symmetric', DispatchModel::etaMinutes(10.0, 123.0, 10.1, 123.1) === DispatchModel::etaMinutes(10.1, 123.1, 10.0, 123.0));

$check('0.1 degree of latitude is about 11.1 km', abs(DispatchModel::distanceKm(10.0, 123.0, 10.1, 123.0) - 11.12) < 0.05);

exit($failures === 0 ? 0 : 1);
