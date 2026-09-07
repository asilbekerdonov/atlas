<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\Level;
use PHPUnit\Framework\TestCase;

/**
 * Level labels are rendered via |trans, so the enum must expose stable
 * translation keys instead of hardcoded strings.
 */
final class LevelTest extends TestCase
{
    public function testTranslationKeysMapAllCases(): void
    {
        $expected = [
            'JUNIOR' => 'position.level.junior',
            'MIDDLE' => 'position.level.middle',
            'SENIOR' => 'position.level.senior',
            'LEAD' => 'position.level.lead',
            'C_LEVEL' => 'position.level.c_level',
        ];

        foreach (Level::cases() as $level) {
            self::assertSame($expected[$level->value], $level->translationKey());
        }
    }
}
