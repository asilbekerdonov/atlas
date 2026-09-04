<?php

declare(strict_types=1);

namespace App\Service\Social;

use App\Entity\Cv;
use App\Entity\CvLike;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Likes on CVs. The denormalized likesCount on Cv is kept in sync here so
 * list screens can sort by popularity without counting rows.
 */
class CvLikeService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return bool true when the recruiter liked the CV, false when unliked */
    public function toggleLike(Cv $cv, User $recruiter): bool
    {
        if (!$recruiter->hasRole(UserRole::ROLE_RECRUITER) && !$recruiter->hasRole(UserRole::ROLE_ADMIN)) {
            throw new AccessDeniedException('Only recruiters and admins can like CVs.');
        }

        $like = $this->em->getRepository(CvLike::class)->findOneBy([
            'cv' => $cv,
            'recruiter' => $recruiter,
        ]);

        if ($like !== null) {
            $this->em->remove($like);
            $cv->decrementLikes();
            $this->em->flush();

            return false;
        }

        $this->em->persist(new CvLike($cv, $recruiter));
        $cv->incrementLikes();
        $this->em->flush();

        return true;
    }
}
