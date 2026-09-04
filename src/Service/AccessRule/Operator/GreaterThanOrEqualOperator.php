<?php

declare(strict_types=1);

namespace App\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use DateTimeImmutable;
use Exception;
use App\Entity\CandidateAttributeValue;
use App\Service\AccessRule\OperatorInterface;

/**
 * Numeric or date value greater than or equal to the rule value.
 */
final class GreaterThanOrEqualOperator implements OperatorInterface
{
    public function supports(AccessRuleOperator $operator): bool
    {
        return $operator === AccessRuleOperator::GREATER_THAN_OR_EQUAL;
    }

    public function evaluate(?CandidateAttributeValue $candidateValue, string $ruleValue): bool
    {
        if ($candidateValue === null) {
            return false;
        }

        if ($candidateValue->getValueNumeric() !== null && is_numeric($ruleValue)) {
            return (float) $candidateValue->getValueNumeric() >= (float) $ruleValue;
        }

        if ($candidateValue->getValueDate() !== null) {
            try {
                return $candidateValue->getValueDate() >= new DateTimeImmutable($ruleValue);
            } catch (Exception) {
                return false;
            }
        }

        return false;
    }

    public function getSqlCondition(string $valueTableAlias, string $ruleTableAlias): string
    {
        // CASE WHEN guards the casts: PostgreSQL may evaluate both OR branches,
        // so an unguarded rule_value::date would crash on numeric rules.
        return sprintf(
            '(%2$s.operator = \'GREATER_THAN_OR_EQUAL\' AND ('
            . 'CASE WHEN %2$s.rule_value ~ \'^[0-9]+([.][0-9]+)?$\' '
            . 'THEN %1$s.value_numeric >= %2$s.rule_value::numeric ELSE FALSE END '
            . 'OR CASE WHEN %2$s.rule_value ~ \'^[0-9]{4}-[0-9]{2}-[0-9]{2}$\' '
            . 'THEN %1$s.value_date >= %2$s.rule_value::date ELSE FALSE END))',
            $valueTableAlias,
            $ruleTableAlias,
        );
    }
}
