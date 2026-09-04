<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input for CREATING a candidate project — no version yet, optimistic
 * locking only applies to updates of existing rows.
 *
 * @param list<string> $tags
 */
final class ProjectCreateRequestDTO
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
    ) {
    }
}
