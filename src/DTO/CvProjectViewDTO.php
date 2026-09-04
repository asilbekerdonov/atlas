<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeImmutable;

/** A project relevant to the position (shared tag), rendered in the CV. */
final readonly class CvProjectViewDTO
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public int $id,
        public string $name,
        public DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
        public string $descriptionMd,
        public array $tags = [],
    ) {
    }
}
