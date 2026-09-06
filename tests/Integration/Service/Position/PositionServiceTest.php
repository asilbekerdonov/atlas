<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Position;

use App\DTO\PositionAccessRuleDTO;
use App\DTO\PositionDTO;
use App\DTO\PositionTemplateAttributeDTO;
use App\Entity\Attribute;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\PositionTemplateAttribute;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\AccessRuleOperator;

use App\Enum\AttributeDataType;
use App\Enum\UserRole;
use App\Exception\OptimisticLockConflictException;
use App\Service\Position\PositionService;
use App\Tests\Integration\Service\AbstractServiceIntegrationTestCase;
use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;

final class PositionServiceTest extends AbstractServiceIntegrationTestCase
{
    private PositionService $service;
    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->service(PositionService::class);
        $this->author = new User('recruiter@example.com', [UserRole::ROLE_RECRUITER->value]);
        $this->em->persist($this->author);
        $this->em->flush();
    }

    public function testCreatePositionWithTemplateRulesAndTags(): void
    {
        $gpa = $this->persistAttribute('GPA', AttributeDataType::NUMERIC);
        $english = $this->persistAttribute('English', AttributeDataType::STRING);

        $position = $this->service->createPosition(new PositionDTO(
            title: 'Senior Backend',
            shortDescription: 'PHP + PostgreSQL',
            companyName: 'Acme',
            isPublic: true,
            maxProjects: 3,
            templateAttributes: [
                new PositionTemplateAttributeDTO($gpa->getId(), isRequired: true, sortOrder: 1),
                new PositionTemplateAttributeDTO($english->getId(), isRequired: false, sortOrder: 2),
            ],
            accessRules: [
                new PositionAccessRuleDTO($gpa->getId(), AccessRuleOperator::GREATER_THAN_OR_EQUAL, '3.5'),
            ],
            tags: ['PHP', 'PostgreSQL'],
        ), $this->author);

        self::assertNotNull($position->getId());
        self::assertCount(2, $position->getTemplateAttributes());
        self::assertCount(1, $position->getAccessRules());
        self::assertCount(2, $position->getTags());
        self::assertTrue($position->getTemplateAttributes()->first()->isRequired());
    }

    public function testDuplicatePositionCreatesDeepCopy(): void
    {
        $gpa = $this->persistAttribute('GPA', AttributeDataType::NUMERIC);
        $php = new Tag('PHP');
        $this->em->persist($php);

        $source = new Position('Original', 'Desc');
        $source->setMaxProjects(2);
        $source->addTag($php);
        $source->getTemplateAttributes()->add(new PositionTemplateAttribute($source, $gpa, true, 1));
        $source->getAccessRules()->add(new PositionAccessRule($source, $gpa, AccessRuleOperator::GREATER_THAN_OR_EQUAL, '3'));
        $this->em->persist($source);
        $this->em->flush();

        $copy = $this->service->duplicatePosition($source, $this->author);

        self::assertNotSame($source->getId(), $copy->getId());
        self::assertSame('Copy of Original', $copy->getTitle());
        self::assertSame(2, $copy->getMaxProjects());
        self::assertCount(1, $copy->getTemplateAttributes());
        self::assertCount(1, $copy->getAccessRules());
        self::assertCount(1, $copy->getTags());

        // Rows must be fresh (deep copy), tags shared.
        $copiedTemplate = $copy->getTemplateAttributes()->first();
        self::assertNotSame($source->getTemplateAttributes()->first()->getId(), $copiedTemplate->getId());
        self::assertSame($gpa->getId(), $copiedTemplate->getAttribute()->getId());
        self::assertSame($php->getId(), $copy->getTags()->first()->getId());
    }

    public function testUpdatePositionWithStaleVersionThrowsConflict(): void
    {
        $position = $this->service->createPosition(new PositionDTO('Title', 'Desc'), $this->author);

        $this->expectException(OptimisticLockConflictException::class);
        $this->service->updatePosition($position, new PositionDTO('New title', 'New desc'), 99);
    }

    public function testUpdatePositionAppliesChangesAndBumpsVersion(): void
    {
        $gpa = $this->persistAttribute('GPA', AttributeDataType::NUMERIC);
        $position = $this->service->createPosition(new PositionDTO(
            'Old title',
            'Old desc',
            templateAttributes: [new PositionTemplateAttributeDTO($gpa->getId(), isRequired: true)],
        ), $this->author);
        self::assertSame(1, $position->getVersion());

        $updated = $this->service->updatePosition(
            $position,
            new PositionDTO(
                'New title',
                'New desc',
                maxProjects: 5,
                templateAttributes: [],
                tags: ['Go'],
            ),
            expectedVersion: 1,
        );

        self::assertSame('New title', $updated->getTitle());
        self::assertSame(5, $updated->getMaxProjects());
        self::assertSame(2, $updated->getVersion());
        self::assertCount(0, $updated->getTemplateAttributes(), 'old template rows must be removed');
        self::assertCount(1, $updated->getTags());
    }

    public function testSoftDeletePosition(): void
    {
        $position = $this->service->createPosition(new PositionDTO('Title', 'Desc'), $this->author);
        self::assertNull($position->getDeletedAt());

        $this->service->softDeletePosition($position);

        self::assertNotNull($position->getDeletedAt());
        self::assertTrue($position->isDeleted());
        self::assertLessThanOrEqual(new DateTimeImmutable(), $position->getDeletedAt());
    }

    public function testSoftDeletePositionInvalidatesHomeTagCloudCache(): void
    {
        // The home page caches the tag cloud for 120 s. Deleting a position
        // must drop that cache, otherwise the removed tags stay visible
        // until expiry.
        $cache = self::getContainer()->get(CacheItemPoolInterface::class);
        $cache->deleteItem('home.tag_cloud.v1');

        $position = $this->service->createPosition(new PositionDTO('Cached role', 'Desc'), $this->author);
        $cache->get('home.tag_cloud.v1', fn () => ['Python' => 5]);
        self::assertTrue($cache->hasItem('home.tag_cloud.v1'), 'precondition: cache is populated');

        $this->service->softDeletePosition($position);

        self::assertFalse($cache->hasItem('home.tag_cloud.v1'), 'deleting a position must invalidate the tag cloud cache');
    }

    private function persistAttribute(string $name, AttributeDataType $dataType): Attribute
    {
        $attribute = new Attribute($name, $this->category('Domain Knowledge'), $dataType);
        $this->em->persist($attribute);
        $this->em->flush();

        return $attribute;
    }
}
