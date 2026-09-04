<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when creating/renaming an attribute whose name already exists
 * (unique constraint on attribute.name). Maps to HTTP 409.
 */
final class AttributeAlreadyExistsException extends \RuntimeException
{
    public function __construct(string $attributeName)
    {
        parent::__construct(sprintf('Attribute "%s" already exists.', $attributeName));
    }
}
