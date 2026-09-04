<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Attribute;
use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\User;
use App\Enum\AccessRuleOperator;
use App\Entity\AttributeCategory;
use App\Enum\AttributeDataType;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies the single-query Double NOT EXISTS filter against real PostgreSQL.
 * All data is created inside a transaction that is rolled back in tearDown.
 */
final class PositionRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PositionRepository $repository;
    private int $profileId;

    private Position $publicPosition;
    private Position $matchingPosition;
    private Position $blockedPosition;
    private Position $noRulesPosition;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(PositionRepository::class);

        $this->em->beginTransaction();
        $this->seed();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback();
        }
        parent::tearDown();
    }

    public function testFiltersToAccessiblePositionsOnly(): void
    {
        $result = $this->repository->findPositionsForCandidate($this->profileId, onlyAccessible: true);

        self::assertSame(3, $result['total']);
        self::assertCount(3, $result['items']);
        self::assertSame(
            [$this->publicPosition->getId(), $this->matchingPosition->getId(), $this->noRulesPosition->getId()],
            $this->sortedIds($result['items']),
        );
        self::assertNotContains($this->blockedPosition->getId(), $this->sortedIds($result['items']));
    }

    public function testReturnsAllPositionsWithAccessibilityFlag(): void
    {
        $result = $this->repository->findPositionsForCandidate($this->profileId, onlyAccessible: false);

        self::assertSame(4, $result['total']);
        self::assertCount(4, $result['items']);

        $flags = $result['accessibleById'];
        self::assertTrue($flags[$this->publicPosition->getId()]);
        self::assertTrue($flags[$this->matchingPosition->getId()]);
        self::assertFalse($flags[$this->blockedPosition->getId()]);
        self::assertTrue($flags[$this->noRulesPosition->getId()]);
    }

    public function testPagination(): void
    {
        $page1 = $this->repository->findPositionsForCandidate($this->profileId, onlyAccessible: true, page: 1, limit: 2);
        self::assertCount(2, $page1['items']);
        self::assertSame(3, $page1['total']);

        $page2 = $this->repository->findPositionsForCandidate($this->profileId, onlyAccessible: true, page: 2, limit: 2);
        self::assertCount(1, $page2['items']);
        self::assertSame(3, $page2['total']);
    }

    public function testFindTopByCvCountSortsByAttachedCvs(): void
    {
        $candidate2 = new User('candidate2@example.com');
        $this->em->persist($candidate2);

        $busy = new Position('Busy position', 'Two CVs');
        $this->em->persist($busy);
        $this->em->persist(new Cv($this->cvCandidate(), $busy));
        $this->em->persist(new Cv($candidate2, $busy));

        $quiet = new Position('Quiet position', 'No CVs');
        $this->em->persist($quiet);
        $this->em->flush();

        $top = $this->repository->findTopByCvCount(10); // limit must cover ALL seeded rows

        self::assertSame($busy->getId(), $top[0]->getId(), 'most CVs must rank first');
        self::assertContains($quiet->getId(), array_map(static fn (Position $p): int => $p->getId(), $top));
    }

    private function cvCandidate(): User
    {
        $candidate = $this->em->getRepository(User::class)->findOneBy(['email' => 'candidate@example.com']);
        self::assertNotNull($candidate);

        return $candidate;
    }

    private function seed(): void
    {
        $candidate = new User('candidate@example.com');
        $profile = new CandidateProfile($candidate, 'Jane', 'Doe');
        $this->em->persist($candidate);
        $this->em->persist($profile);

        $yearsCategory = $this->em->getRepository(AttributeCategory::class)->findOneBy(['name' => 'Domain Knowledge']);
        if ($yearsCategory === null) {
            $yearsCategory = new AttributeCategory('Domain Knowledge');
            $this->em->persist($yearsCategory);
        }
        $years = new Attribute('Years of experience', $yearsCategory, AttributeDataType::NUMERIC);
        $this->em->persist($years);

        $value = new CandidateAttributeValue($profile, $years);
        $value->setValueNumeric('5');
        $this->em->persist($value);

        $this->publicPosition = new Position('Public position', 'Open to everyone');
        $this->publicPosition->setPublic(true);
        $this->em->persist($this->publicPosition);

        $this->matchingPosition = new Position('Matching position', 'Needs 3+ years');
        $this->matchingPosition->setPublic(false);
        $this->em->persist($this->matchingPosition);
        $this->em->persist(new PositionAccessRule($this->matchingPosition, $years, AccessRuleOperator::GREATER_THAN_OR_EQUAL, '3'));

        $this->blockedPosition = new Position('Blocked position', 'Needs 10+ years');
        $this->blockedPosition->setPublic(false);
        $this->em->persist($this->blockedPosition);
        $this->em->persist(new PositionAccessRule($this->blockedPosition, $years, AccessRuleOperator::GREATER_THAN, '10'));

        $this->noRulesPosition = new Position('No rules position', 'Non-public without rules');
        $this->noRulesPosition->setPublic(false);
        $this->em->persist($this->noRulesPosition);

        $this->em->flush();
        $this->em->clear();

        $this->profileId = $profile->getId();
    }

    /** @param Position[] $positions */
    private function sortedIds(array $positions): array
    {
        $ids = array_map(static fn (Position $p): int => $p->getId(), $positions);
        sort($ids);

        return $ids;
    }
}
