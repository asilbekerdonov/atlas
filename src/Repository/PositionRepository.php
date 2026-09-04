<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Position;
use App\Service\AccessRule\OperatorInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Data access for Position. The feed query filters positions by access rules
 * in a SINGLE SQL statement (double NOT EXISTS), so no N+1 loops in PHP.
 */
class PositionRepository extends ServiceEntityRepository
{
    private const VALUE_ALIAS = 'cav';
    private const RULE_ALIAS = 'par';

    /** @var list<OperatorInterface> */
    private array $operators;

    public function __construct(
        ManagerRegistry $registry,
        #[AutowireIterator('app.access_rule_operator')] iterable $operators,
    ) {
        parent::__construct($registry, Position::class);
        $this->operators = [...$operators];
    }

    /**
     * Paginated feed of positions for a candidate.
     *
     * Double NOT EXISTS semantics: a position is accessible when NO rule
     * exists for which NO satisfying candidate value exists.
     *
     * @param bool $onlyAccessible true — only positions passing the rules;
     *                             false — all positions, with an accessibility
     *                             flag per position (accessibleById).
     *
     * @return array{items: Position[], total: int, accessibleById: array<int, bool>}
     */
    public function findPositionsForCandidate(
        int $candidateProfileId,
        bool $onlyAccessible = true,
        int $page = 1,
        int $limit = 20,
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;

        $accessibleExpr = sprintf(
            '(p.is_public = TRUE OR NOT EXISTS ('
            . 'SELECT 1 FROM position_access_rule %1$s '
            . 'WHERE %1$s.position_id = p.id AND NOT EXISTS ('
            . 'SELECT 1 FROM candidate_attribute_value %2$s '
            . 'WHERE %2$s.profile_id = :profileId '
            . 'AND %2$s.attribute_id = %1$s.attribute_id AND (%3$s))))',
            self::RULE_ALIAS,
            self::VALUE_ALIAS,
            $this->buildRuleConditions(),
        );

        $where = 'p.deleted_at IS NULL' . ($onlyAccessible ? ' AND ' . $accessibleExpr : '');

        $selectList = $onlyAccessible ? 'p.*' : 'p.*, ' . $accessibleExpr . ' AS is_accessible';
        $sql = sprintf(
            'SELECT %s FROM position p WHERE %s ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset',
            $selectList,
            $where,
        );

        $em = $this->getEntityManager();
        $rsm = new ResultSetMappingBuilder($em);
        $rsm->addRootEntityFromClassMetadata(Position::class, 'p');
        if (!$onlyAccessible) {
            $rsm->addScalarResult('is_accessible', 'is_accessible', 'boolean');
        }

        $query = $em->createNativeQuery($sql, $rsm);
        $query->setParameter('profileId', $candidateProfileId, Types::INTEGER);
        $query->setParameter('limit', $limit, Types::INTEGER);
        $query->setParameter('offset', $offset, Types::INTEGER);

        $items = [];
        $accessibleById = [];
        foreach ($query->getResult() as $row) {
            if ($row instanceof Position) {
                $items[] = $row;
                $accessibleById[$row->getId()] = true;
            } else {
                // With a scalar mapping present, the root entity sits under
                // the numeric index 0 (ResultSetMappingBuilder behavior).
                $position = $row[0];
                $items[] = $position;
                $accessibleById[$position->getId()] = (bool) $row['is_accessible'];
            }
        }

        $total = (int) $em->getConnection()
            ->executeQuery('SELECT COUNT(*) FROM position p WHERE ' . $where, [
                'profileId' => $candidateProfileId,
            ], [
                'profileId' => Types::INTEGER,
            ])
            ->fetchOne();

        return [
            'items' => $items,
            'total' => $total,
            'accessibleById' => $accessibleById,
        ];
    }

    /**
     * ORs the SQL fragments of every registered operator. All conditions are
     * static code (column-to-column comparisons), never user input.
     */
    private function buildRuleConditions(): string
    {
        $conditions = array_map(
            fn (OperatorInterface $operator): string => $operator->getSqlCondition(self::VALUE_ALIAS, self::RULE_ALIAS),
            $this->operators,
        );

        if ($conditions === []) {
            throw new LogicException('No access rule operators registered (tag "app.access_rule_operator").');
        }

        return implode(' OR ', $conditions);
    }

