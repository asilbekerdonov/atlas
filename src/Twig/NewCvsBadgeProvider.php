<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\CvRepository;
use DateTimeImmutable;

/**
 * Exposed as a lazy Twig global ("newCvsBadge") — renders the number of CVs
 * submitted in the last 24 hours so recruiters notice fresh applications.
 */
final class NewCvsBadgeProvider
{
    public function __construct(private readonly CvRepository $cvRepository)
    {
    }

    public function newCvsCount(): int
    {
        return $this->cvRepository->countNewForRecruiter();
    }
}
