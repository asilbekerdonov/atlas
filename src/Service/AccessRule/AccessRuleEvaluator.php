<?php

declare(strict_types=1);

namespace App\Service\AccessRule;

use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * In-memory evaluation of position access rules against a candidate profile.
 *
 * All rules of a position are combined with AND: the position is accessible
 * only when every rule matches some value of the candidate.
 */
class AccessRuleEvaluator
{
    /** @var list<OperatorInterface> */
    private array $operators;

    public function __construct(
        #[AutowireIterator('app.access_rule_operator')] iterable $operators,
    ) {
        $this->operators = [...$operators];
    }

    public function isPositionAccessible(Position $position, CandidateProfile $profile): bool
    {
        // Public and restricted are mutually exclusive states (per the spec):
        // a public position is open to everyone, so access rules — even if
        // stale rows are still attached — must be ignored entirely.
        if ($position->isPublic()) {
            return true;
        }

        // Restricted position: every rule must match a candidate value.
        foreach ($position->getAccessRules() as $rule) {
            $operator = $this->resolveOperator($rule->getOperator());
            if (!$operator->evaluate($this->findValueForRule($profile, $rule), $rule->getRuleValue())) {
                return false;
            }
        }

        return true;
    }

    private function findValueForRule(CandidateProfile $profile, PositionAccessRule $rule): ?CandidateAttributeValue
    {
        $ruleAttribute = $rule->getAttribute();

        foreach ($profile->getAttributeValues() as $value) {
            $valueAttribute = $value->getAttribute();
            // Same instance (identity map) or same persisted id.
            if ($valueAttribute === $ruleAttribute || $valueAttribute->getId() === $ruleAttribute->getId()) {
                return $value;
            }
        }

        return null;
    }

    private function resolveOperator(\App\Enum\AccessRuleOperator $operator): OperatorInterface
    {
        foreach ($this->operators as $candidate) {
            if ($candidate->supports($operator)) {
                return $candidate;
            }
        }

        throw new LogicException(sprintf('No access rule operator registered for "%s".', $operator->value));
    }
}
