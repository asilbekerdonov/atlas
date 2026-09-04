<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when changing the data type of an attribute that already has
 * candidate values (would corrupt stored data). Maps to HTTP 400/422.
 */
final class AttributeTypeChangeForbiddenException extends \RuntimeException
{
    public function __construct(string $attributeName)
    {
        parent::__construct(sprintf(
            'Cannot change the data type of attribute "%s" because it already has values.',
            $attributeName,
        ));
    }
}
