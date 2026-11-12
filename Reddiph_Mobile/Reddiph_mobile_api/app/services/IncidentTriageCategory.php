<?php

declare(strict_types=1);

final class IncidentTriageCategory
{
    public const VALUES = ['emergent', 'urgent', 'non_urgent', 'expectant'];

    private const LABELS = [
        'emergent' => 'Emergent',
        'urgent' => 'Urgent',
        'non_urgent' => 'Non-Urgent',
        'expectant' => 'Expectant',
    ];

    public static function isValid(string $value): bool
    {
        return in_array($value, self::VALUES, true);
    }

    public static function label(?string $value): string
    {
        return self::LABELS[$value ?? ''] ?? 'Unclassified';
    }
}
