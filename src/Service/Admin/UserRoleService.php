<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\User;
use App\Enum\UserRole;
use App\Exception\UserRoleChangeException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Admin panel: promote (candidate -> recruiter) and demote
 * (recruiter -> candidate), plus a separate administrator revocation action.
 */
final class UserRoleService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
    )
    {
    }

    /** Promotes a candidate to recruiter. @throws UserRoleChangeException */
    public function promote(User $user, ?User $actor = null): void
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
    public function demote(User $user, ?User $actor = null): void
    {
        $this->assertChangeable($user);

        if (!$user->hasRole(UserRole::ROLE_RECRUITER)) {
            throw new UserRoleChangeException('Only recruiters can be demoted to candidate.');
        }

        $user->removeRole(UserRole::ROLE_RECRUITER);
        $user->addRole(UserRole::ROLE_CANDIDATE);
        $this->em->flush();
    }

    /**
     * Spec: "Administrators may remove their own Administrator role."
     *
     * Safety net: cannot remove the last admin. This rule is NOT in spec,
     * added to prevent an adminless system.
     *
     * @throws UserRoleChangeException
     */
    public function revokeAdmin(User $target, User $actor): void
    {
        if ($target->getId() !== $actor->getId()) {
            throw new UserRoleChangeException('You can only remove your own administrator role.');
        }

        if (!$target->hasRole(UserRole::ROLE_ADMIN)) {
            throw new UserRoleChangeException('User is not an administrator.');
        }

        if ($this->userRepository->countByRole(UserRole::ROLE_ADMIN) <= 1) {
            throw new UserRoleChangeException('Cannot remove the last administrator.');
        }

        $target->removeRole(UserRole::ROLE_ADMIN);
        $target->addRole(UserRole::ROLE_CANDIDATE);
        $this->em->flush();
    }

    private function assertChangeable(User $user): void
    {
        if ($user->hasRole(UserRole::ROLE_ADMIN)) {
            throw new UserRoleChangeException('Use revoke-admin action for administrator accounts.');
        }
    }
}
