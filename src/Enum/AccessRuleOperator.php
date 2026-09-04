<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Comparison operator used by a PositionAccessRule to filter candidates.
 */
enum AccessRuleOperator: string
{
    case EQUALS = 'EQUALS';
    case NOT_EQUALS = 'NOT_EQUALS';
    case GREATER_THAN = 'GREATER_THAN';
    case GREATER_THAN_OR_EQUAL = 'GREATER_THAN_OR_EQUAL';
    case LESS_THAN = 'LESS_THAN';
    case LESS_THAN_OR_EQUAL = 'LESS_THAN_OR_EQUAL';
    case IS_CHECKED = 'IS_CHECKED';
    case CONTAINS = 'CONTAINS';
}
