<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\CandidateProfile;

/** Result of a successful autosave: the updated profile and its new version. */
final readonly class ProfileAutosaveResultDTO
{
    public function __construct(
        public CandidateProfile $profile,
        public int $newVersion,
    ) {
    }
}
