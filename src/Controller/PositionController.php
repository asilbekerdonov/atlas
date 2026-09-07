<?php

declare(strict_types=1);

namespace App\Controller;
use App\Entity\Position;
use App\DTO\Request\PositionRequestDTO;
use App\Enum\AccessRuleOperator;
use App\Enum\Format;
use App\Exception\OptimisticLockConflictException;
use App\Factory\PositionResponseFactory;
use App\Mapper\PositionRequestMapper;
use App\Repository\AttributeRepository;
use App\Repository\PositionRepository;
use App\Security\Voter\PositionVoter;
use App\Service\Position\PositionService;
use App\Service\Position\PositionViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class PositionController extends AbstractController
{
    public function __construct(
        private readonly PositionRepository $positionRepository,
        private readonly PositionService $positionService,
        private readonly PositionViewService $positionViewService,
        private readonly PositionRequestMapper $requestMapper,
        private readonly PositionResponseFactory $responseFactory,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/positions', name: 'position_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', '1'));
        $result = $this->positionViewService->getPositionsForIndex(
            $this->getUser(),
            $page,
            20
        );

        $positionIds = array_map(
            static fn (Position $p): int => $p->getId(),
            $result['items']
        );

        return $this->render('position/index.html.twig', [
            'positions' => $result['items'],
            'total' => $result['total'],
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
        $hasAppliedCv = $user !== null
            ? $this->positionViewService->hasAppliedCv($user, $position)
            : false;

        $discussions = $position->getDiscussions();
        $authorIds = array_keys(
            array_combine(
                array_map(fn ($d) => $d->getAuthor()->getId(), $discussions),
                array_fill(0, count($discussions), true)
            )
        );

        $publishedCvIds = $this->positionViewService->getPublishedCvIdByAuthorIds($authorIds);

        return $this->render('position/show.html.twig', [
            'position' => $position,
            'discussions' => $discussions,
            'hasAppliedCv' => $hasAppliedCv,
            'publishedCvIdByAuthorId' => $publishedCvIds,
        ]);
    }

    #[Route('/positions/new', name: 'position_new', methods: ['GET'])]
    public function newForm(AttributeRepository $attributeRepository): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::CREATE);

        return $this->render('position/form.html.twig', [
            'position' => null,
            'formats' => Format::cases(),
            'attributes' => $attributeRepository->findBy([], ['name' => 'ASC']),
            'operators' => AccessRuleOperator::cases(),
        ]);
    }

    #[Route('/positions/new', name: 'position_new_post', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::CREATE);

        $dto = $this->requestMapper->fromRequest($request);
        $errors = $this->validate($dto);
        if ($errors !== null) {
            return $this->responseFactory->validationFailureResponse(
                $this->wantsJson($request),
                $errors,
                'position_new'
            );
        }

        $position = $this->positionService->createPosition(
            $this->requestMapper->toPositionDTO($dto),
            $this->getUser()
        );

        return $this->responseFactory->creationResponse(
            $this->wantsJson($request),
            $position
        );
    }

    #[Route('/positions/{id}/edit', name: 'position_edit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function editForm(Position $position, AttributeRepository $attributeRepository): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::EDIT, $position);

        return $this->render('position/form.html.twig', [
            'position' => $position,
            'formats' => Format::cases(),
            'attributes' => $attributeRepository->findBy([], ['name' => 'ASC']),
            'operators' => AccessRuleOperator::cases(),
        ]);
    }

    #[Route('/positions/{id}/edit', name: 'position_edit_post', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(Position $position, Request $request): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::EDIT, $position);

        $dto = $this->requestMapper->fromRequest($request);
        $errors = $this->validate($dto);
        if ($errors !== null) {
            return $this->responseFactory->validationFailureResponse(
                $this->wantsJson($request),
                $errors,
                'position_edit',
                ['id' => $position->getId()]
            );
        }

        try {
            $this->positionService->updatePosition(
                $position,
                $this->requestMapper->toPositionDTO($dto),
                $this->getExpectedVersion($request, $position)
            );
        } catch (OptimisticLockConflictException $e) {
            return $this->responseFactory->conflictResponse(
                $this->wantsJson($request),
                $e->getCurrentVersion(),
                $position
            );
        }

        return $this->responseFactory->creationResponse(
            $this->wantsJson($request),
            $position
        );
    }

    #[Route('/positions/bulk-delete', name: 'position_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::DELETE);

        $raw = $this->getRawPayload($request);
        $ids = $raw['ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return $this->errorResponse($request, 'No positions selected for deletion.');
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
            return $this->json([
                'success' => true,
                'deletedCount' => $deletedCount,
                'message' => sprintf('%d position(s) deleted.', $deletedCount),
            ]);
        }

        $this->addFlash('success', sprintf('%d position(s) deleted.', $deletedCount));
        return $this->redirectToRoute('position_index');
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
    public function cvs(Position $position): Response
    {
        $this->denyAccessUnlessGranted(PositionVoter::VIEW, $position);

        $cvs = $this->positionRepository->getEntityManager()
            ->getRepository(Cv::class)
            ->findPublishedByPosition($position);

        foreach ($cvs as $cv) {
            $cv->markViewedByRecruiter();
        }
        $this->positionRepository->getEntityManager()->flush();

        return $this->render('position/cvs.html.twig', [
            'position' => $position,
            'cvs' => $cvs,
        ]);
    }

    // ===== PRIVATE HELPER METHODS =====

    private function validate(PositionRequestDTO $dto): ?string
    {
        $violations = $this->validator->validate($dto);
        return $violations->count() > 0 ? (string) $violations : null;
    }

    private function wantsJson(Request $request): bool
    {
        return $request->getContentTypeFormat() === 'json' || $request->isXmlHttpRequest();
    }

    private function getRawPayload(Request $request): array
    {
        return $request->getContentTypeFormat() === 'json'
            ? (json_decode((string) $request->getContent(), true) ?? [])
            : $request->request->all();
    }

    private function getExpectedVersion(Request $request, Position $position): int
    {
        $raw = $this->getRawPayload($request);
        return isset($raw['version'])
            ? (int) $raw['version']
            : (int) $request->query->get('version', (string) $position->getVersion());
    }

    private function errorResponse(Request $request, string $message): Response
    {
        if ($this->wantsJson($request)) {
            return $this->json([
                'success' => false,
                'message' => $message,
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->addFlash('error', $message);
        return $this->redirectToRoute('position_index');
    }
}