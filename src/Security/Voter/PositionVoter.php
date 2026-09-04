<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Position;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Position access:
 * - ADMIN / RECRUITER: full control (positions are shared, no ownership).
 * - CANDIDATE / anonymous: read-only VIEW.
 */
final class PositionVoter extends Voter
{
    public const CREATE = 'CREATE';
    public const EDIT = 'EDIT';
    public const DUPLICATE = 'DUPLICATE';
    public const DELETE = 'DELETE';
    public const VIEW = 'VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // CREATE has no subject; every other attribute acts on a Position.
        return in_array($attribute, [self::CREATE, self::EDIT, self::DUPLICATE, self::DELETE, self::VIEW], true)
            && ($subject === null || $subject instanceof Position);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if ($user instanceof User && ($user->hasRole(UserRole::ROLE_ADMIN) || $user->hasRole(UserRole::ROLE_RECRUITER))) {
            return true;
        }

        return $attribute === self::VIEW; // candidates and guests: read-only
    }
}
