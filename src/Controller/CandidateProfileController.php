<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ProfileAttributeValueDTO;
use App\DTO\ProfileAutosaveDTO;
use App\DTO\Request\ProfileAutosaveRequestDTO;
use App\Entity\CandidateProfile;
use App\Enum\UserRole;
use App\Exception\OptimisticLockConflictException;
use App\Security\Voter\ProfileVoter;
use App\Service\Profile\ProfileAutosaveService;
use App\Service\Project\ProjectService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Candidate profile: own profile page + autosave API with optimistic locking.
 */
final class CandidateProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProjectService $projectService,
    ) {
    }

    #[Route('/profile', name: 'profile_show_own', methods: ['GET'])]
    public function showOwn(): Response
    {
        $profile = $this->profileForEditor();

        return $this->render('profile/show.html.twig', $this->templateVars($profile));
    }

    #[Route('/profile/edit', name: 'profile_edit', methods: ['GET'])]
    public function editOwn(): Response
    {
        $profile = $this->profileForEditor();

        return $this->render('profile/show.html.twig', $this->templateVars($profile));
    }

    /**
     * View DTOs for the Projects tab: the tags arrive via one LEFT JOIN (no
     * N+1 while rendering profile.projects).
     *
     * @return array{profile: CandidateProfile, projectViews: list<\App\DTO\ProjectViewDTO>}
     */
    private function templateVars(CandidateProfile $profile): array
    {
        return [
            'profile' => $profile,
            'projectViews' => $this->projectService->listProjects($profile),
            // Browser key for the "pick location on a map" modal; injected
            // into the page only, the script itself loads lazily.
            'yandexMapsApiKey' => $this->getParameter('yandex_maps_js_api_key'),
        ];
    }

    /**
     * The profile editor needs a non-null CandidateProfile. Candidates get one
     * lazily (OAuth logins create it eagerly); non-candidates have nothing to
     * edit here.
     */
    private function profileForEditor(): CandidateProfile
    {
        $user = $this->getUser();

        if (!$user->hasRole(UserRole::ROLE_CANDIDATE)) {
            throw $this->createAccessDeniedException('Only candidates have a profile to edit.');
        }

        $profile = $user->getProfile();
        if ($profile === null) {
            $profile = new CandidateProfile($user, 'Candidate', '');
            $this->em->persist($profile);
            $this->em->flush();
        }

        return $profile;
    }

    #[Route('/profile/{id}', name: 'profile_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(CandidateProfile $profile): Response
    {
        $this->denyAccessUnlessGranted(ProfileVoter::VIEW, $profile);

        return $this->render('profile/show.html.twig', $this->templateVars($profile));
    }

    #[Route('/api/profile/autosave', name: 'profile_autosave', methods: ['PATCH'])]
    public function autosave(
        Request $request,
        #[MapRequestPayload] ProfileAutosaveRequestDTO $dto,
        ProfileAutosaveService $autosaveService,
        #[Autowire(service: 'limiter.profile_autosave')] RateLimiterFactory $autosaveLimiter,
    ): JsonResponse {
        
        $limiter = $autosaveLimiter->create($this->getUser()->getUserIdentifier())->consume(1);
        if (!$limiter->isAccepted()) {
            throw new TooManyRequestsHttpException((int) $limiter->getRetryAfter()->getTimestamp() - time());
        }

        try {
            $result = $autosaveService->autosaveProfile($this->getUser(), new ProfileAutosaveDTO(
                firstName: $dto->firstName,
                lastName: $dto->lastName,
                location: $dto->location,
                avatarUrl: $dto->avatarUrl,
                expectedVersion: $dto->expectedVersion,
                attributeValues: array_map(
                    static fn ($v): ProfileAttributeValueDTO => new ProfileAttributeValueDTO($v->attributeId, $v->value),
                    $dto->attributeValues,
                ),
            ));
        } catch (OptimisticLockConflictException $e) {
            $profile = $e->getEntity();
            if (!$profile instanceof CandidateProfile) {
                throw $e;
            }
            return new JsonResponse([
                'error' => 'conflict',
                'serverVersion' => $e->getCurrentVersion(),
                'currentData' => [
                    'firstName' => $profile->getFirstName(),
                    'lastName' => $profile->getLastName(),
                    'location' => $profile->getLocation(),
                    'avatarUrl' => $profile->getAvatarUrl(),
                ],
            ], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(['success' => true, 'newVersion' => $result->newVersion]);
    }

    /**
     * Removes ONE attribute value from the candidate's own profile ("−" button
     * in the Info tab). Published CVs using the attribute are reverted to
     * DRAFT server-side; the response lists them so the UI can inform the
     * candidate.
     */
    #[Route('/api/profile/attribute-value/{id}', name: 'profile_attribute_value_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteAttributeValue(
        int $id,
        ProfileAutosaveService $autosaveService,
    ): JsonResponse {
        $result = $autosaveService->deleteAttributeValue($this->getUser(), $id);

        return new JsonResponse([
            'success' => true,
            'newVersion' => $result->newVersion,
            'unpublishedCvs' => $result->unpublishedCvs,
        ]);
    }

    /**
     * Server-side reverse geocoding for the location picker modal.
     *
     * The frontend sends a placemark's {lat, lng} after a drag and gets back
     * a human-readable address. The Geocoder API key lives only on the server
     * (never shipped to the browser), and lookups fire on dragend — not on
     * every pixel — to stay inside the free daily quota.
     */
    #[Route('/api/profile/geocode', name: 'profile_geocode', methods: ['POST'])]
    public function reverseGeocode(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        $lat = isset($payload['lat']) ? (float) $payload['lat'] : 0.0;
        $lng = isset($payload['lng']) ? (float) $payload['lng'] : 0.0;

        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return new JsonResponse(['error' => 'invalid_coordinates'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $apiKey = $this->getParameter('yandex_geocoder_api_key');
        if ($apiKey === '') {
            return new JsonResponse(['error' => 'geocoder_not_configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        try {
            // Yandex Geocoder HTTP API (current docs): /v1, coordinates as
            // "longitude,latitude", lang is required.
            $response = $httpClient->request('GET', 'https://geocode-maps.yandex.ru/v1', [
                'query' => [
                    'format' => 'json',
                    'apikey' => $apiKey,
                    'geocode' => sprintf('%.6f,%.6f', $lng, $lat),
                    'lang' => 'ru_RU',
                    'results' => '1',
                ],
                'timeout' => 5,
            ]);
            $data = $response->toArray();
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'geocoder_request_failed'], Response::HTTP_BAD_GATEWAY);
        }

        $feature = $data['response']['GeoObjectCollection']['featureMember'][0]['GeoObject'] ?? null;
        if ($feature === null) {
            return new JsonResponse(['error' => 'address_not_found'], Response::HTTP_NOT_FOUND);
        }

        $address = $feature['metaDataProperty']['GeocoderMetaData']['text']
            ?? $feature['name']
            ?? null;

        if ($address === null || $address === '') {
            return new JsonResponse(['error' => 'address_not_found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['success' => true, 'address' => $address]);
    }
}
