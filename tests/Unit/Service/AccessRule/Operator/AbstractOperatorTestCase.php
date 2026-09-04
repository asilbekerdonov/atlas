<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\AccessRule\Operator;

use App\Entity\Attribute;
use App\Entity\AttributeOption;
use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\User;
use App\Entity\AttributeCategory;
use App\Enum\AttributeDataType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Shared factory for CandidateAttributeValue fixtures used by operator tests.
 */
abstract class AbstractOperatorTestCase extends TestCase
{
    protected function createValue(
        ?string $valueString = null,
        ?string $valueText = null,
        ?string $valueNumeric = null,
        ?DateTimeImmutable $valueDate = null,
        ?bool $valueBoolean = null,
        ?AttributeOption $valueOption = null,
    ): CandidateAttributeValue {
        $attribute = new Attribute('test', new AttributeCategory('Personal Information'), AttributeDataType::STRING);
        $value = new CandidateAttributeValue(new CandidateProfile(new User('c@example.com'), 'Jane', 'Doe'), $attribute);

        $value->setValueString($valueString);
        $value->setValueText($valueText);
        $value->setValueNumeric($valueNumeric);
        $value->setValueDate($valueDate);
        $value->setValueBoolean($valueBoolean);
        $value->setValueOption($valueOption);

        return $value;
    }

    /** Creates an option with a forced id (as if it were persisted). */
    protected function createOption(int $id, string $value): AttributeOption
    {
        $attribute = new Attribute('test', new AttributeCategory('Personal Information'), AttributeDataType::ONE_OF_MANY);
        $option = new AttributeOption($attribute, $value, $value);
        (new ReflectionProperty(AttributeOption::class, 'id'))->setValue($option, $id);

        return $option;
    }
}
