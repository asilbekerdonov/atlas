<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\AttributeCategory;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Best-effort category label translation (decision B).
 *
 * Maps the category NAME to a translation key with a pure, deterministic
 * function (lowercase, spaces → underscores) and translates only when that
 * key already exists in the catalogue. Unknown/arbitrary categories fall back
 * to the raw name — no error, no log noise, and the function NEVER writes or
 * creates keys, it only reads existing translations.
 */
final class AttributeCategoryExtension extends AbstractExtension
{
    private const KEY_PREFIX = 'attribute.category.';

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('category_label', $this->label(...)),
        ];
    }

    /**
     * "Domain Knowledge" → "attribute.category.domain_knowledge". Pure.
     *
     * Known limitation (valid while categories are seed-only): the slug does
     * not normalize punctuation or repeated spaces, so "Domain-Knowledge",
     * "Domain  Knowledge" and "Domain Knowledge" would map to different keys.
     * Revisit if a category CRUD UI is ever introduced.
     */
    public function translationKey(AttributeCategory $category): string
    {
        return self::KEY_PREFIX . strtolower(str_replace(' ', '_', trim($category->getName())));
    }

    public function label(AttributeCategory $category): string
    {
        $key = $this->translationKey($category);
        // trans() returns the key itself when no entry exists — that is the
        // expected, quiet fallback path for arbitrary recruiter categories.
        $translated = $this->translator->trans($key);

        return $translated === $key ? $category->getName() : $translated;
    }
}
