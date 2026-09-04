<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Service\AccessRule\Operator\LessThanOrEqualOperator;
use DateTimeImmutable;

final class LessThanOrEqualOperatorTest extends AbstractOperatorTestCase
{
    private LessThanOrEqualOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new LessThanOrEqualOperator();
    }

    public function testSupportsOnlyLessThanOrEqual(): void
    {
        self::assertTrue($this->operator->supports(AccessRuleOperator::LESS_THAN_OR_EQUAL));
        self::assertFalse($this->operator->supports(AccessRuleOperator::LESS_THAN));
    }

    public function testNumericPositive(): void
    {
        self::assertTrue($this->operator->evaluate($this->createValue(valueNumeric: '3'), '3'));
        self::assertTrue($this->operator->evaluate($this->createValue(valueNumeric: '2.1'), '3'));
    }

    public function testNumericNegative(): void
    {
        self::assertFalse($this->operator->evaluate($this->createValue(valueNumeric: '3.01'), '3'));
    }

    public function testDate(): void
    {
        $value = $this->createValue(valueDate: new DateTimeImmutable('2024-01-01'));
        self::assertTrue($this->operator->evaluate($value, '2024-01-01'));
        self::assertFalse($this->operator->evaluate($value, '2023-12-31'));
    }

    public function testNullValueFails(): void
    {
        self::assertFalse($this->operator->evaluate(null, '3'));
        self::assertFalse($this->operator->evaluate($this->createValue(), '3'));
    }
}
