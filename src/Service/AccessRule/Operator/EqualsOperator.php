<?php

declare(strict_types=1);

namespace App\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Entity\CandidateAttributeValue;
use App\Service\AccessRule\OperatorInterface;

/**
 * STRING / ONE_OF_MANY (by option id) / BOOLEAN values equal to the rule value.
 */
final class EqualsOperator implements OperatorInterface
{
    public function supports(AccessRuleOperator $operator): bool
    {
        return $operator === AccessRuleOperator::EQUALS;
    }

    public function evaluate(?CandidateAttributeValue $candidateValue, string $ruleValue): bool
    {
        if ($candidateValue === null) {
            return false;
        }

        if ($candidateValue->getValueOption() !== null) {
            return (string) $candidateValue->getValueOption()->getId() === $ruleValue;
        }

        if ($candidateValue->getValueBoolean() !== null) {
            return ($candidateValue->getValueBoolean() && $ruleValue === 'true')
                || (!$candidateValue->getValueBoolean() && $ruleValue === 'false');
        }

        if ($candidateValue->getValueString() !== null) {
            return $candidateValue->getValueString() === $ruleValue;
        }

        return false;
    }

    public function getSqlCondition(string $valueTableAlias, string $ruleTableAlias): string
    {
        $v = $valueTableAlias;
        $r = $ruleTableAlias;

        return sprintf(
            '(%2$s.operator = \'EQUALS\' AND (%1$s.value_string = %2$s.rule_value '
            . 'OR %1$s.value_option_id::text = %2$s.rule_value '
            . 'OR (%1$s.value_boolean IS TRUE AND %2$s.rule_value = \'true\') '
            . 'OR (%1$s.value_boolean IS FALSE AND %2$s.rule_value = \'false\')))',
            $v,
            $r,
        );
    }
}
