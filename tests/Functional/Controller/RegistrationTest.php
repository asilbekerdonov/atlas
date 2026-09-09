<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;

/**
 * Email registration with a signed, expiring verification link:
 * register → email contains a real signed URL → following it verifies the
 * account (is_blocked = false). Expired and replayed links fail gracefully.
 */
final class RegistrationTest extends AbstractFunctionalTestCase
{
    use MailerAssertionsTrait;

    public function testRegisterVerifyAndLogin(): void
    {
        // 1. Registration form renders.
        $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/register"] input[name="email"]');

        // 2. Submit valid data → user created blocked + email sent.
        $this->client->request('POST', '/register', [
            'email' => 'newbie@example.com',
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
        ]);
        self::assertResponseRedirects('/login');

        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'newbie@example.com']);
        self::assertNotNull($user);
        self::assertTrue($user->isBlocked(), 'fresh account must be blocked until email is verified');

        // 3. The email body contains a real signed link (not a placeholder).
        self::assertEmailCount(1);
        $message = self::getMailerMessage(0);
        self::assertNotNull($message);
        self::assertSame('newbie@example.com', $message->getTo()[0]->getAddress());
        $textBody = $message->getTextBody();
        self::assertStringContainsString('http://localhost/register/verify/' . $user->getId(), $textBody);
        self::assertStringContainsString('_expiration=', $textBody, 'link must carry an expiry');
        self::assertStringContainsString('_hash=', $textBody, 'link must be signed');
        self::assertStringContainsString('Confirm my email', $message->getHtmlBody());
        self::assertStringContainsString('ATLAS', $message->getHtmlBody());

        // 4. Following the signed link from the email verifies the account.
        $verifyUrl = $this->extractVerifyUrl($textBody);
        $this->client->request('GET', $verifyUrl);
        self::assertResponseRedirects('/login');

        $this->em->clear();
        $verified = $this->em->getRepository(User::class)->findOneBy(['email' => 'newbie@example.com']);
        self::assertFalse($verified->isBlocked(), 'verification must unblock the account');

        // 5. The verified user can log in.
        $this->client->loginUser($verified);
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
    }

    public function testReusingConsumedLinkDoesNotBreak(): void
    {
        $user = $this->registerPendingUser('replay@example.com');
        $message = self::getMailerMessage(0);
        $url = $this->extractVerifyUrl($message->getTextBody());

        // First click verifies…
        $this->client->request('GET', $url);
        self::assertResponseRedirects('/login');

        // …a second click on the same (still unexpired) link is a friendly no-op.
        $this->client->request('GET', $url);
        self::assertResponseRedirects('/login');

        $this->em->clear();
        $fresh = $this->em->getRepository(User::class)->find($user->getId());
        self::assertFalse($fresh->isBlocked());
    }

    public function testExpiredLinkShowsErrorPage(): void
    {
        $user = $this->registerPendingUser('expired@example.com');

        // Re-sign the same route with a timestamp in the past.
        $signer = self::getContainer()->get('uri_signer');
        $expiredUrl = $signer->sign(
            'http://localhost/register/verify/' . $user->getId(),
            time() - 10,
        );

        $this->client->request('GET', $expiredUrl);
        self::assertResponseStatusCodeSame(410);
        self::assertSelectorTextContains('h1', 'Link expired');
        self::assertSelectorExists('form[action="/register/resend"]');

        // Account stays blocked.
        $this->em->clear();
        $fresh = $this->em->getRepository(User::class)->find($user->getId());
        self::assertTrue($fresh->isBlocked());
    }

    public function testRegisterRejectsDuplicateEmailAndMismatchedPasswords(): void
    {
        $existing = $this->createUser('taken@example.com', UserRole::ROLE_CANDIDATE);

        $this->client->request('POST', '/register', [
            'email' => $existing->getEmail(),
            'password' => 'secret123',
            'confirmPassword' => 'different',
        ]);

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Passwords do not match.', $html);
        self::assertStringContainsString('An account with this email already exists.', $html);
    }

    /** Registers a fresh user via the real HTTP form and returns it. */
    private function registerPendingUser(string $email): User
    {
        $this->client->request('POST', '/register', [
            'email' => $email,
            'password' => 'secret123',
            'confirmPassword' => 'secret123',
        ]);
        self::assertResponseRedirects('/login');

        $this->em->clear();

        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    private function extractVerifyUrl(string $textBody): string
    {
        // Body is "Confirm your ATLAS account: <url>".
        return trim(substr($textBody, (int) strpos($textBody, 'http')));
    }
}
