<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\CandidateProfile;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Profile access:
 * - ADMIN: full access to every profile.
 * - CANDIDATE: only their own profile.
 * - RECRUITER: no profile access at all.
 */
final class ProfileVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    // public const PROFILE_EDIT = 'PROFILE_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true)
            && $subject instanceof CandidateProfile;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->hasRole(UserRole::ROLE_ADMIN)) {
            return true;
        }

        if ($user->hasRole(UserRole::ROLE_CANDIDATE)) {
            return $subject->getUser() === $user || $subject->getUser()->getId() === $user->getId();
        }

        return false; // recruiters never access candidate profiles
    }
}
