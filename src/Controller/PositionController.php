<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\PositionRequestDTO;
use App\DTO\PositionAccessRuleDTO;
use App\DTO\PositionDTO;
use App\DTO\PositionTemplateAttributeDTO;
use App\DTO\Request\PositionAccessRuleRequestDTO;
use App\DTO\Request\PositionTemplateAttributeRequestDTO;
use App\Entity\Cv;
use App\Entity\Position;
use App\Enum\AccessRuleOperator;
use App\Enum\CvStatus;
use App\Exception\OptimisticLockConflictException;
use App\Repository\AttributeRepository;
use App\Repository\CvRepository;
use App\Repository\PositionRepository;
use App\Security\Voter\PositionVoter;
use Doctrine\DBAL\ArrayParameterType;
use App\Service\Position\PositionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Position management. HTML pages are thin; mutations are JSON APIs.
 */
final class PositionController extends AbstractController
{
    public function __construct(
        private readonly PositionRepository $positionRepository,
        private readonly PositionService $positionService,
    ) {
    }

    #[Route('/positions', name: 'position_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $page = max(1, (int) $request->query->get('page', '1'));

        if ($user !== null && $this->isGranted('ROLE_CANDIDATE') && !$this->isGranted('ROLE_RECRUITER')) {
            $profile = $user->getProfile();
            $result = $profile !== null
                ? $this->positionRepository->findPositionsForCandidate($profile->getId(), true, $page, 20)
                : ['items' => [], 'total' => 0, 'accessibleById' => []];
            $positions = $result['items'];
            $total = $result['total'];
        } else {
            // Guests and recruiters see everything.
            $positions = $this->positionRepository->findLatest(20);
            $total = count($positions);
        }

        $positionIds = array_map(static fn (Position $p): int => $p->getId(), $positions);

