<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Tagify autocomplete: fast prefix search over tags. */
final class TagAutocompleteController extends AbstractController
{
    public function __construct(private readonly TagRepository $tagRepository)
    {
    }

    #[Route('/api/tags', name: 'tag_autocomplete', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $tags = $this->tagRepository->findByPrefix((string) $request->query->get('q', ''), 10);

        return new JsonResponse(array_map(
            static fn ($tag): array => ['id' => $tag->getId(), 'name' => $tag->getName()],
            $tags,
        ));
    }
}
