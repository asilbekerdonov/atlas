<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** Data access for the Tagify autocomplete. */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /** @return list<Tag> case-insensitive prefix match, ordered by name */
    public function findByPrefix(string $prefix, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults(max(1, min(50, $limit)));

        if ($prefix !== '') {
            $qb->where('LOWER(t.name) LIKE :prefix')
                ->setParameter('prefix', mb_strtolower($prefix) . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
