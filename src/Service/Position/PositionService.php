<?php

declare(strict_types=1);

namespace App\Service\Position;

use App\DTO\PositionAccessRuleDTO;
use App\DTO\PositionDTO;
use App\DTO\PositionTemplateAttributeDTO;
use App\Entity\Attribute;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\PositionTemplateAttribute;
use App\Entity\Tag;
use App\Entity\User;
use App\Exception\OptimisticLockConflictException;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Killer Feature #2: position (vacancy template) management.
 *
 * Mutations invalidate the cached home page (tag cloud, stats) — otherwise
 * deleting a position would leave its tags visible in the cloud until the
 * 120 s cache expired.
 */
class PositionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    public function createPosition(PositionDTO $dto, User $author): Position
    {
        $position = new Position($dto->title, $dto->shortDescription);
        $this->applyDto($position, $dto);
        $this->em->persist($position);
        $this->em->flush();
        $this->invalidateHomeCache();

        return $position;
    }

    /**
     * Deep copy: base fields (prefixed with "Copy of"), template attributes,
     * access rules and tags — all as fresh rows except the shared tags.
     */
    public function duplicatePosition(Position $sourcePosition, User $author): Position
    {
        $copy = new Position('Copy of ' . $sourcePosition->getTitle(), $sourcePosition->getShortDescription());
        $copy->setCompanyName($sourcePosition->getCompanyName());
        $copy->setLevel($sourcePosition->getLevel());
        $copy->setPublic($sourcePosition->isPublic());
        $copy->setFormat($sourcePosition->getFormat());
        $copy->setMaxProjects($sourcePosition->getMaxProjects());

        foreach ($sourcePosition->getTemplateAttributes() as $templateAttribute) {
            $copy->getTemplateAttributes()->add(new PositionTemplateAttribute(
                $copy,
                $templateAttribute->getAttribute(),
                $templateAttribute->isRequired(),
                $templateAttribute->getSortOrder(),
            ));
        }

        foreach ($sourcePosition->getAccessRules() as $accessRule) {
            $copy->getAccessRules()->add(new PositionAccessRule(
                $copy,
                $accessRule->getAttribute(),
                $accessRule->getOperator(),
                $accessRule->getRuleValue(),
            ));
        }

        foreach ($sourcePosition->getTags() as $tag) {
            $copy->addTag($tag);
        }

        $this->em->persist($copy);
        $this->em->flush();
        $this->invalidateHomeCache();

        return $copy;
    }

    public function updatePosition(Position $position, PositionDTO $dto, int $expectedVersion): Position
    {
        if ($position->getVersion() !== $expectedVersion) {
            throw new OptimisticLockConflictException($position, $position->getVersion());
        }

        $this->applyDto($position, $dto);
        $this->em->flush();
        $this->invalidateHomeCache();

        return $position;
    }

    public function softDeletePosition(Position $position): void
    {
        // Soft delete keeps already attached CVs intact.
        $position->softDelete();
        $this->em->flush();
        $this->invalidateHomeCache();
    }

    /** The home page caches the tag cloud and stats — drop them on mutation. */
    private function invalidateHomeCache(): void
    {
        $this->cache->deleteItems(['home.tag_cloud.v1', 'home.stats.v1']);
    }

    private function applyDto(Position $position, PositionDTO $dto): void
    {
        $position->setTitle($dto->title);
        $position->setShortDescription($dto->shortDescription);
        $position->setCompanyName($dto->companyName);
        $position->setLevel($dto->level);
        $position->setPublic($dto->isPublic);
        $position->setMaxProjects($dto->maxProjects);
        $position->setFormat($dto->format);

        // Replace the template (orphanRemoval drops removed rows).
        $position->getTemplateAttributes()->clear();
        foreach ($dto->templateAttributes as $templateDto) {
            $position->getTemplateAttributes()->add(new PositionTemplateAttribute(
                $position,
                $this->requireAttribute($templateDto),
                $templateDto->isRequired,
                $templateDto->sortOrder,
            ));
        }

        // Replace the access rules.
        $position->getAccessRules()->clear();
        foreach ($dto->accessRules as $ruleDto) {
            $position->getAccessRules()->add(new PositionAccessRule(
                $position,
                $this->requireAttribute($ruleDto),
                $ruleDto->operator,
                $ruleDto->ruleValue,
            ));
        }

        // Replace tags (shared rows, resolved case-insensitively).
        $position->getTags()->clear();
        foreach ($dto->tags as $tagName) {
            $position->addTag($this->resolveTag($tagName));
        }
    }

    private function requireAttribute(PositionTemplateAttributeDTO|PositionAccessRuleDTO $dto): Attribute
    {
        $attribute = $this->em->find(Attribute::class, $dto->attributeId);
        if ($attribute === null) {
            throw new InvalidArgumentException(sprintf('Attribute #%d does not exist.', $dto->attributeId));
        }

        return $attribute;
    }

    private function resolveTag(string $name): Tag
    {
        $tag = $this->em->createQueryBuilder()
            ->select('t')
            ->from(Tag::class, 't')
            ->where('LOWER(t.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($tag === null) {
            $tag = new Tag($name);
            $this->em->persist($tag);
        }

        return $tag;
    }
}
