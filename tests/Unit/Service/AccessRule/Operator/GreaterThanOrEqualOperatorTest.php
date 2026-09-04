<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Service\AccessRule\Operator\GreaterThanOrEqualOperator;
use DateTimeImmutable;

final class GreaterThanOrEqualOperatorTest extends AbstractOperatorTestCase
{
    private GreaterThanOrEqualOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new GreaterThanOrEqualOperator();
    }

    public function testSupportsOnlyGreaterThanOrEqual(): void
    {
        self::assertTrue($this->operator->supports(AccessRuleOperator::GREATER_THAN_OR_EQUAL));
        self::assertFalse($this->operator->supports(AccessRuleOperator::GREATER_THAN));
    }

    public function testNumericPositive(): void
    {
        self::assertTrue($this->operator->evaluate($this->createValue(valueNumeric: '3'), '3'));
        self::assertTrue($this->operator->evaluate($this->createValue(valueNumeric: '4.2'), '3'));
    }

    public function testNumericNegative(): void
    {
        self::assertFalse($this->operator->evaluate($this->createValue(valueNumeric: '2.9'), '3'));
    }

    public function testDate(): void
    {
        $value = $this->createValue(valueDate: new DateTimeImmutable('2024-01-01'));
        self::assertTrue($this->operator->evaluate($value, '2024-01-01'));
        self::assertFalse($this->operator->evaluate($value, '2024-01-02'));
    }

    public function testNullValueFails(): void
    {
        self::assertFalse($this->operator->evaluate(null, '3'));
        self::assertFalse($this->operator->evaluate($this->createValue(), '3'));
    }
}
