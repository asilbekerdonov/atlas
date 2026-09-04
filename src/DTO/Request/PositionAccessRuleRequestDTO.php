<?php

declare(strict_types=1);

namespace App\DTO\Request;

use App\Enum\AccessRuleOperator;
use Symfony\Component\Validator\Constraints as Assert;

/** One access rule of a position. */
final class PositionAccessRuleRequestDTO
{
    public function __construct(
        #[Assert\Positive]
        public int $attributeId = 0,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: [AccessRuleOperator::EQUALS->value, AccessRuleOperator::NOT_EQUALS->value, AccessRuleOperator::GREATER_THAN->value, AccessRuleOperator::GREATER_THAN_OR_EQUAL->value, AccessRuleOperator::LESS_THAN->value, AccessRuleOperator::LESS_THAN_OR_EQUAL->value, AccessRuleOperator::IS_CHECKED->value, AccessRuleOperator::CONTAINS->value])]
        public string $operator = '',

        #[Assert\Length(max: 255)]
        public string $ruleValue = '',
    ) {
    }
}
