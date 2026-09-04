<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Social;

use App\Entity\CandidateProfile;
use App\Entity\Cv;
use App\Entity\CvLike;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\UserRole;
use App\Service\Social\CvLikeService;
use App\Tests\Integration\Service\AbstractServiceIntegrationTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class CvLikeServiceTest extends AbstractServiceIntegrationTestCase
{
    private CvLikeService $service;
    private Cv $cv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->service(CvLikeService::class);

        $candidate = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $this->em->persist($candidate);
        $this->em->persist(new CandidateProfile($candidate, 'Jane', 'Doe'));
        $position = new Position('Senior Backend', 'Desc');
        $this->em->persist($position);
        $this->cv = new Cv($candidate, $position);
        $this->em->persist($this->cv);
        $this->em->flush();
    }

    public function testCandidateCannotLike(): void
    {
        $candidate = new User('another@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $this->em->persist($candidate);
        $this->em->flush();

        $this->expectException(AccessDeniedException::class);
        $this->service->toggleLike($this->cv, $candidate);
    }

    public function testRecruiterTogglesLikeAndCounter(): void
    {
        $recruiter = new User('recruiter@example.com', [UserRole::ROLE_RECRUITER->value]);
        $this->em->persist($recruiter);
        $this->em->flush();

        self::assertTrue($this->service->toggleLike($this->cv, $recruiter));
        self::assertSame(1, $this->cv->getLikesCount());
        self::assertSame(1, $this->em->getRepository(CvLike::class)->count(['cv' => $this->cv]));

        self::assertFalse($this->service->toggleLike($this->cv, $recruiter));
        self::assertSame(0, $this->cv->getLikesCount());
        self::assertSame(0, $this->em->getRepository(CvLike::class)->count(['cv' => $this->cv]));
    }
}
