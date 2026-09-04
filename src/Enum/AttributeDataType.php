<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Physical value type an attribute stores in CandidateAttributeValue.
 */
enum AttributeDataType: string
{
    case STRING = 'STRING';
    case TEXT = 'TEXT';
    case IMAGE = 'IMAGE';
    case NUMERIC = 'NUMERIC';
    case DATE = 'DATE';
    case PERIOD = 'PERIOD';
    case BOOLEAN = 'BOOLEAN';
    case ONE_OF_MANY = 'ONE_OF_MANY';

    /**
     * Translation key for the human-readable label (rendered via |trans).
     * Never output the raw enum value in the UI.
     */
    public function translationKey(): string
    {
        return match ($this) {
            self::STRING => 'attribute.type.string',
            self::TEXT => 'attribute.type.text',
            self::IMAGE => 'attribute.type.image',
            self::NUMERIC => 'attribute.type.numeric',
            self::DATE => 'attribute.type.date',
            self::PERIOD => 'attribute.type.period',
            self::BOOLEAN => 'attribute.type.boolean',
            self::ONE_OF_MANY => 'attribute.type.one_of_many',
        };
    }
}
