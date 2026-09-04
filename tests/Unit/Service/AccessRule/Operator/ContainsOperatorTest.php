<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Service\AccessRule\Operator\ContainsOperator;

final class ContainsOperatorTest extends AbstractOperatorTestCase
{
    private ContainsOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new ContainsOperator();
    }

    public function testSupportsOnlyContains(): void
    {
        self::assertTrue($this->operator->supports(AccessRuleOperator::CONTAINS));
        self::assertFalse($this->operator->supports(AccessRuleOperator::EQUALS));
    }

    public function testStringContainsPositive(): void
    {
        self::assertTrue($this->operator->evaluate($this->createValue(valueString: 'Senior PHP Developer'), 'php'));
        self::assertTrue($this->operator->evaluate($this->createValue(valueString: 'Senior PHP Developer'), 'PHP'));
    }

    public function testStringContainsNegative(): void
    {
        self::assertFalse($this->operator->evaluate($this->createValue(valueString: 'Senior PHP Developer'), 'Python'));
    }

    public function testTextContainsPositive(): void
    {
        self::assertTrue($this->operator->evaluate($this->createValue(valueText: 'Worked with Kubernetes and Docker'), 'kubernetes'));
    }

    public function testNullValueFails(): void
    {
        self::assertFalse($this->operator->evaluate(null, 'php'));
        self::assertFalse($this->operator->evaluate($this->createValue(), 'php'));
    }
}
