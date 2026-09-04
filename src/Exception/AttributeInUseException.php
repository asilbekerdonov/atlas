<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when deleting an attribute that is still referenced by candidate
 * values, position templates or access rules. Maps to HTTP 409.
 */
final class AttributeInUseException extends \RuntimeException
{
    public function __construct(
        string $attributeName,
        private readonly int $usageCount,
    ) {
        parent::__construct(sprintf(
            'Attribute "%s" cannot be deleted: it is used by %d record(s).',
            $attributeName,
            $usageCount,
        ));
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }
}
