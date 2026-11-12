<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/services/ResponderAvailability.php';

$failures = 0;
$check = static function (string $name, bool $ok) use (&$failures): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    $failures += $ok ? 0 : 1;
};
$timezone = new DateTimeZone('Asia/Manila');
$period = static fn(int $day, string $start, string $end): array => [
    'day_of_week' => $day,
    'start_time' => $start,
    'end_time' => $end,
];

$check(
    'regular period includes start and excludes end',
    ResponderAvailability::isOnDuty(
        [$period(1, '07:00:00', '19:00:00')],
        new DateTimeImmutable('2026-11-09 07:00:00', $timezone)
    ) && !ResponderAvailability::isOnDuty(
        [$period(1, '07:00:00', '19:00:00')],
        new DateTimeImmutable('2026-11-09 19:00:00', $timezone)
    )
);
$check(
    'overnight period continues into the next day',
    ResponderAvailability::isOnDuty(
        [$period(1, '22:00:00', '06:00:00')],
        new DateTimeImmutable('2026-11-10 05:59:00', $timezone)
    ) && !ResponderAvailability::isOnDuty(
        [$period(1, '22:00:00', '06:00:00')],
        new DateTimeImmutable('2026-11-10 06:00:00', $timezone)
    )
);
$check(
    'Sunday overnight period wraps into Monday',
    ResponderAvailability::isOnDuty(
        [$period(7, '22:00:00', '06:00:00')],
        new DateTimeImmutable('2026-11-09 05:59:00', $timezone)
    ) && !ResponderAvailability::isOnDuty(
        [$period(7, '22:00:00', '06:00:00')],
        new DateTimeImmutable('2026-11-09 06:00:00', $timezone)
    )
);
$check(
    'schedule does not cross an unavailable weekday',
    !ResponderAvailability::isOnDuty(
        [$period(1, '07:00:00', '19:00:00')],
        new DateTimeImmutable('2026-11-10 12:00:00', $timezone)
    )
);
$check(
    'no configured periods means off duty',
    !ResponderAvailability::isOnDuty(
        [],
        new DateTimeImmutable('2026-11-09 12:00:00', $timezone)
    )
);
$check(
    'equal start and end times are invalid',
    !ResponderAvailability::isValidPeriod($period(1, '07:00:00', '07:00:00'))
);
$check(
    'overlapping periods across midnight are invalid',
    !ResponderAvailability::arePeriodsValid([
        $period(1, '22:00:00', '06:00:00'),
        $period(2, '05:00:00', '07:00:00'),
    ])
);
$check(
    'touching periods do not overlap',
    ResponderAvailability::arePeriodsValid([
        $period(1, '07:00:00', '12:00:00'),
        $period(1, '12:00:00', '19:00:00'),
    ])
);

exit($failures === 0 ? 0 : 1);