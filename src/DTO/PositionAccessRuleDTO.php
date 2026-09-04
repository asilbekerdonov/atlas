<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\AccessRuleOperator;

/** One access rule of a position. */
final readonly class PositionAccessRuleDTO
{
    public function __construct(
        public int $attributeId,
        public AccessRuleOperator $operator,
        public string $ruleValue = '',
    ) {
    }
}
