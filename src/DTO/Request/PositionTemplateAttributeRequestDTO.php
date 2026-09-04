<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** One attribute of a position template. */
final class PositionTemplateAttributeRequestDTO
{
    public function __construct(
        #[Assert\Positive]
        public int $attributeId = 0,

        public bool $isRequired = false,

        #[Assert\Range(min: 0, max: 1000)]
        public int $sortOrder = 0,
    ) {
    }
}
