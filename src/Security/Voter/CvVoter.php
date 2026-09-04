<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Cv;
use App\Entity\User;
use App\Enum\CvStatus;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * CV access:
 * - ADMIN: everything.
 * - CANDIDATE: VIEW / EDIT / PUBLISH only on their own CVs; cannot like.
 * - RECRUITER: VIEW only published/visible CVs; LIKE allowed; no EDIT/PUBLISH.
 */
final class CvVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const PUBLISH = 'PUBLISH';
    public const LIKE = 'LIKE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::PUBLISH, self::LIKE], true)
            && $subject instanceof Cv;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return $user === null && $subject->isVisible() && $subject->getStatus() === CvStatus::PUBLISHED && $attribute === self::VIEW;
        }

        if ($user->hasRole(UserRole::ROLE_ADMIN)) {
            return true;
        }

        if ($user->hasRole(UserRole::ROLE_CANDIDATE)) {
            $isOwner = $subject->getCandidate() === $user || $subject->getCandidate()->getId() === $user->getId();

            return $isOwner && in_array($attribute, [self::VIEW, self::EDIT, self::PUBLISH], true);
        }

        // Recruiter.
        if ($attribute === self::LIKE) {
            return true;
        }

        return $attribute === self::VIEW
            && $subject->isVisible()
            && $subject->getStatus() === CvStatus::PUBLISHED;
    }
}
