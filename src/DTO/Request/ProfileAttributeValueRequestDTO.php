<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** One attribute value submitted by the autosave client. */
final class ProfileAttributeValueRequestDTO
{
    public function __construct(
        #[Assert\Positive]
        public int $attributeId = 0,

        public mixed $value = null,

        #[Assert\Positive]
        public ?int $version = null,
    ) {
    }
}
