<?php

declare(strict_types=1);

final class ResponderAvailability
{
    public static function isValidPeriod(array $period): bool
    {
        $day = filter_var($period['day_of_week'] ?? null, FILTER_VALIDATE_INT);
        $start = $period['start_time'] ?? null;
        $end = $period['end_time'] ?? null;

        return $day !== false && $day >= 1 && $day <= 7
            && is_string($start) && self::isValidTime($start)
            && is_string($end) && self::isValidTime($end)
            && $start !== $end;
    }

    public static function arePeriodsValid(array $periods): bool
    {
        if ($periods === [] || count($periods) > 28) {
            return false;
        }

        $rangesByDay = [];
        foreach ($periods as $period) {
            if (!is_array($period) || !self::isValidPeriod($period)) {
                return false;
            }
            $day = (int) $period['day_of_week'];
            $start = self::minutes($period['start_time']);
            $end = self::minutes($period['end_time']);
            if ($end > $start) {
                $rangesByDay[$day][] = [$start, $end];
            } else {
                $rangesByDay[$day][] = [$start, 1440];
                $nextDay = $day === 7 ? 1 : $day + 1;
                $rangesByDay[$nextDay][] = [0, $end];
            }
        }

        foreach ($rangesByDay as $ranges) {
            usort($ranges, static fn(array $left, array $right): int => $left[0] <=> $right[0]);
            for ($index = 1; $index < count($ranges); $index++) {
                if ($ranges[$index][0] < $ranges[$index - 1][1]) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function isOnDuty(array $periods, DateTimeImmutable $now): bool
    {
        $day = (int) $now->format('N');
        $previousDay = $day === 1 ? 7 : $day - 1;
        $time = $now->format('H:i:s');

        foreach ($periods as $period) {
            if (!self::isValidPeriod($period)) {
                continue;
            }
            $periodDay = (int) $period['day_of_week'];
            $start = $period['start_time'];
            $end = $period['end_time'];

            if ($periodDay === $day) {
                if ($start < $end && $time >= $start && $time < $end) {
                    return true;
                }
                if ($start > $end && $time >= $start) {
                    return true;
                }
            }

            if ($periodDay === $previousDay && $start > $end && $time < $end) {
                return true;
            }
        }

        return false;
    }

    private static function isValidTime(string $time): bool
    {
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time) === 1;
    }

    private static function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }
}