<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\User;
use App\Exception\UserBlockException;
use Doctrine\ORM\EntityManagerInterface;

final class UserBlockService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Toggles block status for $target performed by $actor.
     *
     * @throws UserBlockException when the actor tries to block themselves
     */
    public function toggle(User $target, User $actor): void
    {
        if ($target->getId() === $actor->getId()) {
            throw new UserBlockException('You cannot block yourself.');
        }

        if ($target->isBlocked()) {
            $target->unblock();
        } else {
            $target->block();
        }

        $this->em->flush();
    }
}