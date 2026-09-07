<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Position;
use App\Enum\Level;
use App\Enum\UserRole;

/**
 * Position Level is a real enum: creating a position with level=SENIOR must
 * persist it and the value must render on the show page (localised, never
 * raw). Regression guard for the form → DTO → service → entity chain.
 */
final class PositionLevelTest extends AbstractFunctionalTestCase
{
    public function testCreatePositionWithLevelPersistsAndRenders(): void
    {
        $recruiter = $this->createUser('level.recruiter@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);

        $response = $this->jsonRequest($this->client, 'POST', '/positions/new', [
            'title' => 'Senior Platform Engineer',
            'shortDescription' => 'Kubernetes + Go',
            'level' => 'SENIOR',
            'isPublic' => true,
            'maxProjects' => 4,
            'templateAttributes' => [],
            'accessRules' => [],
            'tags' => ['Go'],
        ]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        // The entity really stores the enum.
        $this->em->clear();
        $position = $this->em->getRepository(Position::class)->find($data['id']);
        self::assertInstanceOf(Position::class, $position);
        self::assertSame(Level::SENIOR, $position->getLevel());

        // The show page renders the translated label (default locale: en).
        $this->client->request('GET', '/positions/' . $position->getId());
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Senior', $html);
        self::assertStringNotContainsString('SENIOR</span>', $html, 'raw enum value must not leak into the page');
    }

    public function testCreatePositionWithLegacyTitleCaseLevelStillMaps(): void
    {
        $recruiter = $this->createUser('level.legacy@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);

        // Old clients sent display labels ('Senior'); the mapper must accept them.
        $response = $this->jsonRequest($this->client, 'POST', '/positions/new', [
            'title' => 'Legacy Level Role',
            'shortDescription' => 'desc',
            'level' => 'Senior',
            'isPublic' => false,
            'maxProjects' => 4,
            'templateAttributes' => [],
            'accessRules' => [],
            'tags' => [],
        ]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->em->clear();
        $position = $this->em->getRepository(Position::class)->find($data['id']);
        self::assertSame(Level::SENIOR, $position->getLevel());
    }

    public function testEditFormPreselectsStoredLevel(): void
    {
        $recruiter = $this->createUser('level.edit@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);

        $position = new Position('Editable Role', 'desc');
        $position->setLevel(Level::C_LEVEL);
        $this->em->persist($position);
        $this->em->flush();

        $this->client->request('GET', '/positions/' . $position->getId() . '/edit');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();

        self::assertSelectorExists('select[name="level"] option[value="C_LEVEL"][selected]', 'edit form must preselect the stored level');
        self::assertSelectorExists('select[name="level"] option[value="SENIOR"]', 'all five levels must be offered');
    }
}
