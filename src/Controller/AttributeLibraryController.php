<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\AttributeDTO;
use App\DTO\Request\AttributeRequestDTO;
use App\Entity\Attribute;
use App\Enum\AttributeDataType;
use App\Exception\AttributeAlreadyExistsException;
use App\Exception\AttributeInUseException;
use App\Exception\AttributeTypeChangeForbiddenException;
use App\Exception\CategoryNotFoundException;
use App\Service\Attribute\AttributeLibraryService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Attribute library management (recruiters/admins) + autocomplete API.
 *
 * JSON contract: 201/200 {success,id}, 409 on duplicate name / in-use,
 * 422 on type-freeze or validation failures — never silent 302 for fetch.
 *
 * The controller talks ONLY to the service layer (Controller → Service →
 * Repository); no repository is injected or called here.
 */
final class AttributeLibraryController extends AbstractController
{
    public function __construct(
        private readonly AttributeLibraryService $libraryService,
    ) {
    }

    #[Route('/attributes', name: 'attribute_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('attribute/index.html.twig', [
            'attributes' => $this->libraryService->listAttributes(),
            'categories' => $this->libraryService->listCategories(),
        ]);
    }

    #[Route('/attributes/new', name: 'attribute_new', methods: ['POST'])]
    public function new(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): JsonResponse|Response {
        $this->denyAccessUnlessGranted('ROLE_RECRUITER');

        $dto = $this->denormalize($request, $serializer);
        if ($error = $this->validate($request, $validator, $dto)) {
            return $error;
        }

        try {
            $attribute = $this->libraryService->createAttribute($this->mapDto($dto));
        } catch (CategoryNotFoundException $e) {
            return $this->jsonResponse($request, ['success' => false, 'error' => 'category_not_found', 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY, 'attribute_new');
        } catch (AttributeAlreadyExistsException|UniqueConstraintViolationException $e) {
            return $this->jsonResponse($request, ['success' => false, 'error' => 'name_exists', 'message' => $e->getMessage()], Response::HTTP_CONFLICT, 'attribute_new');
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse($request, ['success' => false, 'error' => 'bad_request', 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST, 'attribute_new');
        }

        return $this->jsonResponse($request, ['success' => true, 'id' => $attribute->getId()], Response::HTTP_CREATED, 'attribute_index');
    }

    #[Route('/attributes/{id}/edit', name: 'attribute_edit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Attribute $attribute,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
    ): JsonResponse|Response {
        $this->denyAccessUnlessGranted('ROLE_RECRUITER');

        $dto = $this->denormalize($request, $serializer);
        if ($error = $this->validate($request, $validator, $dto)) {
            return $error;
        }

        try {
            $this->libraryService->updateAttribute($attribute, $this->mapDto($dto));
        } catch (CategoryNotFoundException $e) {
            return $this->jsonResponse($request, ['success' => false, 'error' => 'category_not_found', 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY, 'attribute_index');
        } catch (AttributeTypeChangeForbiddenException $e) {
            return $this->jsonResponse($request, ['success' => false, 'error' => 'type_change_forbidden', 'message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY, 'attribute_index');
        } catch (AttributeAlreadyExistsException|UniqueConstraintViolationException $e) {
            return $this->jsonResponse($request, ['success' => false, 'error' => 'name_exists', 'message' => $e->getMessage()], Response::HTTP_CONFLICT, 'attribute_index');
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse($request, ['success' => false, 'error' => 'bad_request', 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST, 'attribute_index');
        }

        return $this->jsonResponse($request, ['success' => true, 'id' => $attribute->getId()], Response::HTTP_OK, 'attribute_index');
    }

    #[Route('/attributes/{id}/delete', name: 'attribute_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Attribute $attribute, Request $request): JsonResponse|Response
    {
        $this->denyAccessUnlessGranted('ROLE_RECRUITER');

        try {
            $this->libraryService->deleteAttribute($attribute);
        } catch (AttributeInUseException $e) {
            return $this->jsonResponse($request, ['success' => false, 'error' => 'attribute_in_use', 'message' => $e->getMessage()], Response::HTTP_CONFLICT, 'attribute_index');
        }

        return $this->jsonResponse($request, ['success' => true, 'deleted' => true], Response::HTTP_OK, 'attribute_index');
    }

    /** Full attribute payload for the edit modal. */
    #[Route('/api/attributes/{id}', name: 'attribute_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showJson(Attribute $attribute): JsonResponse
    {
        return $this->json($this->attributePayload($attribute));
    }

    #[Route('/api/attributes/search', name: 'attribute_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $category = $request->query->get('category');

        $attributes = $this->libraryService->searchAttributes(
            (string) $request->query->get('q', ''),
            is_string($category) ? $category : null,
            $user,
            max(1, min(50, (int) $request->query->get('limit', '10'))),
        );

        return $this->json(array_map(fn (Attribute $a): array => $this->attributePayload($a), $attributes));
    }

    // ------------------------------------------------------------ helpers

    private function denormalize(Request $request, SerializerInterface $serializer): AttributeRequestDTO
    {
        $data = $request->getContentTypeFormat() === 'json'
            ? (json_decode((string) $request->getContent(), true) ?? [])
            : $request->request->all();

        $data['options'] ??= [];

        /** @var AttributeRequestDTO $dto */
        $dto = $serializer->denormalize($data, AttributeRequestDTO::class);

        return $dto;
    }

    private function validate(Request $request, ValidatorInterface $validator, AttributeRequestDTO $dto): ?Response
    {
        $violations = $validator->validate($dto);
        if ($violations->count() > 0) {
            return $this->jsonResponse($request, ['success' => false, 'error' => 'validation', 'message' => (string) $violations], Response::HTTP_UNPROCESSABLE_ENTITY, 'attribute_index');
        }

        // ONE_OF_MANY without options would produce an unusable dropdown.
        if ($dto->dataType === AttributeDataType::ONE_OF_MANY->value && $dto->options === []) {
            return $this->jsonResponse($request, ['success' => false, 'error' => 'validation', 'message' => 'ONE_OF_MANY requires at least one option.'], Response::HTTP_UNPROCESSABLE_ENTITY, 'attribute_index');
        }

        return null;
    }

    private function jsonResponse(Request $request, array $payload, int $status, string $fallbackRoute): JsonResponse|Response
    {
        if ($request->getContentTypeFormat() === 'json' || $request->isXmlHttpRequest()) {
            return $this->json($payload, $status);
        }

        if ($status >= 400) {
            $this->addFlash('error', (string) ($payload['message'] ?? 'Operation failed.'));

            return $this->redirectToRoute($fallbackRoute);
        }

        $this->addFlash('success', 'Saved.');

        return $this->redirectToRoute($fallbackRoute);
    }

    private function mapDto(AttributeRequestDTO $dto): AttributeDTO
    {
        $dataType = AttributeDataType::tryFrom($dto->dataType);
        if ($dataType === null) {
            throw new InvalidArgumentException('Invalid attribute data type.');
        }

        // Category existence is verified by the service (CategoryNotFoundException
        // → 422). Resolve here through the service to build the flat DTO fields.
        $category = $this->libraryService->resolveCategoryOrFail($dto->category);

        return new AttributeDTO(
            name: $dto->name,
            categoryId: $category->getId(),
            categoryName: $category->getName(),
            format: $dto->format,
            dataType: $dataType,
            description: $dto->description,
            options: array_values($dto->options),
        );
    }

    /** @return array<string, mixed> */
    private function attributePayload(Attribute $a): array
    {
        return [
            'id' => $a->getId(),
            'name' => $a->getName(),
            'category' => $a->getCategory()->getName(),
            'dataType' => $a->getDataType()->value,
            'format' => $a->getFormat(),
            'description' => $a->getDescription(),
            'options' => array_map(
                static fn ($option): array => ['id' => $option->getId(), 'label' => $option->getLabel()],
                $a->getOptions()->toArray(),
            ),
        ];
    }
}
