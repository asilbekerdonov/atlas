<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\CandidateProfile;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\CvRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CvRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CvRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(CvRepository::class);
        $this->em->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            $this->em->rollback();
        }
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    public function testCountNewForRecruiterDropsAfterMarkingViewed(): void
    {
        $position = new Position('Role', 'Desc');
        $this->em->persist($position);

        $seen = $this->candidateWithCv('seen@example.com', $position, viewed: true);
        $unseen = $this->candidateWithCv('unseen@example.com', $position, viewed: false);
        $draft = $this->candidateWithCv('draft@example.com', $position, viewed: false, publish: false);
        $this->em->persist($seen);
        $this->em->persist($unseen);
        $this->em->persist($draft);
        $this->em->flush();

        // Drafts do not count; only the unseen published CV does.
        self::assertSame(1, $this->repository->countNewForRecruiter());

        $unseen->markViewedByRecruiter();
        $this->em->flush();

        self::assertSame(0, $this->repository->countNewForRecruiter());
    }

    public function testFindPublishedByPositionExcludesDrafts(): void
    {
        $position = new Position('Role', 'Desc');
        $this->em->persist($position);
        $published = $this->candidateWithCv('pub@example.com', $position, viewed: true);
        $draft = $this->candidateWithCv('drf@example.com', $position, viewed: false, publish: false);
        $this->em->persist($published);
        $this->em->persist($draft);
        $this->em->flush();

        $cvs = $this->repository->findPublishedByPosition($position);
        self::assertCount(1, $cvs);
        self::assertSame('pub@example.com', $cvs[0]->getCandidate()->getEmail());
    }

    private function candidateWithCv(string $email, Position $position, bool $viewed, bool $publish = true): Cv
    {
        $candidate = new User($email, [UserRole::ROLE_CANDIDATE->value]);
        $this->em->persist($candidate);
        $this->em->persist(new CandidateProfile($candidate, 'First', 'Last'));
        $cv = new Cv($candidate, $position);
        if ($publish) {
            $cv->publish();
        }
        if ($viewed) {
            $cv->markViewedByRecruiter();
        }

        return $cv;
    }
}
