<?php

declare(strict_types=1);

namespace App\Security\OAuth;

use App\Entity\CandidateProfile;
use App\Entity\OAuthIdentity;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * OAuth2 login (Google / GitHub). First-time visitors get a local account
 * linked through an OAuthIdentity row; repeat logins reuse the existing user.
 */
final class OAuthAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === 'connect_google_check'
            || $request->attributes->get('_route') === 'connect_github_check';
    }

    public function authenticate(Request $request): Passport
    {
        $provider = $request->attributes->get('_route') === 'connect_google_check' ? 'google' : 'github';
        $client = $this->clientRegistry->getClient($provider);
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), fn (): User => $this->findOrCreateUser($provider, $client->fetchUserFromToken($accessToken))),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse('/');
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()?->getFlashBag()->add('error', 'OAuth login failed: ' . $exception->getMessage());

        return new RedirectResponse('/login');
    }

    private function findOrCreateUser(string $provider, ResourceOwnerInterface $resourceOwner): User
    {
        $providerUserId = (string) $resourceOwner->getId();

        $identity = $this->em->getRepository(OAuthIdentity::class)->findOneBy([
            'provider' => $provider,
            'providerUserId' => $providerUserId,
        ]);
        if ($identity !== null) {
            return $this->ensureCandidateProfile($identity->getUser(), $resourceOwner);
        }

        $email = $resourceOwner->getEmail();

        // Idempotent, race-safe creation: if a parallel request created the
        // user/identity between our lookup and flush, the unique constraint
        // violation is caught and the existing rows are reused instead of
        // surfacing a 500 to the visitor (e.g. second Google account login).
        try {
            $user = $email !== null
                ? $this->em->getRepository(User::class)->findOneBy(['email' => $email])
                : null;

            if ($user === null) {
                // No email on the provider: synthesize a stable local identifier.
                $user = new User($email ?? sprintf('%s-%s@oauth.local', $provider, $providerUserId), [UserRole::ROLE_CANDIDATE->value]);
                $this->em->persist($user);
            }

            $this->em->persist(new OAuthIdentity($user, $provider, $providerUserId));
            $this->em->flush();

            return $this->ensureCandidateProfile($user, $resourceOwner);
        } catch (UniqueConstraintViolationException) {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }
            $this->em->clear();

            $identity = $this->em->getRepository(OAuthIdentity::class)->findOneBy([
                'provider' => $provider,
                'providerUserId' => $providerUserId,
            ]);
            if ($identity !== null) {
                return $this->ensureCandidateProfile($identity->getUser(), $resourceOwner);
            }

            // Another user with this email won the race — attach to them.
            $existing = $email !== null
                ? $this->em->getRepository(User::class)->findOneBy(['email' => $email])
                : null;
            if ($existing !== null) {
                $this->em->persist(new OAuthIdentity($existing, $provider, $providerUserId));
                $this->em->flush();

                return $this->ensureCandidateProfile($existing, $resourceOwner);
            }

            throw new \LogicException('OAuth identity could not be stored; please try again.');
        }
    }

    /**
     * Candidates land on the profile editor right after the first login, so a
     * CandidateProfile must exist (the profile page is not a placeholder).
     */
    private function ensureCandidateProfile(User $user, ResourceOwnerInterface $resourceOwner): User
    {
        if (!$user->hasRole(UserRole::ROLE_CANDIDATE) || $user->getProfile() !== null) {
            return $user;
        }

        [$firstName, $lastName] = $this->splitName($resourceOwner->toArray()['name'] ?? null, $user->getEmail());

        $profile = new CandidateProfile($user, $firstName, $lastName);
        $this->em->persist($profile);
        $this->em->flush();

        return $user;
    }

    /** @return array{string, string} */
    private function splitName(?string $fullName, string $email): array
    {
        if ($fullName !== null && trim($fullName) !== '') {
            $parts = preg_split('/\s+/', trim($fullName), 2);
            if ($parts !== false && $parts[0] !== '') {
                return [$parts[0], $parts[1] ?? ''];
            }
        }

        // Fallback: local part of the email (e.g. jane.doe → Jane Doe).
        $local = str_replace(['.', '_', '-'], ' ', (string) preg_split('/@/', $email, 2)[0]);
        $parts = preg_split('/\s+/', trim($local), 2);

        return [$parts[0] ?? 'Candidate', ucfirst($parts[1] ?? '')];
    }
}
