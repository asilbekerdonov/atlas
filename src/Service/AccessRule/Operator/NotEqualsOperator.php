<?php

declare(strict_types=1);

namespace App\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Entity\CandidateAttributeValue;
use App\Service\AccessRule\OperatorInterface;

/**
 * Inverse of EQUALS. NULL guards keep "attribute not filled in" from matching.
 */
final class NotEqualsOperator implements OperatorInterface
{
    public function supports(AccessRuleOperator $operator): bool
    {
        return $operator === AccessRuleOperator::NOT_EQUALS;
    }

    public function evaluate(?CandidateAttributeValue $candidateValue, string $ruleValue): bool
    {
        if ($candidateValue === null) {
            return false;
        }

        if ($candidateValue->getValueOption() !== null) {
            return (string) $candidateValue->getValueOption()->getId() !== $ruleValue;
        }

        if ($candidateValue->getValueBoolean() !== null) {
            return ($candidateValue->getValueBoolean() && $ruleValue !== 'true')
                || (!$candidateValue->getValueBoolean() && $ruleValue !== 'false');
        }

        if ($candidateValue->getValueString() !== null) {
            return $candidateValue->getValueString() !== $ruleValue;
        }

        return false;
    }

    public function getSqlCondition(string $valueTableAlias, string $ruleTableAlias): string
    {
        $v = $valueTableAlias;
        $r = $ruleTableAlias;

        return sprintf(
            '(%2$s.operator = \'NOT_EQUALS\' AND (%1$s.value_string IS NOT NULL AND %1$s.value_string <> %2$s.rule_value '
            . 'OR %1$s.value_option_id IS NOT NULL AND %1$s.value_option_id::text <> %2$s.rule_value '
            . 'OR (%1$s.value_boolean IS TRUE AND %2$s.rule_value <> \'true\') '
            . 'OR (%1$s.value_boolean IS FALSE AND %2$s.rule_value <> \'false\')))',
            $v,
            $r,
        );
    }
}
