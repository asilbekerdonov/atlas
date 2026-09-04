<?php

declare(strict_types=1);

namespace App\Service\Attribute;

use App\Entity\Attribute;
use App\Entity\AttributeOption;
use App\Entity\CandidateAttributeValue;
use App\Enum\AttributeDataType;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Parses a raw client value into the matching typed column of a
 * CandidateAttributeValue. Only one column is ever populated, matching the
 * attribute's AttributeDataType — this keeps the typed storage invariant.
 */
final class AttributeValueParser
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function apply(CandidateAttributeValue $value, Attribute $attribute, mixed $rawValue): void
    {
        $this->reset($value);

        // Empty input means "clear the attribute".
        if ($rawValue === null || $rawValue === '') {
            return;
        }

        switch ($attribute->getDataType()) {
            case AttributeDataType::STRING:
            case AttributeDataType::IMAGE:
                $value->setValueString($this->requireString($rawValue));
                break;

            case AttributeDataType::TEXT:
                $value->setValueText($this->requireString($rawValue));
                break;

            case AttributeDataType::NUMERIC:
                $value->setValueNumeric($this->parseNumeric($rawValue));
                break;

            case AttributeDataType::DATE:
                $value->setValueDate($this->parseDate($rawValue));
                break;

            case AttributeDataType::PERIOD:
                $this->applyPeriod($value, $rawValue);
                break;

            case AttributeDataType::BOOLEAN:
                $value->setValueBoolean($this->parseBoolean($rawValue));
                break;

            case AttributeDataType::ONE_OF_MANY:
                $value->setValueOption($this->parseOption($attribute, $rawValue));
                break;
        }
    }

    private function reset(CandidateAttributeValue $value): void
    {
        $value->setValueString(null);
        $value->setValueText(null);
        $value->setValueNumeric(null);
        $value->setValueDate(null);
        $value->setValueDateRangeStart(null);
        $value->setValueDateRangeEnd(null);
        $value->setValueBoolean(null);
        $value->setValueOption(null);
    }

    private function requireString(mixed $rawValue): string
    {
        if (!is_string($rawValue)) {
            throw new InvalidArgumentException('Expected a string value.');
        }

        return $rawValue;
    }

    private function parseNumeric(mixed $rawValue): string
    {
        $value = str_replace(',', '.', trim((string) $rawValue));
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(sprintf('Invalid numeric value "%s".', (string) $rawValue));
        }

        return $value;
    }

    private function parseDate(mixed $rawValue): DateTimeImmutable
    {
        if (!is_string($rawValue)) {
            throw new InvalidArgumentException('Expected a date string.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $rawValue);
        if ($date === false) {
            throw new InvalidArgumentException(sprintf('Invalid date "%s", expected YYYY-MM-DD.', $rawValue));
        }

        return $date;
    }

    private function applyPeriod(CandidateAttributeValue $value, mixed $rawValue): void
    {
        if (!is_array($rawValue)) {
            throw new InvalidArgumentException('Period value must be an array with "start" and/or "end" dates.');
        }

        if (isset($rawValue['start']) && $rawValue['start'] !== '') {
            $value->setValueDateRangeStart($this->parseDate($rawValue['start']));
        }
        if (isset($rawValue['end']) && $rawValue['end'] !== '') {
            $value->setValueDateRangeEnd($this->parseDate($rawValue['end']));
        }
    }

    private function parseBoolean(mixed $rawValue): bool
    {
        if (is_bool($rawValue)) {
            return $rawValue;
        }
        if (is_int($rawValue) && ($rawValue === 0 || $rawValue === 1)) {
            return $rawValue === 1;
        }
        if (is_string($rawValue)) {
            return match (strtolower(trim($rawValue))) {
                'true', '1', 'on', 'yes' => true,
                'false', '0', 'off', 'no' => false,
                default => throw new InvalidArgumentException(sprintf('Invalid boolean value "%s".', $rawValue)),
            };
        }

        throw new InvalidArgumentException('Invalid boolean value.');
    }

    private function parseOption(Attribute $attribute, mixed $rawValue): AttributeOption
    {
        if (!$rawValue instanceof AttributeOption) {
            if (!is_int($rawValue) && !(is_string($rawValue) && ctype_digit($rawValue))) {
                throw new InvalidArgumentException('ONE_OF_MANY value must be an option id.');
            }
            $rawValue = $this->em->find(AttributeOption::class, (int) $rawValue);
        }

        if (!$rawValue instanceof AttributeOption) {
            throw new InvalidArgumentException('Selected option does not exist.');
        }

        $optionAttribute = $rawValue->getAttribute();
        if ($optionAttribute !== $attribute && $optionAttribute->getId() !== $attribute->getId()) {
            throw new InvalidArgumentException('Selected option does not belong to this attribute.');
        }

        return $rawValue;
    }
}
