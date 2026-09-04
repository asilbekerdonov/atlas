<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\CvStatus;

/**
 * Aggregated CV view: base profile fields + template attributes joined with
 * candidate values + relevant projects. Never exposes raw Doctrine entities.
 */
final readonly class CvViewDTO
{
    /**
     * @param list<CvAttributeViewDTO> $attributes
     * @param list<CvProjectViewDTO>   $projects
     */
    public function __construct(
        public int $cvId,
        public CvStatus $status,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $location,
        public ?string $avatarUrl,
        public array $attributes,
        public array $projects,
    ) {
    }
}
