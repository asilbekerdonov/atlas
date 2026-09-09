<?php

declare(strict_types=1);

namespace App\Service\Attribute;

use App\DTO\AttributeDTO;
use App\Entity\Attribute;
use App\Entity\AttributeCategory;
use App\Entity\AttributeOption;
use App\Entity\CandidateAttributeValue;
use App\Entity\PositionAccessRule;
use App\Entity\PositionTemplateAttribute;
use App\Entity\User;
use App\Entity\UserRecentAttribute;
use App\Enum\AttributeDataType;
use App\Exception\AttributeAlreadyExistsException;
use App\Exception\AttributeInUseException;
use App\Exception\AttributeTypeChangeForbiddenException;
use App\Exception\CategoryNotFoundException;
use App\Repository\AttributeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Killer Feature #1: reusable attribute library management.
 *
 * Category lookup rows are resolved HERE (never in the controller): the
 * controller works only with DTOs carrying flat categoryId/categoryName.
 */
class AttributeLibraryService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeRepository $attributeRepository,
    ) {
    }

    /** All categories ordered by name (drives dropdowns). */
    public function listCategories(): array
    {
        return $this->em->getRepository(AttributeCategory::class)->findBy([], ['name' => 'ASC']);
    }

    /**
     * All attributes ordered by name with their category JOINed in one query.
     * Read-only listing — controllers reach attributes ONLY through the
     * service (Controller → Service → Repository).
     *
     * @return list<Attribute>
     */
    public function listAttributes(): array
    {
        return $this->attributeRepository->findAllWithCategory();
    }

    /**
     * Category name (string from request) → lookup entity, else 422-domain
     * exception. The single place that maps client input to the entity.
     */
    public function resolveCategoryOrFail(string $categoryName): AttributeCategory
    {
        $category = $this->em->getRepository(AttributeCategory::class)
            ->findOneBy(['name' => $categoryName]);

        if ($category === null) {
            throw new CategoryNotFoundException($categoryName);
        }

        return $category;
    }

    public function createAttribute(AttributeDTO $dto): Attribute
    {
        $this->assertNameAvailable($dto->name);

        $attribute = new Attribute(
            $dto->name,
            $this->resolveCategoryOrFail($dto->categoryName),
            $dto->dataType,
            $dto->description,
        );
        $this->syncOptions($attribute, $dto->dataType, $dto->options);
        $this->em->persist($attribute);
        $this->em->flush();

        return $attribute;
    }

    public function updateAttribute(Attribute $attribute, AttributeDTO $dto): Attribute
    {
        // The data type is frozen once any candidate value exists — changing it
        // would silently corrupt typed columns.
        if ($attribute->getDataType() !== $dto->dataType && $this->hasValues($attribute)) {
            throw new AttributeTypeChangeForbiddenException($attribute->getName());
        }

        // Renaming to a name already used by another attribute is a conflict.
        // Case-insensitive: "Python" and "python" are the same name, but the
        // attribute itself is excluded so a pure case change stays allowed.
        if ($dto->name !== $attribute->getName()) {
            $this->assertNameAvailable($dto->name, $attribute->getId());
        }

        $attribute->setName($dto->name);
        $attribute->setCategory($this->resolveCategoryOrFail($dto->categoryName));
        $attribute->setDescription($dto->description);
        if ($attribute->getDataType() !== $dto->dataType) {
            // Allowed only when no values exist yet.
            $attribute->changeDataType($dto->dataType);
        }

        $this->syncOptions($attribute, $dto->dataType, $dto->options);
        $this->em->flush();

        return $attribute;
    }

    public function deleteAttribute(Attribute $attribute): void
    {
        $usageCount = $this->em->getRepository(CandidateAttributeValue::class)->count(['attribute' => $attribute])
            + $this->em->getRepository(PositionTemplateAttribute::class)->count(['attribute' => $attribute])
            + $this->em->getRepository(PositionAccessRule::class)->count(['attribute' => $attribute]);

        if ($usageCount > 0) {
            throw new AttributeInUseException($attribute->getName(), $usageCount);
        }

        $this->em->remove($attribute);
        $this->em->flush();
    }

    /**
     * Prefix search over active attributes, with the user's 5 most recently
     * used attributes prepended as a separate block. Categories are fetched
     * with a JOIN in the same query — no per-row lazy loads.
     *
     * @param string|null $categoryName filter by category name; null = no filter
     *
     * @return list<Attribute>
     */
    public function searchAttributes(string $prefix, ?string $categoryName, User $user, int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $recentAttributes = $this->findRecentAttributes($user, 5);

        $qb = $this->em->createQueryBuilder()
            ->select('a', 'c')
            ->from(Attribute::class, 'a')
            ->join('a.category', 'c')
            ->where('a.isActive = true')
            ->orderBy('a.name', 'ASC');

        if ($prefix !== '') {
            $qb->andWhere('LOWER(a.name) LIKE :prefix')
                ->setParameter('prefix', strtolower($prefix) . '%');
        }
        if ($categoryName !== null && $categoryName !== '') {
            $qb->andWhere('c.name = :category')
                ->setParameter('category', $categoryName);
        }
        $qb->setMaxResults($limit);

        $result = [];
        $seenIds = [];
        foreach ($recentAttributes as $attribute) {
            $result[] = $attribute;
            $seenIds[$attribute->getId()] = true;
        }
        foreach ($qb->getQuery()->getResult() as $row) {
            $attribute = $this->extractAttribute($row);
            if ($attribute === null || isset($seenIds[$attribute->getId()])) {
                continue;
            }
            $result[] = $attribute;
            $seenIds[$attribute->getId()] = true;
        }

        return array_slice($result, 0, $limit);
    }

    public function trackRecentUse(User $user, Attribute $attribute, string $context): void
    {
        $recent = $this->em->getRepository(UserRecentAttribute::class)
            ->findOneBy(['user' => $user, 'attribute' => $attribute]);

        if ($recent === null) {
            $recent = new UserRecentAttribute($user, $attribute, $context);
            $this->em->persist($recent);
        } else {
            $recent->markUsed();
            $recent->setContext($context);
        }

        $this->em->flush();
    }

    /** @return list<Attribute> unique, most recently used first (category JOINed) */
    private function findRecentAttributes(User $user, int $max): array
    {
        // Two queries, never N+1: first the ordered attribute ids from the
        // usage log, then the attributes WITH their category in one JOIN.
        $ids = $this->em->createQueryBuilder()
            ->select('IDENTITY(ura.attribute) AS attributeId')
            ->from(UserRecentAttribute::class, 'ura')
            ->where('ura.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ura.lastUsedAt', 'DESC')
            ->setMaxResults($max * 3)
            ->getQuery()
            ->getScalarResult();

        if ($ids === []) {
            return [];
        }

        $idList = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['attributeId'], $ids)));

        $rows = $this->em->createQueryBuilder()
            ->select('a', 'c')
            ->from(Attribute::class, 'a')
            ->join('a.category', 'c')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $idList)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($rows as $row) {
            $attribute = $this->extractAttribute($row);
            if ($attribute !== null && $attribute->isActive()) {
                $byId[$attribute->getId()] = $attribute;
            }
        }

        // Preserve the recency order from the usage log.
        $attributes = [];
        foreach ($idList as $id) {
            if (isset($byId[$id])) {
                $attributes[] = $byId[$id];
            }
            if (count($attributes) >= $max) {
                break;
            }
        }

        return $attributes;
    }

    /**
     * ORM 3 hydration: a query selecting 'a','c' returns the root entity for
     * each row (the joined category lands in the fetched association), but
     * pair-row shapes are handled defensively.
     */
    private function extractAttribute(mixed $row): ?Attribute
    {
        if ($row instanceof Attribute) {
            return $row;
        }
        if (is_array($row)) {
            $attribute = $row['a'] ?? reset($row);

            return $attribute instanceof Attribute ? $attribute : null;
        }

        return null;
    }

    private function hasValues(Attribute $attribute): bool
    {
        return $this->em->getRepository(CandidateAttributeValue::class)->count(['attribute' => $attribute]) > 0;
    }

    /**
     * Case-insensitive uniqueness: "Python" and "python" are the same name.
     * The DB enforces it too via uniq_attribute_name_lower (LOWER(name)), so
     * a concurrent insert still ends up as a UniqueConstraintViolationException
     * → 409 in the controller. This pre-check exists only to fail fast with a
     * clean domain exception instead of a raw DB error.
     */
    private function assertNameAvailable(string $name, ?int $exceptId = null): void
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Attribute::class, 'a')
            ->where('LOWER(a.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->setMaxResults(1);

        if ($exceptId !== null) {
            $qb->andWhere('a.id <> :exceptId')->setParameter('exceptId', $exceptId);
        }

        $existing = $qb->getQuery()->getOneOrNullResult();

        if ($existing !== null) {
            throw new AttributeAlreadyExistsException($name);
        }
    }

    /**
     * ONE_OF_MANY attributes carry AttributeOption rows; every other data type
     * must have none (stale options are removed via orphanRemoval).
     *
     * @param list<string> $options
     */
    private function syncOptions(Attribute $attribute, AttributeDataType $dataType, array $options): void
    {
        $attribute->getOptions()->clear();

        if ($dataType !== AttributeDataType::ONE_OF_MANY) {
            return;
        }

        foreach (array_values($options) as $index => $label) {
            $attribute->getOptions()->add(new AttributeOption($attribute, $label, $label, $index + 1));
        }
    }
}
