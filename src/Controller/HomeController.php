<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PositionRepository;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Home route: public marketing landing for guests,
 * operational dashboard (latest/top positions, CV stats) for logged-in users.
 */
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PositionRepository $positionRepository, CacheItemPoolInterface $cache): Response
    {
        $stats = $cache->get('home.stats.v1', function (ItemInterface $item) use ($positionRepository): array {
            $item->expiresAfter(60);

            return $positionRepository->getStats();
        });

        // Used by both guest landing and authenticated dashboard.
        $tagCloud = $cache->get('home.tag_cloud.v1', function (ItemInterface $item) use ($positionRepository): array {
            $item->expiresAfter(120);

            return $positionRepository->findTagCloud(20);
        });

        // Guest → marketing landing page.
        if (!$this->getUser()) {
            return $this->render('home/landing.html.twig', [
                'stats' => $stats,
                'tagCloud' => $tagCloud,
            ]);
        }

        // Authenticated → existing dashboard.
        return $this->render('home/index.html.twig', [
            'latestPositions' => $positionRepository->findLatest(5),
            'topPositions' => $positionRepository->findTopByCvCount(5),
            'tagCloud' => $tagCloud,
            'stats' => $stats,
        ]);
    }
}