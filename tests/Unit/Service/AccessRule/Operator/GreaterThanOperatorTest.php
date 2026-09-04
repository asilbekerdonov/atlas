<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Service\AccessRule\Operator\GreaterThanOperator;
use DateTimeImmutable;

final class GreaterThanOperatorTest extends AbstractOperatorTestCase
{
    private GreaterThanOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new GreaterThanOperator();
    }

    public function testSupportsOnlyGreaterThan(): void
    {
        self::assertTrue($this->operator->supports(AccessRuleOperator::GREATER_THAN));
        self::assertFalse($this->operator->supports(AccessRuleOperator::LESS_THAN));
    }

    public function testNumericGreaterThanPositive(): void
    {
        self::assertTrue($this->operator->evaluate($this->createValue(valueNumeric: '5.5'), '3'));
    }

    public function testNumericGreaterThanNegative(): void
    {
        self::assertFalse($this->operator->evaluate($this->createValue(valueNumeric: '2'), '3'));
        self::assertFalse($this->operator->evaluate($this->createValue(valueNumeric: '3'), '3'));
    }

    public function testDateGreaterThan(): void
    {
        $value = $this->createValue(valueDate: new DateTimeImmutable('2024-06-01'));
        self::assertTrue($this->operator->evaluate($value, '2024-01-01'));
        self::assertFalse($this->operator->evaluate($value, '2024-07-01'));
    }

    public function testNullValueFails(): void
    {
        self::assertFalse($this->operator->evaluate(null, '3'));
        self::assertFalse($this->operator->evaluate($this->createValue(), '3'));
    }
}
