<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Attribute;

/**
 * Thrown when publishing a CV while required attributes of the position
 * template are not filled in. Maps to HTTP 422 and carries the list of
 * missing attributes for the frontend to highlight.
 */
final class CvIncompleteException extends \RuntimeException
{
    /** @param list<Attribute> $missingAttributes */
    public function __construct(private readonly array $missingAttributes)
    {
        parent::__construct(sprintf(
            'CV cannot be published: %d required attribute(s) are not filled in.',
            count($missingAttributes),
        ));
    }

    /** @return list<Attribute> */
    public function getMissingAttributes(): array
    {
        return $this->missingAttributes;
    }
}
