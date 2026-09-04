<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Result of deleting a candidate's attribute value: the profile version
 * (bumped — the profile changed) and the list of published CVs that were
 * automatically reverted to DRAFT because they used the removed attribute.
 */
final readonly class ProfileAttributeValueDeleteResultDTO
{
    /**
     * @param list<array{cvId: int, positionTitle: string}> $unpublishedCvs
     */
    public function __construct(
        public int $newVersion,
        public array $unpublishedCvs,
    ) {
    }
}
