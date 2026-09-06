<?php

declare(strict_types=1);

namespace App\Service\Project;

use App\DTO\ProjectViewDTO;
use App\DTO\Request\ProjectCreateRequestDTO;
use App\DTO\Request\ProjectUpdateRequestDTO;
use App\Entity\CandidateProfile;
use App\Entity\Project;
use App\Entity\Tag;
use App\Exception\OptimisticLockConflictException;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;

/**
 * CRUD for candidate projects (with technology tags).
 *
 * Tags are shared lookup rows (unique on LOWER(name)): the service resolves
 * them get-or-create, handling the unique-constraint race when two requests
 * create the same tag simultaneously. Project rows carry an optimistic-lock
 * version — updates compare it and throw on mismatch (HTTP 409 upstream).
 */
class ProjectService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * All projects of a profile, newest first, tags fetched in the SAME query
     * (LEFT JOIN) — no per-row lazy loads when building the view.
     *
     * @return list<ProjectViewDTO>
     */
    public function listProjects(CandidateProfile $profile): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('p', 't')
            ->from(Project::class, 'p')
            ->leftJoin('p.tags', 't')
            ->where('p.profile = :profile')
            ->setParameter('profile', $profile)
            ->orderBy('p.startDate', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        // ORM 3 hydrates root entities (tags land in the fetched collection);
        // pair-row shapes are handled defensively.
        $projects = [];
        foreach ($rows as $row) {
            $project = $row instanceof Project ? $row : ($row['p'] ?? reset($row));
            if ($project instanceof Project) {
                $projects[] = $project;
            }
        }

        return array_map($this->toView(...), $projects);
    }

    public function createProject(CandidateProfile $profile, ProjectCreateRequestDTO $dto): ProjectViewDTO
    {
        $project = new Project($profile, $dto->name, $this->parseDate($dto->startDate), $dto->descriptionMd);
        $project->setEndDate($dto->endDate !== null && $dto->endDate !== '' ? $this->parseDate($dto->endDate) : null);
        $this->applyTags($project, $dto->tags);

        $this->em->persist($project);
        $this->em->flush();
        $this->invalidateHomeCache();

        return $this->toView($project);
    }

    /**
     * @throws OptimisticLockConflictException when the payload version is stale
     */
    public function updateProject(Project $project, ProjectUpdateRequestDTO $dto): ProjectViewDTO
    {
        if ($project->getVersion() !== $dto->expectedVersion) {
            throw new OptimisticLockConflictException($project, $project->getVersion());
        }

        $project->setName($dto->name);
        $project->setStartDate($this->parseDate($dto->startDate));
        $project->setEndDate($dto->endDate !== null && $dto->endDate !== '' ? $this->parseDate($dto->endDate) : null);
        $project->setDescriptionMd($dto->descriptionMd);
        $this->applyTags($project, $dto->tags);

        $this->em->flush();
        $this->invalidateHomeCache();

        return $this->toView($project);
    }

    public function deleteProject(Project $project): void
    {
        $this->em->remove($project);
        $this->em->flush();
        $this->invalidateHomeCache();
    }

    /** Projects feed the home tag cloud — drop the cached copy on mutation. */
    private function invalidateHomeCache(): void
    {
        $this->cache->deleteItems(['home.tag_cloud.v1', 'home.stats.v1']);
    }

    private function toView(Project $project): ProjectViewDTO
    {
        return new ProjectViewDTO(
            id: $project->getId(),
            name: $project->getName(),
            startDate: $project->getStartDate()->format('Y-m-d'),
            endDate: $project->getEndDate()?->format('Y-m-d'),
            descriptionMd: $project->getDescriptionMd(),
            tags: array_map(static fn (Tag $tag): string => $tag->getName(), $project->getTags()->toArray()),
            version: $project->getVersion(),
        );
    }

    private function applyTags(Project $project, array $tags): void
    {
        $project->getTags()->clear();
        foreach ($tags as $tagName) {
            $project->addTag($this->resolveTag((string) $tagName));
        }
    }

    /**
     * Get-or-create a tag by its (case-insensitive unique) name.
     *
     * A create race is handled explicitly: flush ONLY the new tag so a lost
     * race rolls back nothing else; on UniqueConstraintViolationException the
     * doomed duplicate is detached and the winner row is re-read.
     */
    private function resolveTag(string $name): Tag
    {
        $tag = $this->findTag($name);
        if ($tag !== null) {
            return $tag;
        }

        $tag = new Tag($name);
        $this->em->persist($tag);

        try {
            // Flush just this row: pending project changes stay in the UoW
            // and are committed by the caller's final flush.
            $this->em->flush($tag);
        } catch (UniqueConstraintViolationException) {
            $this->em->detach($tag);
            $tag = $this->findTag($name);
            if ($tag === null) {
                throw new RuntimeException(sprintf('Could not resolve tag "%s" after a create race.', $name));
            }
        }

        return $tag;
    }

    private function findTag(string $name): ?Tag
    {
        $tag = $this->em->createQueryBuilder()
            ->select('t')
            ->from(Tag::class, 't')
            ->where('LOWER(t.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $tag instanceof Tag ? $tag : null;
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($date === false) {
            throw new InvalidArgumentException(sprintf('Invalid date "%s", expected YYYY-MM-DD.', $value));
        }

        return $date;
    }
}
