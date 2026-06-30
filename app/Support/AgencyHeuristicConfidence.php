<?php

namespace App\Support;

final class AgencyHeuristicConfidence
{
    public const HIGH = 'high';

    public const MEDIUM = 'medium';

    public const LOW = 'low';

    public static function rank(string $level): int
    {
        return match ($level) {
            self::HIGH => 3,
            self::MEDIUM => 2,
            default => 1,
        };
    }

    public static function label(string $level): string
    {
        return match ($level) {
            self::HIGH => 'Visoka',
            self::MEDIUM => 'Srednja',
            default => 'Niska',
        };
    }
}
