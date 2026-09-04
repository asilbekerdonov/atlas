<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Service\AccessRule\Operator\LessThanOperator;
use DateTimeImmutable;

final class LessThanOperatorTest extends AbstractOperatorTestCase
{
    private LessThanOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new LessThanOperator();
    }

    public function testSupportsOnlyLessThan(): void
    {
        self::assertTrue($this->operator->supports(AccessRuleOperator::LESS_THAN));
        self::assertFalse($this->operator->supports(AccessRuleOperator::GREATER_THAN));
    }

    public function testNumericLessThanPositive(): void
    {
        self::assertTrue($this->operator->evaluate($this->createValue(valueNumeric: '2'), '3'));
    }

    public function testNumericLessThanNegative(): void
    {
        self::assertFalse($this->operator->evaluate($this->createValue(valueNumeric: '5.5'), '3'));
        self::assertFalse($this->operator->evaluate($this->createValue(valueNumeric: '3'), '3'));
    }

    public function testDateLessThan(): void
    {
        $value = $this->createValue(valueDate: new DateTimeImmutable('2024-01-01'));
        self::assertTrue($this->operator->evaluate($value, '2024-06-01'));
        self::assertFalse($this->operator->evaluate($value, '2023-12-31'));
    }

    public function testNullValueFails(): void
    {
        self::assertFalse($this->operator->evaluate(null, '3'));
        self::assertFalse($this->operator->evaluate($this->createValue(), '3'));
    }
}
