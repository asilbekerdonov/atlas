<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Admin panel: user directory with block/unblock. */
final class AdminController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/admin/users', name: 'admin_users', methods: ['GET'])]
    public function users(PositionRepository $positionRepository): Response
    {
        $users = $this->em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/users.html.twig', ['users' => $users]);
    }

    #[Route('/admin/users/{id}/block', name: 'admin_user_block', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleBlock(User $user): RedirectResponse
    {
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot block yourself.');
        } elseif ($user->isBlocked()) {
            $user->unblock();
        } else {
            $user->block();
        }

        $this->em->flush();

        return $this->redirectToRoute('admin_users');
    }
}
