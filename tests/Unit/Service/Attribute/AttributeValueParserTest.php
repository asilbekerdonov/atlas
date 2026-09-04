<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Attribute;

use App\Entity\Attribute;
use App\Entity\AttributeOption;
use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\User;
use App\Entity\AttributeCategory;
use App\Enum\AttributeDataType;
use App\Service\Attribute\AttributeValueParser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class AttributeValueParserTest extends TestCase
{
    private AttributeValueParser $parser;
    private CandidateAttributeValue $value;

    protected function setUp(): void
    {
        $this->parser = new AttributeValueParser($this->createStub(EntityManagerInterface::class));
        $this->value = new CandidateAttributeValue(
            new CandidateProfile(new User('c@example.com'), 'Jane', 'Doe'),
            new Attribute('test', new AttributeCategory('Personal Information'), AttributeDataType::STRING),
        );
    }

    public function testStringAndImageMapToValueString(): void
    {
        $stringAttr = $this->attribute(AttributeDataType::STRING);
        $this->parser->apply($this->value, $stringAttr, 'C1');
        self::assertSame('C1', $this->value->getValueString());
        self::assertTrue($this->value->isEmpty() === false);

        $this->parser->apply($this->value, $this->attribute(AttributeDataType::IMAGE), 'https://cdn.example/a.jpg');
        self::assertSame('https://cdn.example/a.jpg', $this->value->getValueString());
    }

    public function testTextMapsToValueText(): void
    {
        $this->parser->apply($this->value, $this->attribute(AttributeDataType::TEXT), 'Long description');
        self::assertSame('Long description', $this->value->getValueText());
    }

    public function testNumericAcceptsCommaDecimal(): void
    {
        $attr = $this->attribute(AttributeDataType::NUMERIC);
        $this->parser->apply($this->value, $attr, '5,5');
        self::assertSame('5.5', $this->value->getValueNumeric());

        $this->expectException(InvalidArgumentException::class);
        $this->parser->apply($this->value, $attr, 'abc');
    }

    public function testDateParsing(): void
    {
        $attr = $this->attribute(AttributeDataType::DATE);
        $this->parser->apply($this->value, $attr, '2024-06-01');
        self::assertEquals(new DateTimeImmutable('2024-06-01'), $this->value->getValueDate());

        $this->expectException(InvalidArgumentException::class);
        $this->parser->apply($this->value, $attr, '01.06.2024');
    }

    public function testBooleanParsing(): void
    {
        $attr = $this->attribute(AttributeDataType::BOOLEAN);
        $this->parser->apply($this->value, $attr, true);
        self::assertTrue($this->value->getValueBoolean());

        $this->parser->apply($this->value, $attr, 'false');
        self::assertFalse($this->value->getValueBoolean());

        $this->expectException(InvalidArgumentException::class);
        $this->parser->apply($this->value, $attr, 'maybe');
    }

    public function testPeriodParsing(): void
    {
        $this->parser->apply($this->value, $this->attribute(AttributeDataType::PERIOD), [
            'start' => '2024-01-01',
            'end' => '2024-06-30',
        ]);
        self::assertEquals(new DateTimeImmutable('2024-01-01'), $this->value->getValueDateRangeStart());
        self::assertEquals(new DateTimeImmutable('2024-06-30'), $this->value->getValueDateRangeEnd());
    }

    public function testOptionResolvedThroughEntityManager(): void
    {
        $attr = $this->attribute(AttributeDataType::ONE_OF_MANY, 1);
        $option = new AttributeOption($attr, 'C1', 'C1');
        (new ReflectionProperty(AttributeOption::class, 'id'))->setValue($option, 42);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('find')
            ->with(AttributeOption::class, 42)
            ->willReturn($option);
        $this->parser = new AttributeValueParser($em);

        $this->parser->apply($this->value, $attr, 42);
        self::assertSame($option, $this->value->getValueOption());
    }

    public function testOptionNotBelongingToAttributeIsRejected(): void
    {
        $attr = $this->attribute(AttributeDataType::ONE_OF_MANY, 1);
        $otherAttr = $this->attribute(AttributeDataType::ONE_OF_MANY, 2);
        $option = new AttributeOption($otherAttr, 'B2', 'B2');
        (new ReflectionProperty(AttributeOption::class, 'id'))->setValue($option, 7);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('find')
            ->with(AttributeOption::class, 7)
            ->willReturn($option);
        $this->parser = new AttributeValueParser($em);

        $this->expectException(InvalidArgumentException::class);
        $this->parser->apply($this->value, $attr, 7);
    }

    public function testEmptyInputClearsAllColumns(): void
    {
        $attr = $this->attribute(AttributeDataType::STRING);
        $this->parser->apply($this->value, $attr, 'C1');
        $this->parser->apply($this->value, $attr, null);
        self::assertTrue($this->value->isEmpty());
    }

    private function attribute(AttributeDataType $dataType, int $id = 0): Attribute
    {
        $attribute = new Attribute('test', new AttributeCategory('Personal Information'), $dataType);
        (new ReflectionProperty(Attribute::class, 'id'))->setValue($attribute, $id);

        return $attribute;
    }
}
