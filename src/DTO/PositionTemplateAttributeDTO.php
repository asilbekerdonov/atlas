<?php

declare(strict_types=1);

namespace App\DTO;

/** One attribute of a position template. */
final readonly class PositionTemplateAttributeDTO
{
    public function __construct(
        public int $attributeId,
        public bool $isRequired = false,
        public int $sortOrder = 0,
    ) {
    }
}
