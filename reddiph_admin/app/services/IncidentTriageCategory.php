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

    private const COLORS = [
        'emergent' => '#DC2626',
        'urgent' => '#EAB308',
        'non_urgent' => '#16A34A',
        'expectant' => '#000000',
    ];

    public static function isValid(string $value): bool
    {
        return in_array($value, self::VALUES, true);
    }

    public static function label(?string $value): string
    {
        return self::LABELS[$value ?? ''] ?? 'Unclassified';
    }

    public static function color(?string $value): string
    {
        return self::COLORS[$value ?? ''] ?? '#6B7280';
    }
}
