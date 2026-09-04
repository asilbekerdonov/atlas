<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Input of the profile autosave. Nullable fields are only applied when set;
 * expectedVersion guards against lost updates.
 */
final readonly class ProfileAutosaveDTO
{
    /**
     * @param list<ProfileAttributeValueDTO> $attributeValues
     */
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $location = null,
        public ?string $avatarUrl = null,
        public ?int $expectedVersion = null,
        public array $attributeValues = [],
    ) {
    }
}
