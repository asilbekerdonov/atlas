<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CandidateProfile;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\UserRole;

/**
 * Position page: the CV-icon toolbar button shows a small red badge with the
 * number of published CVs the recruiter has not opened yet. Opening the CV
 * list marks them viewed, so the badge disappears on the next page view.
 */
final class PositionCvBadgeTest extends AbstractFunctionalTestCase
{
    public function testBadgeShowsUnviewedCountAndClearsAfterVisit(): void
    {
        $recruiter = $this->createUser('badge@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);

        $position = new Position('Badge Position', 'desc');
        $this->em->persist($position);
        $this->em->flush();

        // Two published CVs for this position, none viewed yet.
        $this->publishedCv('a@example.com', $position);
        $this->publishedCv('b@example.com', $position);
        $this->em->flush();

        $this->em->clear();

        // Badge must show "2" on the CV icon.
        $this->client->request('GET', '/positions/' . $position->getId());
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('position-cv-badge', $html);
        self::assertStringContainsString('>2</span>', $html, 'badge must show 2 unviewed CVs');

        // Visiting the CV list marks them as viewed by the recruiter.
        $this->client->request('GET', '/positions/' . $position->getId() . '/cvs');
        self::assertResponseIsSuccessful();

        // Back on the position page the badge is gone (count = 0).
        $this->client->request('GET', '/positions/' . $position->getId());
        self::assertResponseIsSuccessful();
        $htmlAfter = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('position-cv-badge', $htmlAfter, 'badge must disappear once all CVs are viewed');
    }

    public function testCandidateDoesNotSeeCvActionOrBadge(): void
    {
        $candidate = $this->createUser('cand-badge@example.com', UserRole::ROLE_CANDIDATE);
        $this->client->loginUser($candidate);

        $position = new Position('Candidate View', 'desc');
        $this->em->persist($position);
        $this->em->flush();
        $this->publishedCv('other@example.com', $position);
        $this->em->flush();

        $this->client->request('GET', '/positions/' . $position->getId());
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();

        self::assertStringNotContainsString('position-cv-badge', $html, 'candidate must not see the badge');
        self::assertStringNotContainsString('position-page-actions', $html, 'candidate must not see the recruiter toolbar');
    }

    private function publishedCv(string $email, Position $position): void
    {
        $user = new User($email, [UserRole::ROLE_CANDIDATE->value]);
        $profile = new CandidateProfile($user, 'Jane', 'Doe');
        $this->em->persist($user);
        $this->em->persist($profile);

        $cv = new Cv($user, $position);
        $cv->publish();
        $this->em->persist($cv);
    }
}
