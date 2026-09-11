<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\CandidateProfile;
use App\Enum\UserRole;

final class ProfileLocationMapTest extends AbstractFunctionalTestCase
{
    public function testOwnerSeesMapButtonAndModal(): void
    {
        $user = $this->createUser('map-owner@example.com', UserRole::ROLE_CANDIDATE);
        $profile = new CandidateProfile($user, 'Map', 'Owner');
        $user->setProfile($profile);
        $this->em->persist($profile);
        $this->em->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('id="pick-location-btn"', $html);
        self::assertStringContainsString('id="locationMapModal"', $html);
        $m = null; preg_match('/data-location-key="([^"]*)"/', $html, $m);
        self::assertNotEmpty($m[1] ?? '', 'JS API key must be injected');
        self::assertStringContainsString('location-map-container', $html);
    }

    public function testNonOwnerAdminDoesNotSeeMapButton(): void
    {
        $owner = $this->createUser('map-owner2@example.com', UserRole::ROLE_CANDIDATE);
        $profile = new CandidateProfile($owner, 'Map', 'Owner');
        $owner->setProfile($profile);
        $this->em->persist($profile);
        $this->em->flush();

        // Admins may view any profile, but non-owner profiles remain read-only.
        $admin = $this->createUser('map-admin@example.com', UserRole::ROLE_ADMIN);
        $this->client->loginUser($admin);
        $this->client->request('GET', '/profile/' . $profile->getId());
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('id="pick-location-btn"', $html);
        self::assertStringNotContainsString('id="locationMapModal"', $html);
    }
}
