<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Entity\AttributeCategory;
use App\Twig\AttributeCategoryExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Decision B: category labels are translated best-effort via a deterministic
 * slug key when the key exists; otherwise the raw name is shown. The slug
 * function must be pure and never write/create translation keys.
 */
final class AttributeCategoryExtensionTest extends TestCase
{
    private function extension(TranslatorInterface $translator): AttributeCategoryExtension
    {
        return new AttributeCategoryExtension($translator);
    }

    public function testTranslationKeyIsDeterministicSlug(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $extension = $this->extension($translator);

        self::assertSame(
            'attribute.category.domain_knowledge',
            $extension->translationKey(new AttributeCategory('Domain Knowledge')),
        );
        self::assertSame(
            'attribute.category.technical_skills',
            $extension->translationKey(new AttributeCategory('Technical Skills')),
        );
    }

    public function testLabelReturnsTranslationWhenKeyExists(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $key): string => match ($key) {
                'attribute.category.soft_skills' => 'Мягкие навыки',
                default => $key,
            },
        );

        self::assertSame('Мягкие навыки', $this->extension($translator)->label(new AttributeCategory('Soft Skills')));
    }

    public function testLabelFallsBackToRawNameWhenKeyMissing(): void
    {
        // Unknown categories (entered by a recruiter) have no key — the raw
        // name must render as-is, with the key NEVER leaking and no exception.
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $key): string => $key, // translator returns the key when absent
        );

        self::assertSame(
            'Quantum Physics',
            $this->extension($translator)->label(new AttributeCategory('Quantum Physics')),
        );
    }
}
