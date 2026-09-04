<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\AccessRule\Operator;

use App\Enum\AccessRuleOperator;
use App\Service\AccessRule\Operator\NotEqualsOperator;

final class NotEqualsOperatorTest extends AbstractOperatorTestCase
{
    private NotEqualsOperator $operator;

    protected function setUp(): void
    {
        $this->operator = new NotEqualsOperator();
    }

    public function testSupportsOnlyNotEquals(): void
    {
        self::assertTrue($this->operator->supports(AccessRuleOperator::NOT_EQUALS));
        self::assertFalse($this->operator->supports(AccessRuleOperator::EQUALS));
    }

    public function testStringNotEqualsPositive(): void
    {
        self::assertTrue($this->operator->evaluate($this->createValue(valueString: 'B2'), 'C1'));
    }

    public function testStringNotEqualsNegative(): void
    {
        self::assertFalse($this->operator->evaluate($this->createValue(valueString: 'C1'), 'C1'));
    }

    public function testBooleanNotEquals(): void
    {
        self::assertTrue($this->operator->evaluate($this->createValue(valueBoolean: false), 'true'));
        self::assertFalse($this->operator->evaluate($this->createValue(valueBoolean: false), 'false'));
    }

    public function testNullValueFails(): void
    {
        self::assertFalse($this->operator->evaluate(null, 'C1'));
        self::assertFalse($this->operator->evaluate($this->createValue(), 'C1'));
    }
}
