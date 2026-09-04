<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Attribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Data access for the attribute library list.
 */
class AttributeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attribute::class);
    }

    /**
     * All attributes ordered by name with their category fetched via JOIN
     * (single query — no per-row lazy loads when rendering category.name).
     *
     * @return list<Attribute>
     */
    public function findAllWithCategory(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a', 'c')
            ->join('a.category', 'c')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();

        // ORM 3 hydrates the root entity; pair-row shapes handled defensively.
        $attributes = [];
        foreach ($rows as $row) {
            if ($row instanceof Attribute) {
                $attributes[] = $row;
            } elseif (is_array($row)) {
                $attribute = $row['a'] ?? reset($row);
                if ($attribute instanceof Attribute) {
                    $attributes[] = $attribute;
                }
            }
        }

        return $attributes;
    }
}
