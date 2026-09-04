<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\ProjectCreateRequestDTO;
use App\DTO\Request\ProjectUpdateRequestDTO;
use App\Entity\CandidateProfile;
use App\Entity\Project;
use App\Exception\OptimisticLockConflictException;
use App\Service\Project\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JSON CRUD for candidate projects (profile → Projects tab).
 *
 * Ownership is enforced here: a project may only be read/modified/deleted by
 * its owner. Update carries the optimistic-lock version in the body and maps
 * a stale version to HTTP 409 {error: conflict, serverVersion}.
 */
final class ProjectController extends AbstractController
{
    public function __construct(private readonly ProjectService $projectService)
    {
    }

    #[Route('/api/projects', name: 'project_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_CANDIDATE');

        return $this->json($this->projectService->listProjects($this->profile()));
    }

    #[Route('/api/projects', name: 'project_create', methods: ['POST'])]
    public function create(#[MapRequestPayload] ProjectCreateRequestDTO $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_CANDIDATE');
        $project = $this->projectService->createProject($this->profile(), $dto);

        return $this->json($project, Response::HTTP_CREATED);
    }

    #[Route('/api/projects/{id}', name: 'project_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(Project $project, #[MapRequestPayload] ProjectUpdateRequestDTO $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_CANDIDATE');
        $this->assertOwns($project);

        try {
            $updated = $this->projectService->updateProject($project, $dto);
        } catch (OptimisticLockConflictException $e) {
            return $this->json([
                'error' => 'conflict',
                'serverVersion' => $e->getCurrentVersion(),
            ], Response::HTTP_CONFLICT);
        }

        return $this->json($updated);
    }

    #[Route('/api/projects/{id}', name: 'project_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Project $project): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_CANDIDATE');
        $this->assertOwns($project);

        $this->projectService->deleteProject($project);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function assertOwns(Project $project): void
    {
        if ($project->getProfile()->getUser()->getId() !== $this->getUser()->getId()) {
            throw $this->createAccessDeniedException('You can only modify your own projects.');
        }
    }

    private function profile(): CandidateProfile
    {
        $profile = $this->getUser()->getProfile();
        if ($profile === null) {
            throw $this->createAccessDeniedException('Candidates need a profile to manage projects.');
        }

        return $profile;
    }
}
