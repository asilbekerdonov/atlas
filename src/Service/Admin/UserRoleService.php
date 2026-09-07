<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\User;
use App\Enum\UserRole;
use App\Exception\UserRoleChangeException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Admin panel: promote (candidate -> recruiter) and demote
 * (recruiter -> candidate). Administrator accounts are immutable here —
 * their role is only ever changed through direct DB/console actions.
 */
final class UserRoleService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** Promotes a candidate to recruiter. @throws UserRoleChangeException */
    public function promote(User $user): void
    {
        $this->assertChangeable($user);

        if (!$user->hasRole(UserRole::ROLE_CANDIDATE)) {
            throw new UserRoleChangeException('Only candidates can be promoted to recruiter.');
        }

        $user->removeRole(UserRole::ROLE_CANDIDATE);
        $user->addRole(UserRole::ROLE_RECRUITER);
        $this->em->flush();
    }

    /** Demotes a recruiter back to candidate. @throws UserRoleChangeException */
    public function demote(User $user): void
    {
        $this->assertChangeable($user);

        if (!$user->hasRole(UserRole::ROLE_RECRUITER)) {
            throw new UserRoleChangeException('Only recruiters can be demoted to candidate.');
        }

        $user->removeRole(UserRole::ROLE_RECRUITER);
        $user->addRole(UserRole::ROLE_CANDIDATE);
        $this->em->flush();
    }

    private function assertChangeable(User $user): void
    {
        if ($user->hasRole(UserRole::ROLE_ADMIN)) {
            throw new UserRoleChangeException('Administrator roles cannot be changed.');
        }
    }
}
