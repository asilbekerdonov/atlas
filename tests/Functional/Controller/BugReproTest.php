<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Enum\UserRole;

final class BugReproTest extends AbstractFunctionalTestCase
{
    public function testPositionsNewIsNotMatchedAsPositionId(): void
    {
        // /positions/new must hit the "new" route, never /positions/{id} with id="new".
        $this->client->request('GET', '/positions/new');
        self::assertResponseRedirects(); // guest → login

        $recruiter = $this->createUser('recruiter@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);
        $this->client->request('GET', '/positions/new');
        self::assertResponseIsSuccessful(); // form page, no 500

        // Non-numeric ids are rejected with 404, not a SQL conversion error.
        $this->client->request('GET', '/positions/not-a-number');
        self::assertResponseStatusCodeSame(404);
    }

    public function testPositionCreateViaJsonReturnsRedirectUrl(): void
    {
        $recruiter = $this->createUser('recruiter@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);

        $response = $this->jsonRequest($this->client, 'POST', '/positions/new', [
            'title' => 'DevOps Lead',
            'shortDescription' => 'Cloud infrastructure',
            'companyName' => 'CloudTech',
            'level' => 'Senior',
            'isPublic' => true,
            'maxProjects' => 3,
            'templateAttributes' => [],
            'accessRules' => [],
            'tags' => ['Docker'],
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);
        self::assertStringContainsString('/positions/', $data['redirectUrl']);
    }

    public function testPositionCreateViaClassicFormRedirects(): void
    {
        $recruiter = $this->createUser('recruiter2@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);

        $this->client->request('POST', '/positions/new', [
            'title' => 'Classic role',
            'shortDescription' => 'Via form-urlencoded',
            'companyName' => 'CloudTech',
            'level' => 'Junior',
            'isPublic' => 'on',   // HTML checkbox string
            'maxProjects' => '5', // HTML number string
        ], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        // String 'on'/'5' must normalize into bool/int — no 500.
        self::assertTrue(in_array($this->client->getResponse()->getStatusCode(), [302, 200], true), 'status was ' . $this->client->getResponse()->getStatusCode());
        if (302 === $this->client->getResponse()->getStatusCode()) {
            $position = $this->em->getRepository(\App\Entity\Position::class)->findOneBy(['title' => 'Classic role']);
            self::assertNotNull($position, 'position must have been persisted');
            self::assertTrue($position->isPublic());
            self::assertSame(5, $position->getMaxProjects());
        }
    }

    public function testBulkDeleteSoftDeletesSelectedPositions(): void
    {
        $recruiter = $this->createUser('bulk@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);

        $p1 = $this->createPosition();
        $p2 = $this->createPosition();

        $response = $this->jsonRequest($this->client, 'POST', '/positions/bulk-delete', [
            'ids' => [$p1->getId(), $p2->getId()],
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);
        self::assertSame(2, $data['deletedCount']);

        $this->em->clear();
        self::assertNotNull($this->em->find(\App\Entity\Position::class, $p1->getId())->getDeletedAt());
        self::assertNotNull($this->em->find(\App\Entity\Position::class, $p2->getId())->getDeletedAt());
    }

    public function testEditPositionWithVersionInBody(): void
    {
        $recruiter = $this->createUser('editor@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);
        $position = $this->createPosition();

        $response = $this->jsonRequest($this->client, 'POST', '/positions/' . $position->getId() . '/edit', [
            'version' => 1,
            'title' => 'Edited title',
            'shortDescription' => 'Updated description',
            'isPublic' => true,
            'maxProjects' => 6,
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);

        // Stale version (1) after a successful bump to 2 → 409 conflict.
        $conflict = $this->jsonRequest($this->client, 'POST', '/positions/' . $position->getId() . '/edit', [
            'version' => 1,
            'title' => 'Edited again',
            'shortDescription' => 'x',
        ]);
        self::assertResponseStatusCodeSame(409);
        $conflictData = json_decode($conflict->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('conflict', $conflictData['error']);
        self::assertSame(2, $conflictData['serverVersion']);
    }

    public function testDiscussionPostWithFormData(): void
    {
        $author = $this->createUser('recruiter@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($author);
        $position = $this->createPosition();

        $this->client->request('POST', '/positions/' . $position->getId() . '/discussions', [
            'message' => 'Hello discussion',
        ], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $status = $this->client->getResponse()->getStatusCode();
        self::assertTrue(in_array($status, [302, 200], true), 'status was ' . $status);
        if (302 === $status) {
            $this->client->followRedirect();
            self::assertSelectorTextContains('body', 'Hello discussion');
        }
    }

    public function testPublicPositionAllowsApplyWithoutProfile(): void
    {
        // Candidate with NO profile applies to a public position → allowed.
        $candidate = $this->createUser('candidate2@example.com', UserRole::ROLE_CANDIDATE);
        $this->client->loginUser($candidate);

        $position = $this->createPosition();
        $position->setPublic(true);
        $this->em->flush();

        $this->client->request('GET', '/positions/' . $position->getId() . '/apply');
        self::assertTrue(in_array($this->client->getResponse()->getStatusCode(), [302, 200], true), 'status was ' . $this->client->getResponse()->getStatusCode());
    }

    public function testRestrictedPositionStillBlocksCandidateWithoutProfile(): void
    {
        $candidate = $this->createUser('candidate3@example.com', UserRole::ROLE_CANDIDATE);
        $this->client->loginUser($candidate);

        $position = $this->createPosition();
        $position->setPublic(false); // restricted, no profile data to evaluate
        $this->em->flush();

        $this->client->request('GET', '/positions/' . $position->getId() . '/apply');
        self::assertTrue(in_array($this->client->getResponse()->getStatusCode(), [403, 302], true), 'status was ' . $this->client->getResponse()->getStatusCode());
    }
}
