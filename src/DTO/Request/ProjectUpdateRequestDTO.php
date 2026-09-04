<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input for UPDATING a candidate project. expectedVersion is REQUIRED —
 * the optimistic-lock check compares it with the persisted row version and
 * returns 409 on mismatch, so a PATCH without a version must fail validation
 * instead of silently overwriting concurrent changes.
 *
 * @param list<string> $tags
 */
final class ProjectUpdateRequestDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 200)]
        public string $name = '',

        #[Assert\NotBlank]
        public string $startDate = '',

        public ?string $endDate = null,

        #[Assert\NotBlank]
        #[Assert\Length(max: 20000)]
        public string $descriptionMd = '',

        #[Assert\All([new Assert\Length(max: 50)])]
        public array $tags = [],

        #[Assert\NotNull]
        public ?int $expectedVersion = null,
    ) {
    }
}
