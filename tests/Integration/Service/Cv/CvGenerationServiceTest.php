<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Cv;

use App\Entity\Attribute;
use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\PositionTemplateAttribute;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\AccessRuleOperator;

use App\Enum\AttributeDataType;
use App\Enum\CvStatus;
use App\Enum\UserRole;
use App\Exception\CvIncompleteException;
use App\Exception\OptimisticLockConflictException;
use App\Exception\PositionAccessDeniedException;
use App\Service\Cv\CvGenerationService;
use App\Tests\Integration\Service\AbstractServiceIntegrationTestCase;
use DateTimeImmutable;

final class CvGenerationServiceTest extends AbstractServiceIntegrationTestCase
{
    private CvGenerationService $service;
    private User $candidate;
    private Attribute $years;
    private Attribute $english;
    private Position $position;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->service(CvGenerationService::class);

        $this->candidate = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $profile = new CandidateProfile($this->candidate, 'Jane', 'Doe');
        $this->em->persist($this->candidate);
        $this->em->persist($profile);

        $this->years = $this->attribute('Years of experience', AttributeDataType::NUMERIC);
        $this->english = $this->attribute('English', AttributeDataType::STRING);

        $value = new CandidateAttributeValue($profile, $this->years);
        $value->setValueNumeric('5');
        $profile->getAttributeValues()->add($value);
        $this->em->persist($value);

        $this->position = new Position('Senior Backend', 'PHP + PostgreSQL');
        $this->position->setPublic(false);
        $this->position->getAccessRules()->add(new PositionAccessRule($this->position, $this->years, AccessRuleOperator::GREATER_THAN_OR_EQUAL, '3'));
        $this->position->getTemplateAttributes()->add(new PositionTemplateAttribute($this->position, $this->years, true, 1));
        $this->position->getTemplateAttributes()->add(new PositionTemplateAttribute($this->position, $this->english, true, 2));
        $this->em->persist($this->position);

        $this->em->flush();
    }

    public function testGetOrCreateCvDeniedWhenRulesNotSatisfied(): void
    {
        $this->position->getAccessRules()->clear();
        $this->position->getAccessRules()->add(new PositionAccessRule($this->position, $this->years, AccessRuleOperator::GREATER_THAN, '10'));
        $this->em->flush();

        $this->expectException(PositionAccessDeniedException::class);
        $this->service->getOrCreateCv($this->candidate, $this->position);
    }

    public function testGetOrCreateCvCreatesDraftOnce(): void
    {
        $cv = $this->service->getOrCreateCv($this->candidate, $this->position);
        self::assertSame(CvStatus::DRAFT, $cv->getStatus());
        self::assertNotNull($cv->getId());

        $again = $this->service->getOrCreateCv($this->candidate, $this->position);
        self::assertSame($cv->getId(), $again->getId(), 'UNIQUE(candidate, position) must be respected');
    }

    public function testAssembleCvViewWithTagFilterAndMaxProjects(): void
    {
        $php = new Tag('PHP');
        $python = new Tag('Python');
        $this->em->persist($php);
        $this->em->persist($python);
        $this->position->addTag($php);

        $profile = $this->candidate->getProfile();
        $project1 = new Project($profile, 'Atlas', new DateTimeImmutable('2024-06-01'), 'Uses PHP');
        $project1->addTag($php);
        $project2 = new Project($profile, 'Legacy', new DateTimeImmutable('2024-01-01'), 'Also PHP');
        $project2->addTag($php);
        $project3 = new Project($profile, 'Data pipeline', new DateTimeImmutable('2024-03-01'), 'Python only');
        $project3->addTag($python);
        $this->em->persist($project1);
        $this->em->persist($project2);
        $this->em->persist($project3);
        $this->position->setMaxProjects(1);
        $this->em->flush();

        $cv = $this->service->getOrCreateCv($this->candidate, $this->position);
        $view = $this->service->assembleCvView($cv);

        self::assertSame('Jane', $view->firstName);
        self::assertCount(2, $view->attributes);

        $byId = [];
        foreach ($view->attributes as $attributeView) {
            $byId[$attributeView->attributeId] = $attributeView;
        }
        self::assertFalse($byId[$this->years->getId()]->isEmpty, 'years filled in');
        self::assertTrue($byId[$this->english->getId()]->isEmpty, 'english missing');

        // Only the newest project sharing a tag with the position, capped by maxProjects=1.
        self::assertCount(1, $view->projects);
        self::assertSame('Atlas', $view->projects[0]->name);
    }

    public function testUpdateCvAttributeInPlaceMutatesMasterProfile(): void
    {
        $created = $this->service->updateCvAttributeInPlace($this->candidate, $this->english->getId(), 'C1', null);
        self::assertSame('C1', $created->getValueString());

        $this->em->clear();

        // Re-read the master profile: the value must live there, not on a CV copy.
        $freshCandidate = $this->em->find(User::class, $this->candidate->getId());
        $freshValue = null;
        foreach ($freshCandidate->getProfile()->getAttributeValues() as $value) {
            if ($value->getAttribute()->getId() === $this->english->getId()) {
                $freshValue = $value;
            }
        }
        self::assertNotNull($freshValue);
        self::assertSame('C1', $freshValue->getValueString());

        $updated = $this->service->updateCvAttributeInPlace($freshCandidate, $this->english->getId(), 'B2', 1);
        self::assertSame('B2', $updated->getValueString());
        self::assertSame(2, $updated->getVersion());
    }

    public function testUpdateCvAttributeWithStaleVersionThrowsConflict(): void
    {
        $this->service->updateCvAttributeInPlace($this->candidate, $this->english->getId(), 'C1', null);

        $this->expectException(OptimisticLockConflictException::class);
        $this->service->updateCvAttributeInPlace($this->candidate, $this->english->getId(), 'B2', 99);
    }

    public function testPublishCvFailsWhenRequiredAttributeMissing(): void
    {
        $cv = $this->service->getOrCreateCv($this->candidate, $this->position);

        try {
            $this->service->publishCv($cv);
            self::fail('Expected CvIncompleteException');
        } catch (CvIncompleteException $e) {
            self::assertCount(1, $e->getMissingAttributes());
            self::assertSame($this->english->getId(), $e->getMissingAttributes()[0]->getId());
        }
        self::assertSame(CvStatus::DRAFT, $cv->getStatus(), 'status must not change on failure');
    }

    public function testPublishCvSucceedsWhenAllRequiredFilled(): void
    {
        $this->service->updateCvAttributeInPlace($this->candidate, $this->english->getId(), 'C1', null);
        $cv = $this->service->getOrCreateCv($this->candidate, $this->position);

        $this->service->publishCv($cv);

        self::assertSame(CvStatus::PUBLISHED, $cv->getStatus());
    }

    private function attribute(string $name, AttributeDataType $dataType): Attribute
    {
        $attribute = new Attribute($name, $this->category('Domain Knowledge'), $dataType);
        $this->em->persist($attribute);

        return $attribute;
    }
}
