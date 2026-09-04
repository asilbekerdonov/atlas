<?php

declare(strict_types=1);

namespace App\Twig;

use App\Security\MarkdownSanitizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/** Twig filter exposing the sanitized Markdown pipeline. */
final class MarkdownExtension extends AbstractExtension
{
    public function __construct(private readonly MarkdownSanitizer $sanitizer)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown_safe', fn (string $text): string => $this->sanitizer->toSafeHtml($text), ['is_safe' => ['html']]),
        ];
    }
}
