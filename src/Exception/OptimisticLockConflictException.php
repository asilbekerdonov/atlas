<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when a client submits an outdated version of a versioned entity
 * (autosave, in-place editing, position update). Maps to HTTP 409 and
 * carries the current persisted entity so the client can rebase.
 */
final class OptimisticLockConflictException extends \RuntimeException
{
    public function __construct(
        private readonly object $entity,
        private readonly int $currentVersion,
    ) {
        parent::__construct(sprintf(
            'Concurrent modification detected for "%s": expected another version, current is %d.',
            $entity::class,
            $currentVersion,
        ));
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function getCurrentVersion(): int
    {
        return $this->currentVersion;
    }
}
