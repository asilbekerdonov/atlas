<?php

declare(strict_types=1);

namespace App\Service\Profile;

use App\DTO\ProfileAttributeValueDeleteResultDTO;
use App\DTO\ProfileAutosaveDTO;
use App\DTO\ProfileAutosaveResultDTO;
use App\Entity\Attribute;
use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\Cv;
use App\Entity\User;
use App\Enum\CvStatus;
use App\Exception\AttributeValueNotFoundException;
use App\Exception\OptimisticLockConflictException;
use App\Service\Attribute\AttributeValueParser;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Autosave of the candidate profile: base fields + attribute values, guarded
 * by optimistic locking so concurrent edits surface as HTTP 409 with fresh
 * data instead of silent overwrites.
 */
class ProfileAutosaveService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AttributeValueParser $valueParser,
    ) {
    }

    public function autosaveProfile(User $user, ProfileAutosaveDTO $dto): ProfileAutosaveResultDTO
    {
        $profile = $user->getProfile();
        if ($profile === null) {
            $profile = new CandidateProfile($user, $dto->firstName ?? '', $dto->lastName ?? '');
            $this->em->persist($profile);
        }

        if ($dto->expectedVersion !== null && $profile->getVersion() !== $dto->expectedVersion) {
            throw new OptimisticLockConflictException($profile, $profile->getVersion());
        }

        if ($dto->firstName !== null) {
            $profile->setFirstName($dto->firstName);
        }
        if ($dto->lastName !== null) {
            $profile->setLastName($dto->lastName);
        }
        if ($dto->location !== null) {
            $profile->setLocation($dto->location);
        }
        if ($dto->avatarUrl !== null) {
            $profile->setAvatarUrl($dto->avatarUrl);
        }

        foreach ($dto->attributeValues as $attributeValueDto) {
            $attribute = $this->em->find(Attribute::class, $attributeValueDto->attributeId);
            if ($attribute === null) {
                throw new InvalidArgumentException(sprintf('Attribute #%d does not exist.', $attributeValueDto->attributeId));
            }

            $value = $this->findValue($profile, $attribute);
            if ($value === null) {
                $value = new CandidateAttributeValue($profile, $attribute);
                $profile->getAttributeValues()->add($value);
                $this->em->persist($value);
            }
            $this->valueParser->apply($value, $attribute, $attributeValueDto->rawValue);
        }

        $this->em->flush();

        return new ProfileAutosaveResultDTO($profile, $profile->getVersion());
    }

    /**
     * Deletes ONE attribute value from the candidate's own profile.
     *
     * Any PUBLISHED CV whose position template uses the removed attribute is
     * automatically reverted to DRAFT (it would render an empty required
     * value) — the caller surfaces this to the candidate as a notice.
     *
     * @throws AttributeValueNotFoundException when the value does not exist
     *                                         on the candidate's own profile
     */
    public function deleteAttributeValue(User $user, int $valueId): ProfileAttributeValueDeleteResultDTO
    {
        $profile = $user->getProfile();
        if ($profile === null) {
            throw new AttributeValueNotFoundException($valueId);
        }

        $value = $this->em->find(CandidateAttributeValue::class, $valueId);
        if ($value === null || !$this->belongsTo($value, $profile)) {
            throw new AttributeValueNotFoundException($valueId);
        }

        $attribute = $value->getAttribute();

        // Remove the row (orphanRemoval is off — delete explicitly).
        $profile->getAttributeValues()->removeElement($value);
        $this->em->remove($value);

        // Revert published CVs that render this attribute.
        $unpublishedCvs = $this->unpublishCvsUsingAttribute($user, $attribute);

        // Bump the profile version so concurrent autosaves surface a 409.
        $profile->markChanged();
        $this->em->flush();

        return new ProfileAttributeValueDeleteResultDTO(
            newVersion: $profile->getVersion(),
            unpublishedCvs: $unpublishedCvs,
        );
    }

    private function belongsTo(CandidateAttributeValue $value, CandidateProfile $profile): bool
    {
        $valueProfile = $value->getProfile();

        return $valueProfile === $profile || $valueProfile->getId() === $profile->getId();
    }

    /**
     * @return list<array{cvId: int, positionTitle: string}>
     */
    private function unpublishCvsUsingAttribute(User $user, Attribute $attribute): array
    {
        /** @var list<Cv> $cvs */
        $cvs = $this->em->createQueryBuilder()
            ->select('cv')
            ->from(Cv::class, 'cv')
            ->join('cv.position', 'p')
            ->join('p.templateAttributes', 'ta')
            ->where('cv.candidate = :user')
            ->andWhere('cv.status = :published')
            ->andWhere('ta.attribute = :attribute')
            ->setParameter('user', $user)
            ->setParameter('published', CvStatus::PUBLISHED)
            ->setParameter('attribute', $attribute)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($cvs as $cv) {
            $cv->unpublish();
            $result[] = [
                'cvId' => $cv->getId(),
                'positionTitle' => $cv->getPosition()->getTitle(),
            ];
        }

        return $result;
    }

    private function findValue(CandidateProfile $profile, Attribute $attribute): ?CandidateAttributeValue
    {
        foreach ($profile->getAttributeValues() as $value) {
            $valueAttribute = $value->getAttribute();
            if ($valueAttribute === $attribute || $valueAttribute->getId() === $attribute->getId()) {
                return $value;
            }
        }

        return null;
    }
}