        return $this->render('position/index.html.twig', [
            'positions' => $positions,
            'total' => $total,
            'page' => $page,
            'tagMap' => $this->positionRepository->findTagsByPositionIds($positionIds),
            'cvStats' => $this->positionRepository->findCvStatsByPositionIds($positionIds),
        ]);
    }

    #[Route('/positions/{id}', name: 'position_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Position $position): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::VIEW, $position);

        $user = $this->getUser();
        $hasAppliedCv = false;
        if ($user !== null) {
            $hasAppliedCv = $this->positionRepository->getEntityManager()
                ->getRepository(Cv::class)
                ->findOneBy(['candidate' => $user, 'position' => $position]) !== null;
        }

        // Discussion author links: recruiters may open the candidate's PUBLISHED
        // CV (read-only, per rules); admins may open the full profile. Resolve
        // both maps in ONE query each — no per-row lazy loads in the template.
        $discussions = $position->getDiscussions();
        $authorIds = [];
        foreach ($discussions as $discussion) {
            $authorIds[$discussion->getAuthor()->getId()] = true;
        }

        $publishedCvIds = $authorIds !== [] ? $this->publishedCvIdByAuthorId(array_keys($authorIds)) : [];

        return $this->render('position/show.html.twig', [
            'position' => $position,
            'discussions' => $discussions,
            'hasAppliedCv' => $hasAppliedCv,
            'publishedCvIdByAuthorId' => $publishedCvIds,
        ]);
    }

    /**
     * First published CV id per candidate author — the page a recruiter may
     * open from a discussion post (published CVs are the recruiter-visible
     * read-only representation of a candidate).
     *
     * @param list<int> $authorIds
     *
     * @return array<int, int> user id => cv id
     */
    private function publishedCvIdByAuthorId(array $authorIds): array
    {
        $rows = $this->positionRepository->getEntityManager()->createQueryBuilder()
            ->select('u.id AS uid', 'c.id AS cid')
            ->from(Cv::class, 'c')
            ->join('c.candidate', 'u')
            ->where('u.id IN (:ids)')
            ->andWhere('c.status = :published')
            ->andWhere('c.isVisible = true')
            ->setParameter('ids', $authorIds, ArrayParameterType::INTEGER)
            ->setParameter('published', CvStatus::PUBLISHED)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $uid = (int) $row['uid'];
            if (!isset($map[$uid])) {
                $map[$uid] = (int) $row['cid']; // first published CV wins
            }
        }

        return $map;
    }

    #[Route('/positions/new', name: 'position_new', methods: ['GET'])]
    public function newForm(AttributeRepository $attributeRepository): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::CREATE);

        return $this->render('position/form.html.twig', [
            'position' => null,
            'attributes' => $attributeRepository->findBy([], ['name' => 'ASC']),
            'operators' => AccessRuleOperator::cases(),
        ]);
    }

    #[Route('/positions/new', name: 'position_new_post', methods: ['POST'])]
    public function create(Request $request, SerializerInterface $serializer, ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::CREATE);

        $dto = $this->denormalizePosition($request, $serializer);
        $errors = $this->validationErrors($validator, $dto);
        if ($errors !== null) {
            return $this->validationFailure($request, $errors, 'position_new');
        }

        $position = $this->positionService->createPosition($this->mapDto($dto), $this->getUser());

        return $this->creationResponse($request, $position);
    }

    #[Route('/positions/{id}/edit', name: 'position_edit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function editForm(Position $position, AttributeRepository $attributeRepository): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::EDIT, $position);

        return $this->render('position/form.html.twig', [
            'position' => $position,
            'attributes' => $attributeRepository->findBy([], ['name' => 'ASC']),
            'operators' => AccessRuleOperator::cases(),
        ]);
    }

    #[Route('/positions/{id}/edit', name: 'position_edit_post', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(Position $position, Request $request, SerializerInterface $serializer, ValidatorInterface $validator): JsonResponse|Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::EDIT, $position);

        $raw = $this->rawPayload($request);
        $dto = $this->denormalizePosition($request, $serializer);
        $errors = $this->validationErrors($validator, $dto);
        if ($errors !== null) {
            return $this->validationFailure($request, $errors, 'position_edit', ['id' => $position->getId()]);
        }

        // Optimistic lock: prefer the version from the payload body, fall back
        // to the ?version= query param, then to the current row version.
        $expectedVersion = isset($raw['version'])
            ? (int) $raw['version']
            : (int) $request->query->get('version', (string) $position->getVersion());
        try {
            $this->positionService->updatePosition($position, $this->mapDto($dto), $expectedVersion);
        } catch (OptimisticLockConflictException $e) {
            if ($this->wantsJson($request)) {
                return new JsonResponse(['success' => false, 'error' => 'conflict', 'serverVersion' => $e->getCurrentVersion()], Response::HTTP_CONFLICT);
            }
            $this->addFlash('error', 'Version conflict — reload and retry.');

            return $this->redirectToRoute('position_edit', ['id' => $position->getId()]);
        }

        return $this->creationResponse($request, $position);
    }

    #[Route('/positions/bulk-delete', name: 'position_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): JsonResponse|Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::DELETE);

        $raw = $this->rawPayload($request);
        $ids = $raw['ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return $this->wantsJson($request)
                ? $this->json(['success' => false, 'message' => 'No positions selected for deletion.'], Response::HTTP_BAD_REQUEST)
                : $this->redirectToRoute('position_index');
        }

        $deletedCount = 0;
        foreach ($ids as $id) {
            $position = $this->positionRepository->find((int) $id);
            if ($position !== null && $position->getDeletedAt() === null) {
                $this->positionService->softDeletePosition($position);
                ++$deletedCount;
            }
        }

        if ($this->wantsJson($request)) {
            return $this->json(['success' => true, 'deletedCount' => $deletedCount, 'message' => sprintf('%d position(s) deleted.', $deletedCount)]);
        }

        $this->addFlash('success', sprintf('%d position(s) deleted.', $deletedCount));

        return $this->redirectToRoute('position_index');
    }

    /** Raw payload independent of content type (JSON body or form fields). */
    private function rawPayload(Request $request): array
    {
        return $request->getContentTypeFormat() === 'json'
            ? (json_decode((string) $request->getContent(), true) ?? [])
            : $request->request->all();
    }

    /** Accepts both JSON (fetch) and classic form-urlencoded payloads. */
    private function denormalizePosition(Request $request, SerializerInterface $serializer): PositionRequestDTO
    {
        $data = $this->normalizeFormTypes($this->rawPayload($request));

        /** @var PositionRequestDTO $dto */
        $dto = $serializer->denormalize($data, PositionRequestDTO::class);

        return $dto;
    }

    /**
     * Classic HTML forms send raw strings: checkboxes arrive as 'on', numbers
     * as '4', absent checkboxes as missing keys. Normalize before denormalizing
     * into the strictly typed DTO.
     */
    private function normalizeFormTypes(array $data): array
    {
        $data['isPublic'] = isset($data['isPublic']) ? filter_var($data['isPublic'], FILTER_VALIDATE_BOOLEAN) : false;
        $data['maxProjects'] = isset($data['maxProjects']) ? (int) $data['maxProjects'] : 4;
        $data['templateAttributes'] ??= [];
        $data['accessRules'] ??= [];
        $data['tags'] ??= [];
        $data['title'] ??= '';
        $data['shortDescription'] ??= '';

        return $data;
    }

    private function validationErrors(ValidatorInterface $validator, PositionRequestDTO $dto): ?string
    {
        $violations = $validator->validate($dto);

        return $violations->count() > 0 ? (string) $violations : null;
    }

    private function wantsJson(Request $request): bool
    {
        return $request->getContentTypeFormat() === 'json' || $request->isXmlHttpRequest();
    }

    private function validationFailure(Request $request, string $errors, string $fallbackRoute, array $params = []): JsonResponse|Response
    {
        if ($this->wantsJson($request)) {
            return new JsonResponse(['success' => false, 'errors' => $errors, 'message' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->addFlash('error', $errors);

        return $this->redirectToRoute($fallbackRoute, $params);
    }

    private function creationResponse(Request $request, Position $position): JsonResponse|Response
    {
        if ($this->wantsJson($request)) {
            return new JsonResponse([
                'success' => true,
                'id' => $position->getId(),
                'redirectUrl' => $this->generateUrl('position_show', ['id' => $position->getId()]),
            ]);
        }

        return $this->redirectToRoute('position_show', ['id' => $position->getId()]);
    }

    #[Route('/positions/{id}/duplicate', name: 'position_duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Position $position): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::DUPLICATE, $position);
        $copy = $this->positionService->duplicatePosition($position, $this->getUser());

        return $this->redirectToRoute('position_show', ['id' => $copy->getId()]);
    }

    #[Route('/positions/{id}/delete', name: 'position_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Position $position): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::DELETE, $position);
        $this->positionService->softDeletePosition($position);

        $this->addFlash('success', 'Position deleted.');

        return $this->redirectToRoute('position_index');
    }

    #[Route('/positions/{id}/cvs', name: 'position_cvs', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function cvs(Position $position, CvRepository $cvRepository): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::VIEW, $position);

        // Recruiters see ONLY published, visible CVs — never candidate drafts.
        $cvs = $cvRepository->findPublishedByPosition($position);

        // Opening the list acknowledges the notifications (badge clears).
        $em = $this->positionRepository->getEntityManager();
        foreach ($cvs as $cv) {
            $cv->markViewedByRecruiter();
        }
        $em->flush();

        return $this->render('position/cvs.html.twig', ['position' => $position, 'cvs' => $cvs]);
    }

    private function mapDto(PositionRequestDTO $dto): PositionDTO
    {
        // Accept both denormalized DTO objects and raw associative arrays
        // (defensive: any client may post templateAttributes as plain arrays).
        $templateAttributes = array_map(
            static fn ($ta): PositionTemplateAttributeDTO => $ta instanceof PositionTemplateAttributeRequestDTO
                ? new PositionTemplateAttributeDTO($ta->attributeId, $ta->isRequired, $ta->sortOrder)
                : new PositionTemplateAttributeDTO((int) ($ta['attributeId'] ?? 0), (bool) ($ta['isRequired'] ?? false), (int) ($ta['sortOrder'] ?? 0)),
            $dto->templateAttributes,
        );

        $accessRules = array_map(
            static fn ($rule): PositionAccessRuleDTO => $rule instanceof PositionAccessRuleRequestDTO
                ? new PositionAccessRuleDTO($rule->attributeId, $rule->operator instanceof AccessRuleOperator ? $rule->operator : AccessRuleOperator::from($rule->operator), $rule->ruleValue)
                : new PositionAccessRuleDTO((int) ($rule['attributeId'] ?? 0), AccessRuleOperator::from((string) ($rule['operator'] ?? '')), (string) ($rule['ruleValue'] ?? '')),
            $dto->accessRules,
        );

        return new PositionDTO(
            title: $dto->title,
            shortDescription: $dto->shortDescription,
            companyName: $dto->companyName,
            level: $dto->level,
            isPublic: $dto->isPublic,
            maxProjects: $dto->maxProjects,
            templateAttributes: $templateAttributes,
            accessRules: $accessRules,
            tags: $dto->tags,
        );
    }
}
