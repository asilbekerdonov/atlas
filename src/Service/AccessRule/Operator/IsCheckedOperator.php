<?php

declare(strict_types=1);

namespace App\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Entity\CandidateAttributeValue;
use App\Service\AccessRule\OperatorInterface;

/**
 * The candidate's boolean attribute must be checked (true).
 */
final class IsCheckedOperator implements OperatorInterface
{
    public function supports(AccessRuleOperator $operator): bool
    {
        return $operator === AccessRuleOperator::IS_CHECKED;
    }

    public function evaluate(?CandidateAttributeValue $candidateValue, string $ruleValue): bool
    {
        return $candidateValue?->getValueBoolean() === true;
    }

    public function getSqlCondition(string $valueTableAlias, string $ruleTableAlias): string
    {
        return sprintf(
            '(%2$s.operator = \'IS_CHECKED\' AND %1$s.value_boolean IS TRUE)',
            $valueTableAlias,
            $ruleTableAlias,
        );
    }
}
