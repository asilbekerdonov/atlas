<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Enum\UserRole;

/**
 * Navbar rules:
 * - "My profile" only for REAL candidates (raw ROLE_CANDIDATE) — role
 *   hierarchy grants ROLE_CANDIDATE to admins, who have no profile and
 *   would hit 403 on /profile.
 * - "Users" (admin panel) only for admins.
 */
final class NavbarTest extends AbstractFunctionalTestCase
{
    private function fetchHome(string $email, UserRole $role): string
    {
        $user = $this->createUser($email, $role);
        $this->client->loginUser($user);
        $this->client->request('GET', '/');

        return (string) $this->client->getResponse()->getContent();
    }

    public function testCandidateSeesMyProfileLink(): void
    {
        $html = $this->fetchHome('candidate@example.com', UserRole::ROLE_CANDIDATE);

        self::assertStringContainsString('href="/profile"', $html);
    }

    public function testRecruiterDoesNotSeeMyProfileLink(): void
    {
        $html = $this->fetchHome('recruiter@example.com', UserRole::ROLE_RECRUITER);

        self::assertStringNotContainsString('href="/profile"', $html, 'recruiters have no own candidate profile');
    }

    public function testAdminDoesNotSeeMyProfileLinkButSeesUsersLink(): void
    {
        $html = $this->fetchHome('admin@platform.local', UserRole::ROLE_ADMIN);

        // Role hierarchy grants ROLE_CANDIDATE to admins — the guard must NOT
        // rely on is_granted(), otherwise the item appears and 403s on click.
        self::assertStringNotContainsString('href="/profile"', $html, 'admin has no candidate profile — item must stay hidden');
        self::assertStringContainsString('href="/admin/users"', $html, 'admin panel link must be visible for admins');
    }
}
