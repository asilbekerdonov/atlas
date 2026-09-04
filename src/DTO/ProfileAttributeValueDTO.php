<?php

declare(strict_types=1);

namespace App\DTO;

/** One attribute value submitted by the autosave client. */
final readonly class ProfileAttributeValueDTO
{
    public function __construct(
        public int $attributeId,
        public mixed $rawValue,
    ) {
    }
}
