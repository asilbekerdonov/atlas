<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when deleting a candidate attribute value that does not exist on
 * the candidate's own profile. Maps to HTTP 404 via the API listener.
 */
final class AttributeValueNotFoundException extends \RuntimeException
{
    public function __construct(int $valueId)
    {
        parent::__construct(sprintf('Attribute value #%d not found.', $valueId));
    }
}
