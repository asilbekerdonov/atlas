<?php

declare(strict_types=1);

namespace App\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Entity\CandidateAttributeValue;
use App\Service\AccessRule\OperatorInterface;

/**
 * Case-insensitive substring match against value_string or value_text.
 */
final class ContainsOperator implements OperatorInterface
{
    public function supports(AccessRuleOperator $operator): bool
    {
        return $operator === AccessRuleOperator::CONTAINS;
    }

    public function evaluate(?CandidateAttributeValue $candidateValue, string $ruleValue): bool
    {
        if ($candidateValue === null) {
            return false;
        }

        $string = $candidateValue->getValueString();
        if ($string !== null && stripos($string, $ruleValue) !== false) {
            return true;
        }

        $text = $candidateValue->getValueText();
        return $text !== null && stripos($text, $ruleValue) !== false;
    }

    public function getSqlCondition(string $valueTableAlias, string $ruleTableAlias): string
    {
        return sprintf(
            '(%2$s.operator = \'CONTAINS\' AND (%1$s.value_string ILIKE \'%%\' || %2$s.rule_value || \'%%\' '
            . 'OR %1$s.value_text ILIKE \'%%\' || %2$s.rule_value || \'%%\'))',
            $valueTableAlias,
            $ruleTableAlias,
        );
    }
}
