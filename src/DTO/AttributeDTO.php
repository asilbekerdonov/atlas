<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\AttributeDataType;

/**
 * Service-layer input for creating/updating an attribute in the library.
 *
 * Carries flat category data (id + name), NEVER a Doctrine entity — the
 * entity is resolved by the service inside the same layer.
 *
 * @param list<string> $options option labels for ONE_OF_MANY attributes
 */
final readonly class AttributeDTO
{
    public function __construct(
        public string $name,
        public int $categoryId,
        public string $categoryName,
        public AttributeDataType $dataType,
        public ?string $description = null,
        public array $options = [],
    ) {
    }
}
