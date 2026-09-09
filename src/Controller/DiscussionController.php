<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Request\DiscussionMessageRequestDTO;
use App\Entity\Position;
use App\Service\Discussion\DiscussionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** Discussion thread of a position; fan-out via Mercure happens in the service. */
final class DiscussionController extends AbstractController
{
    #[Route('/positions/{id}/discussions', name: 'discussion_post', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function post(
        Position $position,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        DiscussionService $discussionService,
    ): JsonResponse|Response {
        // The HTML form posts urlencoded data; JSON clients are supported too.
        $data = $request->getContentTypeFormat() === 'json'
            ? (json_decode((string) $request->getContent(), true) ?? [])
            : $request->request->all();

        /** @var DiscussionMessageRequestDTO $dto */
        $dto = $serializer->denormalize($data, DiscussionMessageRequestDTO::class);
        $violations = $validator->validate($dto);

        if (count($violations) > 0) {
            if ($request->getContentTypeFormat() === 'json' || $request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'errors' => (string) $violations], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->addFlash('error', (string) $violations);

            return $this->redirectToRoute('position_show', ['id' => $position->getId(), '_fragment' => 'tab-discussion']);
        }

        $discussion = $discussionService->postMessage($position, $this->getUser(), $dto->message);
        if ($request->getContentTypeFormat() === 'json' || $request->isXmlHttpRequest() || $request->headers->contains('Accept', 'application/json')) {
            return $this->json([
                'success' => true,
                'id' => $discussion->getId(),
                'author' => $discussion->getAuthor()->getEmail(),
                'createdAt' => $discussion->getCreatedAt()->format('Y-m-d H:i'),
                'messageMd' => $discussion->getMessageMd(),
            ], Response::HTTP_CREATED);
        }
        $this->addFlash('success', 'Message posted.');

        // Keep the visitor on the discussion tab after the redirect.
        return $this->redirectToRoute('position_show', ['id' => $position->getId(), '_fragment' => 'tab-discussion']);
    }
}
