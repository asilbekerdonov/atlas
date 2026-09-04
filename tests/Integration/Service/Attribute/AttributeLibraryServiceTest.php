<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Attribute;

use App\DTO\AttributeDTO;
use App\Entity\Attribute;
use App\Entity\AttributeCategory;
use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\User;
use App\Enum\AttributeDataType;
use App\Enum\UserRole;
use App\Exception\AttributeInUseException;
use App\Exception\AttributeTypeChangeForbiddenException;
use App\Exception\CategoryNotFoundException;
use App\Service\Attribute\AttributeLibraryService;
use App\Tests\Integration\Service\AbstractServiceIntegrationTestCase;

final class AttributeLibraryServiceTest extends AbstractServiceIntegrationTestCase
{
    private AttributeLibraryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->service(AttributeLibraryService::class);
    }

    /** Service DTO with the resolved lookup category attached. */
    private function attributeDto(string $name, string $categoryName, AttributeDataType $dataType, ?string $description = null): AttributeDTO
    {
        $category = $this->category($categoryName);

        return new AttributeDTO(
            name: $name,
            categoryId: $category->getId(),
            categoryName: $category->getName(),
            dataType: $dataType,
            description: $description,
        );
    }

    public function testCreateAttribute(): void
    {
        $attribute = $this->service->createAttribute($this->attributeDto('English level', 'Soft Skills', AttributeDataType::STRING, 'CEFR level'));

        self::assertNotNull($attribute->getId());
        self::assertSame('English level', $attribute->getName());
        self::assertSame('Soft Skills', $attribute->getCategory()->getName());
    }

    public function testCreateAttributeWithUnknownCategoryThrows(): void
    {
        $this->expectException(CategoryNotFoundException::class);
        $this->service->createAttribute(new AttributeDTO(
            name: 'Mystery',
            categoryId: 999999,
            categoryName: 'No Such Category',
            dataType: AttributeDataType::STRING,
        ));
    }

    public function testChangingDataTypeWithExistingValuesIsForbidden(): void
    {
        $attribute = $this->service->createAttribute($this->attributeDto('Years of experience', 'Domain Knowledge', AttributeDataType::NUMERIC));

        $user = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $profile = new CandidateProfile($user, 'Jane', 'Doe');
        $value = new CandidateAttributeValue($profile, $attribute);
        $value->setValueNumeric('5');
        $this->em->persist($user);
        $this->em->persist($profile);
        $this->em->persist($value);
        $this->em->flush();

        $this->expectException(AttributeTypeChangeForbiddenException::class);
        $this->service->updateAttribute($attribute, $this->attributeDto('Years of experience', 'Domain Knowledge', AttributeDataType::STRING));
    }

    public function testChangingDataTypeWithoutValuesIsAllowed(): void
    {
        $attribute = $this->service->createAttribute($this->attributeDto('Company', 'Personal Information', AttributeDataType::STRING));

        $updated = $this->service->updateAttribute($attribute, $this->attributeDto('Company', 'Personal Information', AttributeDataType::TEXT));

        self::assertSame(AttributeDataType::TEXT, $updated->getDataType());
    }

    public function testDeleteAttributeInUseIsRejected(): void
    {
        $attribute = $this->service->createAttribute($this->attributeDto('Level', 'Soft Skills', AttributeDataType::STRING));

        $user = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $profile = new CandidateProfile($user, 'Jane', 'Doe');
        $value = new CandidateAttributeValue($profile, $attribute);
        $value->setValueString('Senior');
        $this->em->persist($user);
        $this->em->persist($profile);
        $this->em->persist($value);
        $this->em->flush();

        $this->expectException(AttributeInUseException::class);
        $this->service->deleteAttribute($attribute);
    }

    public function testDeleteUnusedAttribute(): void
    {
        $attribute = $this->service->createAttribute($this->attributeDto('Unused', 'Personal Information', AttributeDataType::STRING));
        $id = $attribute->getId();

        $this->service->deleteAttribute($attribute);

        self::assertNull($this->em->find(Attribute::class, $id));
    }

    public function testSearchPrependsRecentlyUsedAttributes(): void
    {
        $this->service->createAttribute($this->attributeDto('PHP', 'Domain Knowledge', AttributeDataType::STRING));
        $python = $this->service->createAttribute($this->attributeDto('Python', 'Domain Knowledge', AttributeDataType::STRING));
        $this->service->createAttribute($this->attributeDto('Golang', 'Domain Knowledge', AttributeDataType::STRING));

        $user = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $this->em->persist($user);
        $this->em->flush();

        $this->service->trackRecentUse($user, $python, 'PROFILE');

        $result = $this->service->searchAttributes('', null, $user, 10);
        self::assertSame($python->getId(), $result[0]->getId(), 'Most recent attribute must come first');

        $filtered = $this->service->searchAttributes('py', null, $user, 10);
        self::assertCount(1, $filtered);
        self::assertSame($python->getId(), $filtered[0]->getId());
    }

    public function testSearchFiltersByCategoryName(): void
    {
        $python = $this->service->createAttribute($this->attributeDto('Python', 'Domain Knowledge', AttributeDataType::STRING));
        $this->service->createAttribute($this->attributeDto('Remote', 'Personal Information', AttributeDataType::BOOLEAN));

        $user = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $this->em->persist($user);
        $this->em->flush();

        $result = $this->service->searchAttributes('', 'Domain Knowledge', $user, 10);

        self::assertCount(1, $result);
        self::assertSame($python->getId(), $result[0]->getId());
    }

    public function testTrackRecentUseUpserts(): void
    {
        $attribute = $this->service->createAttribute($this->attributeDto('Git', 'Domain Knowledge', AttributeDataType::STRING));
        $user = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $this->em->persist($user);
        $this->em->flush();

        $this->service->trackRecentUse($user, $attribute, 'PROFILE');
        $this->service->trackRecentUse($user, $attribute, 'POSITION');

        self::assertSame(1, $this->em->getRepository(\App\Entity\UserRecentAttribute::class)->count(['user' => $user]));
        $recent = $this->em->getRepository(\App\Entity\UserRecentAttribute::class)->findOneBy(['user' => $user]);
        self::assertSame('POSITION', $recent->getContext());
    }
}
