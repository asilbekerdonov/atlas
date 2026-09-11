<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\UserRole;
use App\Entity\User;

class UserRepository extends \Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository
{
    public function __construct(\Doctrine\Persistence\ManagerRegistry $registry)
    {
        parent::__construct($registry, \App\Entity\User::class);
    }

/**
 * All users, newest first.
 *
 * @return list<User>
 */



    public function findAllOrderedByCreatedAtDesc(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function countByRole(UserRole $role): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM "user" WHERE roles::jsonb @> :role',
            ['role' => json_encode([$role->value], JSON_THROW_ON_ERROR)],
        );
    }
}