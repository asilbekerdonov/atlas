<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Cv;
use App\Entity\Position;
use App\Enum\CvStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** Data access for CVs. */
class CvRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cv::class);
    }

    /** How many CVs were created since the given moment (any status). */
    public function countNewSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Published, visible CVs of a position — the ONLY ones recruiters may see
     * (candidate drafts must never leak into the recruiter view).
     *
     * @return list<Cv>
     */
    public function findPublishedByPosition(Position $position): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.position = :position')
            ->andWhere('c.status = :published')
            ->andWhere('c.isVisible = true')
            ->setParameter('position', $position)
            ->setParameter('published', CvStatus::PUBLISHED)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Published CVs no recruiter has opened yet — drives the header badge.
     */
    public function countNewForRecruiter(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :published')
            ->andWhere('c.isVisible = true')
            ->andWhere('c.viewedByRecruiterAt IS NULL')
            ->setParameter('published', CvStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Unviewed published CVs for ONE position — same "new" semantics as
     * countNewForRecruiter(), scoped to the given position. Drives the small
     * badge on the CV icon of the position page.
     */
    public function countNewForRecruiterByPosition(Position $position): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.position = :position')
            ->andWhere('c.status = :published')
            ->andWhere('c.isVisible = true')
            ->andWhere('c.viewedByRecruiterAt IS NULL')
            ->setParameter('position', $position)
            ->setParameter('published', CvStatus::PUBLISHED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Search published CVs by the candidate's first/last name.
     *
     * @return list<Cv>
     */
    public function search(string $query, int $limit = 20): array
    {
        if ($query === '') {
            return [];
        }

        $qb = $this->createQueryBuilder('c')
            ->join('c.candidate', 'u')
            ->join('u.profile', 'p')
            ->where('c.status = :published')
            ->andWhere('LOWER(p.firstName) LIKE :q OR LOWER(p.lastName) LIKE :q OR LOWER(CONCAT(p.firstName, \' \', p.lastName)) LIKE :q')
            ->setParameter('published', CvStatus::PUBLISHED)
            ->setParameter('q', '%' . mb_strtolower($query) . '%')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }
}
