<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Attribute;
use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\Cv;
use App\Entity\Discussion;
use App\Entity\Position;
use App\Entity\PositionTemplateAttribute;
use App\Entity\Project;
use App\Entity\Tag;

use App\Enum\AttributeDataType;
use App\Enum\UserRole;
use DateTimeImmutable;

/**
 * Smoke tests: key pages render without errors after the frontend rework.
 */
final class PageRenderTest extends AbstractFunctionalTestCase
{
    public function testHomePageRendersForGuest(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('table.table'); // latest positions table
    }

    public function testPositionsIndexRendersWithToolbar(): void
    {
        $position = $this->createPosition();
        $tag = new Tag('Data Engineer');
        $this->em->persist($tag);
        $position->addTag($tag);
        $this->em->flush();

        $this->client->request('GET', '/positions');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-action-toolbar]');
        self::assertSelectorExists('tr[data-row-id="' . $position->getId() . '"] input[type="checkbox"]');
        // Tags rendered via the N+1-free tag map must appear on the row.
        self::assertSelectorTextContains('tr[data-row-id="' . $position->getId() . '"]', 'Data Engineer');
    }

    public function testSearchSuggestReturnsJsonPositions(): void
    {
        $position = $this->createPosition();
        $position->setTitle('Senior Data Analyst');
        $this->em->flush();

        $this->client->request('GET', '/api/search/suggest?q=data');

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($data);
        self::assertSame('Senior Data Analyst', $data[0]['title']);
    }

    public function testPositionShowRendersDiscussionTab(): void
    {
        $position = $this->createPosition();
        $this->client->request('GET', '/positions/' . $position->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $position->getTitle());
    }

    public function testCvShowRendersInPlaceEditorsForOwner(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $profile = $this->createProfile($candidate);
        $position = $this->createPosition();
        $attr = new Attribute('Level', $this->category('Soft Skills'), AttributeDataType::STRING);
        $this->em->persist($attr);
        $position->getTemplateAttributes()->add(new PositionTemplateAttribute($position, $attr, true, 1));
        $cv = new Cv($candidate, $position);
        $this->em->persist($cv);
        $this->em->flush();

        $this->client->loginUser($candidate);
        $this->client->request('GET', '/cvs/' . $cv->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.inplace-edit[data-attribute-id="' . $attr->getId() . '"]');
        // Required-but-empty fields render with the red "empty" style.
        self::assertSelectorExists('.inplace-edit.cv-empty');
    }

    public function testCvShowRendersSanitizedMarkdownProjects(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $profile = $this->createProfile($candidate);
        $position = $this->createPosition();

        $tag = new Tag('PHP');
        $this->em->persist($tag);
        $position->addTag($tag);

        $project = new Project($profile, 'Atlas', new DateTimeImmutable('2024-01-01'), '**Secure** <script>alert(1)</script>');
        $project->addTag($tag);
        $this->em->persist($project);

        $cv = new Cv($candidate, $position);
        $this->em->persist($cv);
        $this->em->flush();

        // The owner always sees their own CV (also in DRAFT state).
        $this->client->loginUser($candidate);
        $this->client->request('GET', '/cvs/' . $cv->getId());

        self::assertResponseIsSuccessful();
        // XSS payload must be stripped by the Markdown sanitizer.
        self::assertStringNotContainsString('<script>alert(1)</script>', $this->client->getResponse()->getContent());
    }

    public function testProfileShowsFourSectionsForOwner(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $this->createProfile($candidate);

        $this->client->loginUser($candidate);
        $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#tab-me');
        self::assertSelectorExists('#tab-info');
        self::assertSelectorExists('#tab-projects');
        self::assertSelectorExists('#tab-cvs');
    }

    public function testCvShowDraftRendersPublishActionButton(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $this->createProfile($candidate);
        $position = $this->createPosition();
        $cv = new Cv($candidate, $position); // DRAFT
        $this->em->persist($cv);
        $this->em->flush();

        $this->client->loginUser($candidate);
        $this->client->request('GET', '/cvs/' . $cv->getId());

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('id="publish-cv"', $html, 'draft CV must offer the Publish action');
        self::assertStringNotContainsString('id="publish-cv-done"', $html);
        self::assertStringContainsString('data-cv-status="DRAFT"', $html);
    }

    public function testCvShowPublishedRendersStatusIndicatorNotActionButton(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $this->createProfile($candidate);
        $position = $this->createPosition();
        $cv = new Cv($candidate, $position);
        $cv->publish();
        $this->em->persist($cv);
        $this->em->flush();

        $this->client->loginUser($candidate);
        $this->client->request('GET', '/cvs/' . $cv->getId());

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('id="publish-cv-done"', $html, 'published CV must show the status indicator');
        self::assertStringNotContainsString('id="publish-cv"', $html, 'no Publish action button for an already published CV');
        self::assertStringContainsString('data-cv-status="PUBLISHED"', $html);
    }

    public function testProjectsTabRendersEditableRowsWithVersionAndTags(): void
    {
        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $profile = $this->createProfile($candidate);

        $tag = new Tag('Python');
        $this->em->persist($tag);
        $project = new Project($profile, 'ETL Pipeline', new DateTimeImmutable('2024-01-15'), 'desc');
        $project->addTag($tag);
        $this->em->persist($project);
        $this->em->flush();

        $this->client->loginUser($candidate);
        $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        // Editable autosave row carries the optimistic-lock version for PATCH.
        self::assertStringContainsString('data-project-row', $html);
        self::assertStringContainsString('data-project-id="' . $project->getId() . '"', $html);
        self::assertStringContainsString('data-version="' . $project->getVersion() . '"', $html);
        // Tags preloaded in the row input (join fetched without N+1).
        self::assertStringContainsString('data-field="tags" value="Python"', $html);
        self::assertStringContainsString('data-field="name" value="ETL Pipeline"', $html);
    }

    public function testProfileCreatedLazilyForCandidateWithoutProfile(): void
    {
        // A candidate without a profile still gets the real editor (no stub).
        $candidate = $this->createUser('fresh@example.com', UserRole::ROLE_CANDIDATE);
        $this->client->loginUser($candidate);

        $this->client->request('GET', '/profile/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-autosave]');
        self::assertSelectorExists('#tab-projects');
    }

    public function testPositionFormHasPostMethodAndOperators(): void
    {
        $recruiter = $this->createUser('recruiter@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);

        $this->client->request('GET', '/positions/new');
        $html = (string) $this->client->getResponse()->getContent();

        // The form must POST even if JS dies (no silent GET refresh).
        self::assertStringContainsString('method="POST"', $html);
        // The operators array embedded in JS must not be empty/null.
        self::assertStringContainsString('GREATER_THAN_OR_EQUAL', $html);
    }

    public function testPositionFormHasLevelCompanyAndRulesBuilder(): void
    {
        $recruiter = $this->createUser('recruiter@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);

        $this->client->request('GET', '/positions/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="level"] option[value="Junior"]');
        self::assertSelectorExists('input[name="companyName"]');
        self::assertSelectorExists('#rules-list');
        self::assertSelectorExists('#position-tags');

        // Twig must NOT escape the embedded JSON (would crash the JS with
        // "Uncaught SyntaxError: expected expression, got '&'").
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('&quot;', $html);
    }

    public function testRecruiterSeesOnlyPublishedCvsOnPositionList(): void
    {
        $recruiter = $this->createUser('recruiter@example.com', UserRole::ROLE_RECRUITER);
        $draftCandidate = $this->createUser('draft@example.com', UserRole::ROLE_CANDIDATE);
        $this->createProfile($draftCandidate);
        $publishedCandidate = $this->createUser('published@example.com', UserRole::ROLE_CANDIDATE);
        $this->createProfile($publishedCandidate);

        $position = $this->createPosition();
        $position->setPublic(true);
        $draftCv = new Cv($draftCandidate, $position); // DRAFT
        $publishedCv = new Cv($publishedCandidate, $position);
        $publishedCv->publish();
        $this->em->persist($draftCv);
        $this->em->persist($publishedCv);
        $this->em->flush();

        $this->client->loginUser($recruiter);
        $this->client->request('GET', '/positions/' . $position->getId() . '/cvs');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('published@example.com', $html);
        self::assertStringNotContainsString('draft@example.com', $html, 'draft CVs must be hidden from recruiters');
    }

    public function testLoginPageRendersOAuthLinks(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/connect/google"]');
        self::assertSelectorExists('a[href="/connect/github"]');
    }

    public function testGoogleOAuthRedirectUsesRegisteredCallbackPath(): void
    {
        // The callback path must match the "Authorized redirect URIs" entry
        // registered in Google Cloud Console: http://127.0.0.1:8000/oauth/google/
        $this->client->request('GET', '/connect/google');

        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('accounts.google.com', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        self::assertArrayHasKey('redirect_uri', $query);
        self::assertStringContainsString('/oauth/google/', $query['redirect_uri']);
    }

    public function testDiscussionAuthorLinkForRecruiterPointsToPublishedCv(): void
    {
        $candidate = $this->createUser('author@example.com', UserRole::ROLE_CANDIDATE);
        $this->createProfile($candidate);

        $position = $this->createPosition();
        $cv = new Cv($candidate, $position);
        $cv->publish();
        $this->em->persist($cv);
        $discussion = new Discussion($position, $candidate, 'Hello from candidate');
        $this->em->persist($discussion);
        $position->getDiscussions()->add($discussion);
        $this->em->flush();

        $recruiter = $this->createUser('recruiter@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);
        $this->client->request('GET', '/positions/' . $position->getId());

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        // The author name is a link to the candidate's published CV — the
        // recruiter-visible read-only representation (never the 403 profile).
        self::assertStringContainsString('href="/cvs/' . $cv->getId() . '"', $html);
        self::assertStringContainsString('author@example.com', $html);
    }

    public function testDiscussionAuthorLinkForAdminPointsToProfile(): void
    {
        $candidate = $this->createUser('author@example.com', UserRole::ROLE_CANDIDATE);
        $profile = $this->createProfile($candidate);

        $position = $this->createPosition();
        $discussion = new Discussion($position, $candidate, 'Hello');
        $this->em->persist($discussion);
        $position->getDiscussions()->add($discussion);
        $this->em->flush();

        $admin = $this->createUser('admin@example.com', UserRole::ROLE_ADMIN);
        $this->client->loginUser($admin);
        $this->client->request('GET', '/positions/' . $position->getId());

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        // Admins have unlimited access — link to the full profile page.
        self::assertStringContainsString('href="/profile/' . $profile->getId() . '"', $html);
    }

    public function testDiscussionAuthorWithoutPublishedCvStaysPlainTextForRecruiter(): void
    {
        $candidate = $this->createUser('author@example.com', UserRole::ROLE_CANDIDATE);
        $this->createProfile($candidate);

        $position = $this->createPosition();
        // DRAFT cv only — not recruiter-visible.
        $this->em->persist(new Cv($candidate, $position));
        $discussion = new Discussion($position, $candidate, 'Hello');
        $this->em->persist($discussion);
        $position->getDiscussions()->add($discussion);
        $this->em->flush();

        $recruiter = $this->createUser('recruiter@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);
        $this->client->request('GET', '/positions/' . $position->getId());

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('author@example.com', $html);
        self::assertStringNotContainsString('<a href="/cvs/', $html, 'no link without a published CV');
    }
}
