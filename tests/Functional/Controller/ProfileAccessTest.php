<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Attribute;
use App\Entity\CandidateAttributeValue;
use App\Enum\AttributeDataType;
use App\Enum\UserRole;

final class ProfileAccessTest extends AbstractFunctionalTestCase
{
    public function testCandidateCannotViewAnotherCandidatesProfile(): void
    {
        $owner = $this->createUser('owner@example.com', UserRole::ROLE_CANDIDATE);
        $profile = $this->createProfile($owner);
        $other = $this->createUser('other@example.com', UserRole::ROLE_CANDIDATE);

        $this->client->loginUser($other);
        $this->client->request('GET', '/profile/' . $profile->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testCandidateCanViewOwnProfile(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $this->createProfile($candidate);

        $this->client->loginUser($candidate);
        $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'My profile');
    }

    public function testRecruiterCannotAccessProfilesButCanEditPositions(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $profile = $this->createProfile($candidate);
        $recruiter = $this->createUser('recruiter@example.com', UserRole::ROLE_RECRUITER);
        $position = $this->createPosition();

        $this->client->loginUser($recruiter);

        // Profiles are off-limits for recruiters.
        $this->client->request('GET', '/profile/' . $profile->getId());
        self::assertResponseStatusCodeSame(403);

        // Any position is editable by a recruiter.
        $this->client->request('GET', '/positions/' . $position->getId() . '/edit');
        self::assertResponseIsSuccessful();
    }

    public function testAdminCanAccessAnyProfile(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $profile = $this->createProfile($candidate);
        $admin = $this->createUser('admin@example.com', UserRole::ROLE_ADMIN);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/profile/' . $profile->getId());

        self::assertResponseIsSuccessful();
    }

    public function testAutosaveSuccessReturnsNewVersion(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $this->createProfile($candidate);

        $this->client->loginUser($candidate);
        $response = $this->jsonRequest($this->client, 'PATCH', '/api/profile/autosave', [
            'firstName' => 'Janet',
            'location' => 'Berlin',
            'expectedVersion' => 1,
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);
        self::assertSame(2, $data['newVersion']);
    }

    public function testAutosaveConflictReturns409WithCurrentData(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $this->createProfile($candidate);

        $this->client->loginUser($candidate);
        $response = $this->jsonRequest($this->client, 'PATCH', '/api/profile/autosave', [
            'firstName' => 'Janet',
            'expectedVersion' => 99,
        ]);

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('conflict', $data['error']);
        self::assertSame(1, $data['serverVersion']);
        self::assertArrayHasKey('currentData', $data);
    }

    public function testDeleteAttributeValueReturnsJsonAndRemovesRow(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $profile = $this->createProfile($candidate);
        $attr = new Attribute('Python', $this->category('Domain Knowledge'), AttributeDataType::BOOLEAN);
        $this->em->persist($attr);
        $value = new CandidateAttributeValue($profile, $attr);
        $value->setValueBoolean(true);
        $profile->getAttributeValues()->add($value);
        $this->em->persist($value);
        $this->em->flush();

        $valueId = $value->getId();
        $this->client->loginUser($candidate);
        $response = $this->jsonRequest($this->client, 'DELETE', '/api/profile/attribute-value/' . $valueId, []);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);
        self::assertSame([], $data['unpublishedCvs']);

        $this->em->clear();
        self::assertNull($this->em->find(CandidateAttributeValue::class, $valueId), 'value row must be gone');
        // The shared attribute itself must survive (only the candidate value is deleted).
        self::assertNotNull($this->em->find(Attribute::class, $attr->getId()));
    }

    public function testDeleteOtherCandidatesAttributeValueReturnsJson404(): void
    {
        $owner = $this->createUser('owner@example.com', UserRole::ROLE_CANDIDATE);
        $profile = $this->createProfile($owner);
        $attr = new Attribute('Python', $this->category('Domain Knowledge'), AttributeDataType::BOOLEAN);
        $this->em->persist($attr);
        $value = new CandidateAttributeValue($profile, $attr);
        $value->setValueBoolean(true);
        $profile->getAttributeValues()->add($value);
        $this->em->persist($value);
        $this->em->flush();

        $intruder = $this->createUser('intruder@example.com', UserRole::ROLE_CANDIDATE);
        $this->client->loginUser($intruder);

        $response = $this->jsonRequest($this->client, 'DELETE', '/api/profile/attribute-value/' . $value->getId(), []);

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($data['success']);
        self::assertSame('not_found', $data['error']);
    }
}