    /** @return list<Position> newest non-deleted positions (tags + CVs fetched) */
    public function findLatest(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.tags', 't')->addSelect('t')
            ->leftJoin('p.cvs', 'c')->addSelect('c')
            ->where('p.deletedAt IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Position> non-deleted positions with most attached CVs (CVs fetched) */
    public function findTopByCvCount(int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p', 'c')
            ->leftJoin('p.cvs', 'c')
            ->where('p.deletedAt IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        // ORM 3 hydrates the root entities (CVs land in the fetched collection);
        // handle both plain-entity and pair-row shapes defensively.
        $byId = [];
        foreach ($rows as $row) {
            $position = is_array($row) ? ($row['p'] ?? reset($row)) : $row;
            $byId[$position->getId()] = $position;
        }

        $result = array_values($byId);
        usort($result, static fn (Position $a, Position $b): int => $b->getCvs()->count() <=> $a->getCvs()->count());

        return array_slice($result, 0, $limit);
    }

    /**
     * Tag cloud: tags ordered by how many projects and positions reference them.
     *
     * @return list<array{name: string, count: int}>
     */
    public function findTagCloud(int $limit = 20): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<'SQL'
            SELECT name, SUM(c) AS count FROM (
                SELECT t.name AS name, COUNT(*) AS c FROM project_tag pt JOIN tag t ON t.id = pt.tag_id GROUP BY t.name
                UNION ALL
                SELECT t.name AS name, COUNT(*) AS c FROM position_tag pt JOIN tag t ON t.id = pt.tag_id GROUP BY t.name
            ) merged GROUP BY name ORDER BY count DESC, name ASC LIMIT :limit
        SQL;

        return $conn->executeQuery($sql, ['limit' => $limit], ['limit' => Types::INTEGER])->fetchAllAssociative();
    }

    /**
     * Public counters for the home page.
     *
     * @return array{positions: int, cvs: int, cvs_last_24h: int, candidates: int, recruiters: int}
     */
    public function getStats(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = <<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM position WHERE deleted_at IS NULL) AS positions,
                (SELECT COUNT(*) FROM cv) AS cvs,
                (SELECT COUNT(*) FROM cv WHERE created_at >= NOW() - INTERVAL '24 hours') AS cvs_last_24h,
                (SELECT COUNT(*) FROM "user" WHERE roles::jsonb @> '["ROLE_CANDIDATE"]'::jsonb) AS candidates,
                (SELECT COUNT(*) FROM "user" WHERE roles::jsonb @> '["ROLE_RECRUITER"]'::jsonb) AS recruiters
        SQL;

        return $conn->executeQuery($sql)->fetchAssociative();
    }

    /**
     * All tags of the given positions in ONE query (avoids N+1 on lazy
     * ManyToMany collections after native-query hydration).
     *
     * @param list<int> $positionIds
     *
     * @return array<int, list<array{id: int, name: string}>> position id => tags
     */
    public function findTagsByPositionIds(array $positionIds): array
    {
        if ($positionIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()
            ->executeQuery(
                'SELECT pt.position_id AS pid, t.id AS tid, t.name AS tname
                 FROM position_tag pt JOIN tag t ON t.id = pt.tag_id
                 WHERE pt.position_id IN (:ids)',
                ['ids' => $positionIds],
                ['ids' => ArrayParameterType::INTEGER],
            )
            ->fetchAllAssociative();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['pid']][] = ['id' => (int) $row['tid'], 'name' => $row['tname']];
        }

        return $map;
    }

    /**
     * CV counts (total and last-24h) per position in ONE query.
     *
     * @param list<int> $positionIds
     *
     * @return array<int, array{total: int, new24: int}>
     */
    public function findCvStatsByPositionIds(array $positionIds): array
    {
        if ($positionIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()
            ->executeQuery(
                "SELECT cv.position_id AS pid, COUNT(*) AS total,
                        COUNT(*) FILTER (WHERE cv.created_at >= NOW() - INTERVAL '24 hours') AS new24
                 FROM cv WHERE cv.position_id IN (:ids) GROUP BY cv.position_id",
                ['ids' => $positionIds],
                ['ids' => ArrayParameterType::INTEGER],
            )
            ->fetchAllAssociative();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['pid']] = ['total' => (int) $row['total'], 'new24' => (int) $row['new24']];
        }

        return $map;
    }

    /**
     * PostgreSQL full-text search over positions (GIN tsvector index).
     *
     * @return list<Position>
     */
    public function ftsSearch(string $query, int $limit = 20): array
    {
        $sql = <<<'SQL'
            SELECT id FROM position
            WHERE deleted_at IS NULL
              AND search_vector @@ plainto_tsquery('english', :q)
            ORDER BY ts_rank(search_vector, plainto_tsquery('english', :q)) DESC, created_at DESC
            LIMIT :limit
        SQL;

        $ids = $this->getEntityManager()->getConnection()
            ->executeQuery($sql, ['q' => $query, 'limit' => $limit], ['limit' => Types::INTEGER])
            ->fetchFirstColumn();

        if ($ids === []) {
            return [];
        }

        // Restore the rank order — findBy() does not preserve it.
        $byId = [];
        foreach ($this->findBy(['id' => $ids]) as $position) {
            $byId[$position->getId()] = $position;
        }

        return array_values(array_map(static fn (int $id): Position => $byId[$id], $ids));
    }
}
