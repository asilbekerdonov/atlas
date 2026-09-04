<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when a candidate tries to attach a CV to a position whose access
 * rules they do not satisfy. Maps to HTTP 403.
 */
final class PositionAccessDeniedException extends \RuntimeException
{
    public function __construct(int $positionId)
    {
        parent::__construct(sprintf(
            'Candidate is not allowed to create a CV for position #%d: access rules are not satisfied.',
            $positionId,
        ));
    }
}
