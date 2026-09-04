<?php

declare(strict_types=1);

namespace App\Service\AccessRule;

use App\Enum\AccessRuleOperator;
use App\Entity\CandidateAttributeValue;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Strategy for one access-rule operator.
 *
 * Implementations are auto-tagged, so the evaluator and the repository
 * receive all of them through #[TaggedIterator('app.access_rule_operator')].
 */
#[AutoconfigureTag('app.access_rule_operator')]
interface OperatorInterface
{
    public function supports(AccessRuleOperator $operator): bool;

    /**
     * In-memory evaluation of one rule against a candidate's value.
     * A null value means the attribute is not filled in — the rule fails.
     */
    public function evaluate(?CandidateAttributeValue $candidateValue, string $ruleValue): bool;

    /**
     * Generates a safe SQL fragment for the single-query DBAL filter
     * (double NOT EXISTS pattern). Values are compared column-to-column,
     * never interpolated from user input. The fragment must be
     * self-contained: it has to restrict itself to its own operator
     * (e.g. "par.operator = 'EQUALS' AND ..."), because fragments of all
     * operators are OR-ed together for every rule row.
     *
     * @param string $valueTableAlias Alias of candidate_attribute_value (e.g. 'cav')
     * @param string $ruleTableAlias  Alias of position_access_rule (e.g. 'par')
     */
    public function getSqlCondition(string $valueTableAlias, string $ruleTableAlias): string;
}
