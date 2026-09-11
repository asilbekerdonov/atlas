<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exception\UserBlockException;
use App\Exception\UserRoleChangeException;
use App\Repository\UserRepository;
use App\Service\Admin\UserBlockService;
use App\Service\Admin\UserRoleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin/users', name: 'admin_users', methods: ['GET'])]
    public function users(UserRepository $userRepository): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $userRepository->findAllOrderedByCreatedAtDesc(),
        ]);
    }

    #[Route('/admin/users/{id}/block', name: 'admin_user_block', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleBlock(User $user, UserBlockService $userBlockService): RedirectResponse
    {
        try {
            $userBlockService->toggle($user, $this->getUser());
            $this->addFlash('success', $user->isBlocked()
                ? 'User blocked.'
                : 'User unblocked.');
        } catch (UserBlockException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/users/{id}/promote', name: 'admin_user_promote', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function promote(User $user, UserRoleService $userRoleService): RedirectResponse
    {
        try {
            $userRoleService->promote($user);
            $this->addFlash('success', 'User promoted to recruiter.');
        } catch (UserRoleChangeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/users/{id}/demote', name: 'admin_user_demote', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function demote(User $user, UserRoleService $userRoleService): RedirectResponse
    {
        try {
            $userRoleService->demote($user);
            $this->addFlash('success', 'User demoted to candidate.');
        } catch (UserRoleChangeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/admin/users/{id}/revoke-admin', name: 'admin_user_revoke_admin', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function revokeAdmin(User $user, UserRoleService $userRoleService): RedirectResponse
    {
        try {
            $userRoleService->revokeAdmin($user, $this->getUser());
            $this->addFlash('success', 'Administrator role removed.');
        } catch (UserRoleChangeException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_users');
    }
}
