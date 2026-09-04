<?php

declare(strict_types=1);

namespace App\Security;

use cebe\markdown\Markdown;

/**
 * Anti-XSS pipeline for user-written Markdown (project descriptions,
 * discussion posts). Renders Markdown, then strips dangerous elements and
 * attributes — no raw user HTML ever reaches the page.
 */
final class MarkdownSanitizer
{
    private readonly Markdown $markdown;

    public function __construct()
    {
        $this->markdown = new Markdown();
    }

    public function toSafeHtml(string $markdownText): string
    {
        return $this->sanitize($this->markdown->parse($markdownText));
    }

    private function sanitize(string $html): string
    {
        // 1. Drop dangerous elements together with their content.
        $html = preg_replace('#<(script|iframe|object|embed|style|link|meta|form)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|iframe|object|embed|style|link|meta|form)\b[^>]*/?>#is', '', $html) ?? $html;

        // 2. Strip event handler attributes (onclick, onload, ...).
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;

        // 3. Neutralize javascript:/vbscript: URLs in href/src attributes.
        $html = preg_replace('#\b(href|src)\s*=\s*(["\'])\s*(?:javascript|vbscript):[^"\']*\2#i', '$1=$2#$2', $html) ?? $html;

        return $html;
    }
}
