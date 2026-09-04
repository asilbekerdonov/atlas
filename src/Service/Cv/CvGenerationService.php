<?php

declare(strict_types=1);

namespace App\Service\Cv;

use App\DTO\CvAttributeViewDTO;
use App\DTO\CvProjectViewDTO;
use App\DTO\CvViewDTO;
use App\Entity\Attribute;
use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\CvIncompleteException;
use App\Exception\OptimisticLockConflictException;
use App\Exception\PositionAccessDeniedException;
use App\Service\AccessRule\AccessRuleEvaluator;
use App\Service\Attribute\AttributeValueParser;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

/**
 * Killer Feature #3: CV generation and in-place sync.
 *
 * The CV itself stores no attribute copies: it always renders the candidate's
 * master profile values, edited directly in CandidateAttributeValue.
 */
class CvGenerationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccessRuleEvaluator $accessRuleEvaluator,
        private readonly AttributeValueParser $valueParser,
    ) {
    }

    public function getOrCreateCv(User $candidate, Position $position): Cv
    {
        // Public positions are open to every candidate — no profile and no
        // access rules involved. Restricted positions require a profile whose
        // values satisfy all access rules.
        $profile = $candidate->getProfile();
        $accessible = $position->isPublic()
            || ($profile !== null && $this->accessRuleEvaluator->isPositionAccessible($position, $profile));

        if (!$accessible) {
            throw new PositionAccessDeniedException($position->getId());
        }

        $cv = $this->em->getRepository(Cv::class)->findOneBy([
            'candidate' => $candidate,
            'position' => $position,
        ]);

        if ($cv === null) {
            $cv = new Cv($candidate, $position); // starts as DRAFT
            $this->em->persist($cv);
            $this->em->flush();
        }

        return $cv;
    }

    public function assembleCvView(Cv $cv): CvViewDTO
    {
        $candidate = $cv->getCandidate();
        $profile = $candidate->getProfile();
        $position = $cv->getPosition();

        $attributeViews = [];
        foreach ($position->getTemplateAttributes() as $templateAttribute) {
            $attribute = $templateAttribute->getAttribute();
            $value = $this->findProfileValue($profile, $attribute);

            $attributeViews[] = new CvAttributeViewDTO(
                attributeId: $attribute->getId(),
                name: $attribute->getName(),
                dataType: $attribute->getDataType(),
                isRequired: $templateAttribute->isRequired(),
                isEmpty: $value === null || $value->isEmpty(),
                rawValue: $this->rawValueOf($value),
                displayValue: $this->displayValueOf($value),
                version: $value?->getVersion(),
                options: array_map(
                    static fn ($option): array => ['id' => $option->getId(), 'label' => $option->getLabel()],
                    $attribute->getOptions()->toArray(),
                ),
            );
        }

        return new CvViewDTO(
            cvId: $cv->getId(),
            status: $cv->getStatus(),
            firstName: $profile?->getFirstName(),
            lastName: $profile?->getLastName(),
            location: $profile?->getLocation(),
            avatarUrl: $profile?->getAvatarUrl(),
            attributes: $attributeViews,
            projects: $this->findRelevantProjects($profile, $position),
        );
    }

    /**
     * Updates (or creates) a typed value directly in the candidate's master
     * profile — the CV has no local attribute copies.
     */
    public function updateCvAttributeInPlace(
        User $candidate,
        int $attributeId,
        mixed $rawValue,
        ?int $expectedVersion,
    ): CandidateAttributeValue {
        // A candidate may apply to a public position and start editing the CV
        // before any profile exists — create the profile lazily instead of
        // failing (the profile is the single source of truth for values).
        $profile = $this->getOrCreateProfile($candidate);

        $attribute = $this->em->find(Attribute::class, $attributeId);
        if ($attribute === null) {
            throw new InvalidArgumentException(sprintf('Attribute #%d does not exist.', $attributeId));
        }

        $value = $this->findProfileValue($profile, $attribute);
        if ($value === null) {
            $value = new CandidateAttributeValue($profile, $attribute);
            $profile->getAttributeValues()->add($value);
            $this->em->persist($value);
        } elseif ($expectedVersion === null || $value->getVersion() !== $expectedVersion) {
            throw new OptimisticLockConflictException($value, $value->getVersion());
        }

        $this->valueParser->apply($value, $attribute, $rawValue);
        $this->em->flush();

        return $value;
    }

    public function publishCv(Cv $cv): void
    {
        $profile = $cv->getCandidate()->getProfile();
        $missing = [];

        foreach ($cv->getPosition()->getTemplateAttributes() as $templateAttribute) {
            if (!$templateAttribute->isRequired()) {
                continue;
            }

            $value = $this->findProfileValue($profile, $templateAttribute->getAttribute());
            if ($value === null || $value->isEmpty()) {
                $missing[] = $templateAttribute->getAttribute();
            }
        }

        if ($missing !== []) {
            throw new CvIncompleteException($missing);
        }

        $cv->publish();
        $this->em->flush();
    }

    /**
     * Projects of the candidate sharing at least one tag with the position,
     * newest first, capped by position.maxProjects.
     *
     * @return list<CvProjectViewDTO>
     */
    private function findRelevantProjects(?CandidateProfile $profile, Position $position): array
    {
        if ($profile === null || $position->getTags()->isEmpty()) {
            return [];
        }

        /** @var list<Project> $projects */
        $projects = $this->em->createQueryBuilder()
            ->select('DISTINCT p')
            ->from(Project::class, 'p')
            ->join('p.tags', 't')
            ->where('p.profile = :profile')
            ->andWhere('t IN (:positionTags)')
            ->setParameter('profile', $profile)
            ->setParameter('positionTags', $position->getTags()->toArray())
            ->orderBy('p.startDate', 'DESC')
            ->setMaxResults($position->getMaxProjects())
            ->getQuery()
            ->getResult();

        return array_map(static function (Project $project): CvProjectViewDTO {
            return new CvProjectViewDTO(
                id: $project->getId(),
                name: $project->getName(),
                startDate: $project->getStartDate(),
                endDate: $project->getEndDate(),
                descriptionMd: $project->getDescriptionMd(),
                tags: array_map(
                    static fn ($tag): string => $tag->getName(),
                    $project->getTags()->toArray(),
                ),
            );
        }, $projects);
    }

    private function findProfileValue(?CandidateProfile $profile, Attribute $attribute): ?CandidateAttributeValue
    {
        if ($profile === null) {
            return null;
        }

        foreach ($profile->getAttributeValues() as $value) {
            $valueAttribute = $value->getAttribute();
            if ($valueAttribute === $attribute || $valueAttribute->getId() === $attribute->getId()) {
                return $value;
            }
        }

        return null;
    }

    public function rawValueOf(?CandidateAttributeValue $value): ?string
    {
        if ($value === null || $value->isEmpty()) {
            return null;
        }

        return match (true) {
            $value->getValueOption() !== null => (string) $value->getValueOption()->getId(),
            $value->getValueBoolean() !== null => $value->getValueBoolean() ? 'true' : 'false',
            $value->getValueDate() !== null => $value->getValueDate()->format('Y-m-d'),
            $value->getValueDateRangeStart() !== null => $value->getValueDateRangeStart()->format('Y-m-d'),
            $value->getValueNumeric() !== null => $value->getValueNumeric(),
            $value->getValueText() !== null => $value->getValueText(),
            default => $value->getValueString(),
        };
    }

    public function displayValueOf(?CandidateAttributeValue $value): ?string
    {
        if ($value === null || $value->isEmpty()) {
            return null;
        }

        return match (true) {
            $value->getValueOption() !== null => $value->getValueOption()->getLabel(),
            $value->getValueBoolean() !== null => $value->getValueBoolean() ? 'Yes' : 'No',
            $value->getValueDate() !== null => $value->getValueDate()->format('Y-m-d'),
            $value->getValueDateRangeStart() !== null => $value->getValueDateRangeStart()->format('Y-m-d')
                . ($value->getValueDateRangeEnd() !== null ? ' — ' . $value->getValueDateRangeEnd()->format('Y-m-d') : ''),
            $value->getValueNumeric() !== null => $value->getValueNumeric(),
            $value->getValueText() !== null => mb_substr($value->getValueText(), 0, 120),
            default => $value->getValueString(),
        };
    }

    /**
     * Creates a CandidateProfile on demand (candidates may edit CV values
     * right after OAuth login, before ever opening the profile editor).
     */
    private function getOrCreateProfile(User $candidate): CandidateProfile
    {
        $profile = $candidate->getProfile();
        if ($profile !== null) {
            return $profile;
        }

        [$firstName, $lastName] = $this->splitCandidateName($candidate->getEmail());
        $profile = new CandidateProfile($candidate, $firstName, $lastName);
        $this->em->persist($profile);

        return $profile;
    }

    /** @return array{string, string} */
    private function splitCandidateName(string $email): array
    {
        $local = str_replace(['.', '_', '-'], ' ', (string) preg_split('/@/', $email, 2)[0]);
        $parts = preg_split('/\s+/', trim($local), 2);

        return [ucfirst($parts[0] ?? 'Candidate'), ucfirst($parts[1] ?? '')];
    }
}
