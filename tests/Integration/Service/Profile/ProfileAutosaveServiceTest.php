<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Profile;

use App\DTO\ProfileAttributeValueDTO;
use App\DTO\ProfileAutosaveDTO;
use App\Entity\Attribute;
use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\PositionTemplateAttribute;
use App\Entity\User;

use App\Enum\AttributeDataType;
use App\Enum\CvStatus;
use App\Enum\UserRole;
use App\Exception\AttributeValueNotFoundException;
use App\Exception\OptimisticLockConflictException;
use App\Service\Profile\ProfileAutosaveService;
use App\Tests\Integration\Service\AbstractServiceIntegrationTestCase;

final class ProfileAutosaveServiceTest extends AbstractServiceIntegrationTestCase
{
    private ProfileAutosaveService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->service(ProfileAutosaveService::class);
    }

    public function testAutosaveAppliesChangesAndReturnsNewVersion(): void
    {
        $user = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $this->em->persist($user);
        $this->em->persist(new CandidateProfile($user, 'Jane', 'Doe'));

        $years = new Attribute('Years', $this->category('Domain Knowledge'), AttributeDataType::NUMERIC);
        $this->em->persist($years);
        $this->em->flush();

        $result = $this->service->autosaveProfile($user, new ProfileAutosaveDTO(
            firstName: 'Janet',
            location: 'Berlin',
            expectedVersion: 1,
            attributeValues: [
                new ProfileAttributeValueDTO($years->getId(), '7.5'),
            ],
        ));

        self::assertSame(2, $result->newVersion);
        self::assertSame('Janet', $result->profile->getFirstName());
        self::assertSame('Berlin', $result->profile->getLocation());

        $value = $result->profile->getAttributeValues()->first();
        self::assertSame('7.5', $value->getValueNumeric());
    }

    public function testAutosaveWithStaleVersionThrowsConflict(): void
    {
        $user = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $this->em->persist($user);
        $this->em->persist(new CandidateProfile($user, 'Jane', 'Doe'));
        $this->em->flush();

        $this->expectException(OptimisticLockConflictException::class);
        $this->service->autosaveProfile($user, new ProfileAutosaveDTO(firstName: 'Janet', expectedVersion: 99));
    }

    public function testAutosaveCreatesMissingProfile(): void
    {
        $user = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $this->em->persist($user);
        $this->em->flush();

        $result = $this->service->autosaveProfile($user, new ProfileAutosaveDTO(firstName: 'Jane', lastName: 'Doe'));

        self::assertNotNull($result->profile->getId());
        self::assertSame('Jane', $result->profile->getFirstName());
    }

    public function testDeleteAttributeValueRemovesRowAndUnpublishesCv(): void
    {
        $user = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $profile = new CandidateProfile($user, 'Jane', 'Doe');
        $this->em->persist($user);
        $this->em->persist($profile);

        $python = new Attribute('Python', $this->category('Domain Knowledge'), AttributeDataType::BOOLEAN);
        $this->em->persist($python);
        $value = new CandidateAttributeValue($profile, $python);
        $value->setValueBoolean(true);
        $profile->getAttributeValues()->add($value);
        $this->em->persist($value);

        // Published CV whose position template requires the same attribute.
        $position = new Position('Python role', 'Desc');
        $position->getTemplateAttributes()->add(new PositionTemplateAttribute($position, $python, true, 1));
        $this->em->persist($position);
        $cv = new Cv($user, $position);
        $cv->publish();
        $this->em->persist($cv);
        $this->em->flush();

        $valueId = $value->getId();
        $result = $this->service->deleteAttributeValue($user, $valueId);

        self::assertNull($this->em->find(CandidateAttributeValue::class, $valueId), 'value row must be deleted');
        self::assertSame(CvStatus::DRAFT, $cv->getStatus(), 'published CV using the attribute must revert to DRAFT');
        self::assertSame([['cvId' => $cv->getId(), 'positionTitle' => 'Python role']], $result->unpublishedCvs);
        self::assertGreaterThan(1, $result->newVersion, 'profile version must bump so autosave conflicts surface');
    }

    public function testDeleteAttributeValueLeavesCvPublishedWhenAttributeNotUsed(): void
    {
        $user = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $profile = new CandidateProfile($user, 'Jane', 'Doe');
        $this->em->persist($user);
        $this->em->persist($profile);

        $python = new Attribute('Python', $this->category('Domain Knowledge'), AttributeDataType::BOOLEAN);
        $english = new Attribute('English', $this->category('Soft Skills'), AttributeDataType::STRING);
        $this->em->persist($python);
        $this->em->persist($english);

        $value = new CandidateAttributeValue($profile, $python);
        $value->setValueBoolean(true);
        $profile->getAttributeValues()->add($value);
        $this->em->persist($value);

        // Published CV uses ENGLISH, not Python — must stay published.
        $position = new Position('English role', 'Desc');
        $position->getTemplateAttributes()->add(new PositionTemplateAttribute($position, $english, true, 1));
        $this->em->persist($position);
        $cv = new Cv($user, $position);
        $cv->publish();
        $this->em->persist($cv);
        $this->em->flush();

        $result = $this->service->deleteAttributeValue($user, $value->getId());

        self::assertSame(CvStatus::PUBLISHED, $cv->getStatus(), 'CV must stay published when it does not use the removed attribute');
        self::assertSame([], $result->unpublishedCvs);
    }

    public function testDeleteAttributeValueFromAnotherUserThrows(): void
    {
        $owner = new User('owner@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $profile = new CandidateProfile($owner, 'Jane', 'Doe');
        $this->em->persist($owner);
        $this->em->persist($profile);

        $python = new Attribute('Python', $this->category('Domain Knowledge'), AttributeDataType::BOOLEAN);
        $this->em->persist($python);
        $value = new CandidateAttributeValue($profile, $python);
        $value->setValueBoolean(true);
        $profile->getAttributeValues()->add($value);
        $this->em->persist($value);
        $this->em->flush();

        $intruder = new User('intruder@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $this->em->persist($intruder);
        $this->em->flush();

        $this->expectException(AttributeValueNotFoundException::class);
        $this->service->deleteAttributeValue($intruder, $value->getId());
    }
}
