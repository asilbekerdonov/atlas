<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\CvInPlaceAttributeRequestDTO;
use App\Entity\Cv;
use App\Entity\Position;
use App\Exception\CvIncompleteException;
use App\Exception\OptimisticLockConflictException;
use App\Exception\PositionAccessDeniedException;
use App\Security\Voter\CvVoter;
use App\Service\Cv\CvGenerationService;
use App\Service\Social\CvLikeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * CV flow: apply to a position, view the assembled CV, edit attributes
 * in-place, publish, and like.
 */
final class CvController extends AbstractController
{
    public function __construct(
        private readonly CvGenerationService $cvGenerationService,
        private readonly CvLikeService $cvLikeService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/positions/{positionId}/apply', name: 'cv_apply', methods: ['GET'], requirements: ['positionId' => '\d+'])]
    public function apply(int $positionId): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CANDIDATE');
        $position = $this->em->find(Position::class, $positionId);
        if ($position === null) {
            throw $this->createNotFoundException('Position not found.');
        }

        try {
            $cv = $this->cvGenerationService->getOrCreateCv($this->getUser(), $position);
        } catch (PositionAccessDeniedException $e) {
            throw $this->createAccessDeniedException($e->getMessage(), $e);
        }

        return $this->redirectToRoute('cv_show', ['id' => $cv->getId()]);
    }

    #[Route('/cvs/{id}', name: 'cv_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Cv $cv): Response
    {
        $this->denyAccessUnlessGranted(CvVoter::VIEW, $cv);

        // Recruiter (or admin via the hierarchy) opening the CV acknowledges
        // the "new CV" notification.
        if ($this->isGranted('ROLE_RECRUITER')) {
            $cv->markViewedByRecruiter();
            $this->em->flush();
        }

        return $this->render('cv/show.html.twig', [
            'cv' => $cv,
            'view' => $this->cvGenerationService->assembleCvView($cv),
        ]);
    }

    #[Route('/api/cv/{id}/attribute', name: 'cv_attribute', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateAttribute(Cv $cv, #[MapRequestPayload] CvInPlaceAttributeRequestDTO $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(CvVoter::EDIT, $cv);

        try {
            $value = $this->cvGenerationService->updateCvAttributeInPlace(
                $cv->getCandidate(),
                $dto->attributeId,
                $dto->value,
                $dto->version,
            );
        } catch (OptimisticLockConflictException $e) {
            return new JsonResponse(['error' => 'conflict', 'serverVersion' => $e->getCurrentVersion()], Response::HTTP_CONFLICT);
        }

        return new JsonResponse([
            'attributeId' => $value->getAttribute()->getId(),
            'version' => $value->getVersion(),
            'isEmpty' => $value->isEmpty(),
            'rawValue' => $this->cvGenerationService->rawValueOf($value),
            'displayValue' => $this->cvGenerationService->displayValueOf($value),
        ]);
    }

    #[Route('/api/cv/{id}/publish', name: 'cv_publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function publish(Cv $cv): JsonResponse
    {
        $this->denyAccessUnlessGranted(CvVoter::PUBLISH, $cv);

        try {
            $this->cvGenerationService->publishCv($cv);
        } catch (CvIncompleteException $e) {
            return new JsonResponse([
                'error' => 'incomplete',
                'missingAttributes' => array_map(
                    static fn ($attribute): array => ['id' => $attribute->getId(), 'name' => $attribute->getName()],
                    $e->getMissingAttributes(),
                ),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['status' => $cv->getStatus()->value]);
    }

    #[Route('/api/cv/{id}/like', name: 'cv_like', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function like(Cv $cv): JsonResponse
    {
        $this->denyAccessUnlessGranted(CvVoter::LIKE, $cv);
        $liked = $this->cvLikeService->toggleLike($cv, $this->getUser());

        return new JsonResponse(['likesCount' => $cv->getLikesCount(), 'liked' => $liked]);
    }
}
