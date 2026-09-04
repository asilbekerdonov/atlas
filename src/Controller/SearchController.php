<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Position;
use App\Repository\CvRepository;
use App\Repository\PositionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Global search (public) with rate limiting by user/IP. Positions use the
 * PostgreSQL FTS index; CV search covers published CVs by candidate name.
 */
final class SearchController extends AbstractController
{
    #[Route('/search', name: 'search_index', methods: ['GET'])]
    public function index(
        Request $request,
        PositionRepository $positionRepository,
        CvRepository $cvRepository,
        #[Autowire(service: 'limiter.search')] RateLimiterFactory $searchLimiter,
    ): Response {
        $this->consume($request, $searchLimiter);

        $q = trim((string) $request->query->get('q', ''));

        return $this->render('search/index.html.twig', [
            'q' => $q,
            'positions' => $q !== '' ? $positionRepository->ftsSearch($q, 20) : [],
            'cvs' => $q !== '' ? $cvRepository->search($q, 20) : [],
        ]);
    }

    /** Live autocomplete for the header search box (max 8 hits). */
    #[Route('/api/search/suggest', name: 'search_suggest', methods: ['GET'])]
    public function suggest(
        Request $request,
        PositionRepository $positionRepository,
        #[Autowire(service: 'limiter.search')] RateLimiterFactory $searchLimiter,
    ): JsonResponse {
        $this->consume($request, $searchLimiter);

        $q = trim((string) $request->query->get('q', ''));
        if (mb_strlen($q) < 2) {
            return $this->json([]);
        }

        return $this->json(array_map(
            static fn (Position $p): array => [
                'id' => $p->getId(),
                'title' => $p->getTitle(),
                'company' => $p->getCompanyName(),
            ],
            $positionRepository->ftsSearch($q, 8),
        ));
    }

    private function consume(Request $request, RateLimiterFactory $limiterFactory): void
    {
        $key = $this->getUser()?->getUserIdentifier() ?? (string) $request->getClientIp();
        $limiter = $limiterFactory->create($key)->consume(1);
        if (!$limiter->isAccepted()) {
            throw new TooManyRequestsHttpException((int) $limiter->getRetryAfter()->getTimestamp() - time());
        }
    }
}
