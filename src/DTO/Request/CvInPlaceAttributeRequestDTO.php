<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** In-place attribute edit of a CV. */
final class CvInPlaceAttributeRequestDTO
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
