<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\Format;
use PHPUnit\Framework\TestCase;

/**
 * Format labels are rendered via |trans, so the enum must expose stable
 * translation keys instead of hardcoded English strings.
 */
final class FormatTest extends TestCase
{
    public function testTranslationKeysAreStableAndExistInBothCatalogues(): void
    {
        $expected = [
            Format::Remote->value => 'position.format.remote',
            Format::Local->value => 'position.format.on_site',
            Format::Hybrid->value => 'position.format.hybrid',
        ];

        foreach (Format::cases() as $format) {
            self::assertSame($expected[$format->value], $format->translationKey());
        }
    }
}
