<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\AttributeDataType;

/**
 * One template attribute of a position with the candidate's value status.
 *
 * @phpstan-type CvAttributeOptionList list<array{id: int, label: string}>
 */
final readonly class CvAttributeViewDTO
{
    /**
     * @param string|null                       $rawValue     machine value for editors (option id, 'Y-m-d', 'true'/'false', plain string)
     * @param string|null                       $displayValue human-readable value for the static view
     * @param list<array{id:int,label:string}>  $options      options for ONE_OF_MANY editors
     */
    public function __construct(
        public int $attributeId,
        public string $name,
        public AttributeDataType $dataType,
        public bool $isRequired,
        /** True when the candidate has no value — frontend highlights it red. */
        public bool $isEmpty,
        public ?string $rawValue = null,
        public ?string $displayValue = null,
        public ?int $version = null,
        public array $options = [],
    ) {
    }
}
