<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Service\AccessRule\Operator\EqualsOperator;

final class EqualsOperatorTest extends AbstractOperatorTestCase
{
    private EqualsOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new EqualsOperator();
    }

    public function testSupportsOnlyEquals(): void
    {
        self::assertTrue($this->operator->supports(AccessRuleOperator::EQUALS));
        self::assertFalse($this->operator->supports(AccessRuleOperator::NOT_EQUALS));
        self::assertFalse($this->operator->supports(AccessRuleOperator::GREATER_THAN));
    }

    public function testStringEqualsPositive(): void
    {
        self::assertTrue($this->operator->evaluate($this->createValue(valueString: 'C1'), 'C1'));
    }

    public function testStringEqualsNegative(): void
    {
        self::assertFalse($this->operator->evaluate($this->createValue(valueString: 'B2'), 'C1'));
    }

    public function testBooleanEquals(): void
    {
        self::assertTrue($this->operator->evaluate($this->createValue(valueBoolean: true), 'true'));
        self::assertTrue($this->operator->evaluate($this->createValue(valueBoolean: false), 'false'));
        self::assertFalse($this->operator->evaluate($this->createValue(valueBoolean: true), 'false'));
    }

    public function testOptionEqualsById(): void
    {
        $option = $this->createOption(42, 'C1');
        self::assertTrue($this->operator->evaluate($this->createValue(valueOption: $option), '42'));
        self::assertFalse($this->operator->evaluate($this->createValue(valueOption: $option), '7'));
    }

    public function testNullValueFails(): void
    {
        self::assertFalse($this->operator->evaluate(null, 'C1'));
        self::assertFalse($this->operator->evaluate($this->createValue(), 'C1'));
    }

    public function testSqlConditionUsesAliases(): void
    {
        $sql = $this->operator->getSqlCondition('cav', 'par');
        self::assertStringContainsString('cav.value_string = par.rule_value', $sql);
        self::assertStringContainsString('cav.value_option_id::text = par.rule_value', $sql);
        self::assertStringContainsString('cav.value_boolean IS TRUE', $sql);
    }
}
