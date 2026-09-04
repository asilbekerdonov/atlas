<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Service\AccessRule\Operator\IsCheckedOperator;

final class IsCheckedOperatorTest extends AbstractOperatorTestCase
{
    private IsCheckedOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new IsCheckedOperator();
    }

    public function testSupportsOnlyIsChecked(): void
    {
        self::assertTrue($this->operator->supports(AccessRuleOperator::IS_CHECKED));
        self::assertFalse($this->operator->supports(AccessRuleOperator::EQUALS));
    }

    public function testCheckedPositive(): void
    {
        self::assertTrue($this->operator->evaluate($this->createValue(valueBoolean: true), ''));
    }

    public function testUncheckedNegative(): void
    {
        self::assertFalse($this->operator->evaluate($this->createValue(valueBoolean: false), ''));
    }

    public function testNullValueFails(): void
    {
        self::assertFalse($this->operator->evaluate(null, ''));
        self::assertFalse($this->operator->evaluate($this->createValue(), ''));
    }
}
