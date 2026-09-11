<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\Entity\CandidateProfile;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class CandidateProfileService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function getOrCreateFor(User $user): CandidateProfile
    {
        $profile = $user->getProfile();
        if ($profile !== null) {
            return $profile;
        }

        $profile = new CandidateProfile($user, 'Candidate', '');
        $this->em->persist($profile);
        $this->em->flush();

        return $profile;
    }
}
