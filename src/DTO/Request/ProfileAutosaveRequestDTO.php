<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

/** Input of the profile autosave endpoint. */
final class ProfileAutosaveRequestDTO
{
    /**
     * @param list<ProfileAttributeValueRequestDTO> $attributeValues
     */
    public function __construct(
        #[Assert\Length(max: 100)]
        public ?string $firstName = null,

        #[Assert\Length(max: 100)]
        public ?string $lastName = null,

        #[Assert\Length(max: 255)]
        public ?string $location = null,

        #[Assert\Url]
        #[Assert\Length(max: 255)]
        public ?string $avatarUrl = null,

        #[Assert\Positive]
        public ?int $expectedVersion = null,

        #[Assert\Valid]
        public array $attributeValues = [],
    ) {
    }
}
