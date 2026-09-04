<?php

declare(strict_types=1);

namespace App\DTO\Request;

use App\Enum\AttributeDataType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input for creating/updating an attribute in the library.
 *
 * category is the human-readable category NAME (lookup row, not enum). Its
 * existence is validated by the service layer (CategoryNotFoundException),
 * never by a hard-coded Choice list.
 *
 * @param list<string> $options option labels for ONE_OF_MANY attributes
 */
final class AttributeRequestDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        public string $name = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        public string $category = '',

        #[Assert\NotBlank]
        #[Assert\Choice(choices: [AttributeDataType::STRING->value, AttributeDataType::TEXT->value, AttributeDataType::IMAGE->value, AttributeDataType::NUMERIC->value, AttributeDataType::DATE->value, AttributeDataType::PERIOD->value, AttributeDataType::BOOLEAN->value, AttributeDataType::ONE_OF_MANY->value])]
        public string $dataType = '',

        #[Assert\Length(max: 1000)]
        public ?string $description = null,

        #[Assert\All([new Assert\Length(min: 1, max: 100)])]
        public array $options = [],
    ) {
    }
}
