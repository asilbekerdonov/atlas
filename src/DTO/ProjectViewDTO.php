<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Read-model of a candidate project for the API and the profile editor.
 * Dates are plain 'Y-m-d' strings; tags are names (join rows live in the
 * repository layer, never leak into the view).
 */
final readonly class ProjectViewDTO
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $startDate,
        public ?string $endDate,
        public string $descriptionMd,
        public array $tags,
        public int $version,
    ) {
    }
}
