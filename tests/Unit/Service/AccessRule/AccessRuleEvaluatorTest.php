<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\AccessRule;

use App\Entity\Attribute;
use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\User;
use App\Enum\AccessRuleOperator;
use App\Entity\AttributeCategory;
use App\Enum\AttributeDataType;
use App\Service\AccessRule\AccessRuleEvaluator;
use App\Service\AccessRule\Operator\ContainsOperator;
use App\Service\AccessRule\Operator\EqualsOperator;
use App\Service\AccessRule\Operator\GreaterThanOperator;
use App\Service\AccessRule\Operator\GreaterThanOrEqualOperator;
use App\Service\AccessRule\Operator\IsCheckedOperator;
use App\Service\AccessRule\Operator\LessThanOperator;
use App\Service\AccessRule\Operator\LessThanOrEqualOperator;
use App\Service\AccessRule\Operator\NotEqualsOperator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AccessRuleEvaluatorTest extends TestCase
{
    private AccessRuleEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new AccessRuleEvaluator([
            new EqualsOperator(),
            new NotEqualsOperator(),
            new GreaterThanOperator(),
            new GreaterThanOrEqualOperator(),
            new LessThanOperator(),
            new LessThanOrEqualOperator(),
            new IsCheckedOperator(),
            new ContainsOperator(),
        ]);
    }

    public function testPublicPositionWithoutRulesIsAccessible(): void
    {
        $position = new Position('Open position', 'No rules');
        $position->setPublic(true);
        $profile = $this->createProfile();

        self::assertTrue($this->evaluator->isPositionAccessible($position, $profile));
    }

    public function testPublicPositionIgnoresStaleAccessRules(): void
    {
        // Public and restricted are mutually exclusive: even if stale rules
        // are still attached, a public position stays open for everyone.
        $gpa = $this->createAttribute(1, 'GPA', new AttributeCategory('Domain Knowledge'), AttributeDataType::NUMERIC);
        $profile = $this->createProfile(); // no values at all

        $position = new Position('Open position', 'No rules');
        $position->setPublic(true);
        $position->getAccessRules()->add(new PositionAccessRule($position, $gpa, AccessRuleOperator::GREATER_THAN_OR_EQUAL, '4.0'));

        self::assertTrue($this->evaluator->isPositionAccessible($position, $profile));
    }

    public function testAllRulesMatchReturnsTrue(): void
    {
        // GPA >= 3.5 AND English = C1 AND Remote checked.
        $gpa = $this->createAttribute(1, 'GPA', new AttributeCategory('Domain Knowledge'), AttributeDataType::NUMERIC);
        $english = $this->createAttribute(2, 'English', new AttributeCategory('Soft Skills'), AttributeDataType::ONE_OF_MANY);
        $remote = $this->createAttribute(3, 'Remote', new AttributeCategory('Personal Information'), AttributeDataType::BOOLEAN);

        $profile = $this->createProfile();
        $profile->getAttributeValues()->add($this->createValue($profile, $gpa, valueNumeric: '3.8'));
        $profile->getAttributeValues()->add($this->createValue($profile, $english, valueString: 'C1'));
        $profile->getAttributeValues()->add($this->createValue($profile, $remote, valueBoolean: true));

        $position = new Position('Senior role', 'Demanding position');
        $position->setPublic(false);
        $position->getAccessRules()->add(new PositionAccessRule($position, $gpa, AccessRuleOperator::GREATER_THAN_OR_EQUAL, '3.5'));
        $position->getAccessRules()->add(new PositionAccessRule($position, $english, AccessRuleOperator::EQUALS, 'C1'));
        $position->getAccessRules()->add(new PositionAccessRule($position, $remote, AccessRuleOperator::IS_CHECKED, ''));

        self::assertTrue($this->evaluator->isPositionAccessible($position, $profile));
    }

    public function testSingleFailingRuleReturnsFalse(): void
    {
        $gpa = $this->createAttribute(1, 'GPA', new AttributeCategory('Domain Knowledge'), AttributeDataType::NUMERIC);
        $english = $this->createAttribute(2, 'English', new AttributeCategory('Soft Skills'), AttributeDataType::ONE_OF_MANY);

        $profile = $this->createProfile();
        $profile->getAttributeValues()->add($this->createValue($profile, $gpa, valueNumeric: '3.0'));
        $profile->getAttributeValues()->add($this->createValue($profile, $english, valueString: 'C1'));

        $position = new Position('Senior role', 'Demanding position');
        $position->setPublic(false);
        $position->getAccessRules()->add(new PositionAccessRule($position, $gpa, AccessRuleOperator::GREATER_THAN_OR_EQUAL, '3.5'));
        $position->getAccessRules()->add(new PositionAccessRule($position, $english, AccessRuleOperator::EQUALS, 'C1'));

        self::assertFalse($this->evaluator->isPositionAccessible($position, $profile));
    }

    public function testMissingAttributeValueFailsTheRule(): void
    {
        $gpa = $this->createAttribute(1, 'GPA', new AttributeCategory('Domain Knowledge'), AttributeDataType::NUMERIC);

        $profile = $this->createProfile(); // candidate did not fill in GPA

        $position = new Position('Senior role', 'Demanding position');
        $position->setPublic(false);
        $position->getAccessRules()->add(new PositionAccessRule($position, $gpa, AccessRuleOperator::GREATER_THAN_OR_EQUAL, '3.5'));

        self::assertFalse($this->evaluator->isPositionAccessible($position, $profile));
    }

    public function testNonPublicPositionWithoutRulesIsAccessible(): void
    {
        $position = new Position('Internal position', 'No rules');
        $position->setPublic(false);

        self::assertTrue($this->evaluator->isPositionAccessible($position, $this->createProfile()));
    }

    private function createProfile(): CandidateProfile
    {
        return new CandidateProfile(new User('candidate@example.com'), 'Jane', 'Doe');
    }

    private function createAttribute(
        int $id,
        string $name,
        AttributeCategory $category,
        AttributeDataType $dataType,
    ): Attribute {
        $attribute = new Attribute($name, $category, $dataType);
        (new ReflectionProperty(Attribute::class, 'id'))->setValue($attribute, $id);

        return $attribute;
    }

    private function createValue(
        CandidateProfile $profile,
        Attribute $attribute,
        ?string $valueString = null,
        ?string $valueNumeric = null,
        ?bool $valueBoolean = null,
    ): CandidateAttributeValue {
        $value = new CandidateAttributeValue($profile, $attribute);
        $value->setValueString($valueString);
        $value->setValueNumeric($valueNumeric);
        $value->setValueBoolean($valueBoolean);

        return $value;
    }
}
