<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Project;

use App\DTO\Request\ProjectCreateRequestDTO;
use App\DTO\Request\ProjectUpdateRequestDTO;
use App\Entity\CandidateProfile;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\UserRole;
use App\Exception\OptimisticLockConflictException;
use App\Service\Project\ProjectService;
use App\Tests\Integration\Service\AbstractServiceIntegrationTestCase;

final class ProjectServiceTest extends AbstractServiceIntegrationTestCase
{
    private ProjectService $service;
    private User $candidate;
    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->service(ProjectService::class);

        $this->candidate = new User('candidate@example.com', [UserRole::ROLE_CANDIDATE->value]);
        $this->profile = new CandidateProfile($this->candidate, 'Jane', 'Doe');
        $this->em->persist($this->candidate);
        $this->em->persist($this->profile);
        $this->em->flush();
    }

    public function testCreateProjectPersistsAndReturnsView(): void
    {
        $view = $this->service->createProject($this->profile, new ProjectCreateRequestDTO(
            name: 'ETL Pipeline',
            startDate: '2024-01-15',
            endDate: '2025-03-01',
            descriptionMd: 'Warehouse pipeline',
            tags: ['Python', 'SQL'],
        ));

        self::assertGreaterThan(0, $view->id);
        self::assertSame('ETL Pipeline', $view->name);
        self::assertSame('2024-01-15', $view->startDate);
        self::assertSame('2025-03-01', $view->endDate);
        self::assertSame(['Python', 'SQL'], $view->tags);
        self::assertSame(1, $view->version);

        $project = $this->em->find(Project::class, $view->id);
        self::assertNotNull($project);
        self::assertCount(2, $project->getTags());
    }

    public function testUpdateProjectBumpsVersion(): void
    {
        $created = $this->service->createProject($this->profile, new ProjectCreateRequestDTO(
            name: 'Old name',
            startDate: '2024-01-01',
            descriptionMd: 'Old',
        ));
        $project = $this->em->find(Project::class, $created->id);

        $updated = $this->service->updateProject($project, new ProjectUpdateRequestDTO(
            name: 'New name',
            startDate: '2024-02-01',
            endDate: '2024-03-01',
            descriptionMd: 'New description',
            tags: ['Go'],
            expectedVersion: $created->version,
        ));

        self::assertSame('New name', $updated->name);
        self::assertSame(['Go'], $updated->tags);
        self::assertSame($created->version + 1, $updated->version);
    }

    public function testUpdateProjectWithStaleVersionThrowsConflict(): void
    {
        $created = $this->service->createProject($this->profile, new ProjectCreateRequestDTO(
            name: 'Stale project',
            startDate: '2024-01-01',
            descriptionMd: 'x',
        ));
        $project = $this->em->find(Project::class, $created->id);

        $this->expectException(OptimisticLockConflictException::class);
        $this->service->updateProject($project, new ProjectUpdateRequestDTO(
            name: 'New',
            startDate: '2024-01-01',
            descriptionMd: 'y',
            expectedVersion: 99, // stale
        ));
    }

    public function testGetOrCreateTagReusesExistingCaseInsensitively(): void
    {
        // First project creates the tag.
        $this->service->createProject($this->profile, new ProjectCreateRequestDTO(
            name: 'First',
            startDate: '2024-01-01',
            descriptionMd: 'x',
            tags: ['PHP'],
        ));

        // Second project with the same tag in a different case must REUSE the
        // row, not create a duplicate (unique on LOWER(name)).
        $this->service->createProject($this->profile, new ProjectCreateRequestDTO(
            name: 'Second',
            startDate: '2024-02-01',
            descriptionMd: 'y',
            tags: ['php'],
        ));

        $tags = $this->em->getRepository(Tag::class)->findAll();
        self::assertCount(1, $tags, 'tag must be created once and reused');
        self::assertSame('PHP', $tags[0]->getName(), 'first-created casing wins');
    }

    public function testCreateWithTagInsertedByConcurrentRequestDoesNotDuplicate(): void
    {
        // Simulate the create race window: the tag row appears in the SAME
        // transaction behind our back (as if another request had won between
        // our SELECT and INSERT). resolveTag must catch the unique violation,
        // detach the doomed duplicate and reuse the winner row.
        $this->em->getConnection()->executeStatement("INSERT INTO tag (name) VALUES ('Python')");

        $view = $this->service->createProject($this->profile, new ProjectCreateRequestDTO(
            name: 'Concurrent',
            startDate: '2024-01-01',
            descriptionMd: 'x',
            tags: ['Python'],
        ));

        self::assertCount(1, $view->tags);
        self::assertSame(['Python'], $view->tags);

        $tags = $this->em->getRepository(Tag::class)->findAll();
        self::assertCount(1, $tags, 'no duplicate tag row may be created');
    }

    public function testDeleteProjectRemovesRow(): void
    {
        $created = $this->service->createProject($this->profile, new ProjectCreateRequestDTO(
            name: 'Doomed',
            startDate: '2024-01-01',
            descriptionMd: 'x',
        ));
        $project = $this->em->find(Project::class, $created->id);

        $this->service->deleteProject($project);

        self::assertNull($this->em->find(Project::class, $created->id));
    }

    public function testListProjectsReturnsNewestFirstWithTags(): void
    {
        $this->service->createProject($this->profile, new ProjectCreateRequestDTO(
            name: 'Older',
            startDate: '2023-01-01',
            descriptionMd: 'x',
            tags: ['R'],
        ));
        $this->service->createProject($this->profile, new ProjectCreateRequestDTO(
            name: 'Newer',
            startDate: '2025-01-01',
            descriptionMd: 'y',
            tags: ['Python', 'SQL'],
        ));

        $views = $this->service->listProjects($this->profile);

        self::assertCount(2, $views);
        self::assertSame('Newer', $views[0]->name, 'must be ordered by startDate DESC');
        self::assertSame(['Python', 'SQL'], $views[0]->tags);
        self::assertSame('Older', $views[1]->name);
    }
}
