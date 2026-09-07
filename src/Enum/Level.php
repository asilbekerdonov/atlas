<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Experience level of a position (vacancy template).
 *
 * Backed by the same five values the form used to hardcode. Stored as a
 * plain string column (see Position::$level), so no schema change is needed.
 */
enum Level: string
{
    case JUNIOR = 'JUNIOR';
    case MIDDLE = 'MIDDLE';
    case SENIOR = 'SENIOR';
    case LEAD = 'LEAD';
    case C_LEVEL = 'C_LEVEL';

    /**
     * Translation key for the human-readable label (rendered via |trans).
     * Never output the raw enum value or a hardcoded label in the UI.
     */
    public function translationKey(): string
    {
        return match ($this) {
            self::JUNIOR => 'position.level.junior',
            self::MIDDLE => 'position.level.middle',
            self::SENIOR => 'position.level.senior',
            self::LEAD => 'position.level.lead',
            self::C_LEVEL => 'position.level.c_level',
        };
    }
}
