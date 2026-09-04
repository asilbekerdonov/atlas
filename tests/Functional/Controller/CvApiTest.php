<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Attribute;
use App\Entity\AttributeOption;
use App\Entity\CandidateAttributeValue;
use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\PositionTemplateAttribute;
use App\Enum\AccessRuleOperator;

use App\Enum\AttributeDataType;
use App\Enum\UserRole;

final class CvApiTest extends AbstractFunctionalTestCase
{
    private Position $position;
    private Cv $cv;

    protected function setUp(): void
    {
        parent::setUp();

        $candidate = $this->createUser('candidate@example.com', UserRole::ROLE_CANDIDATE);
        $profile = $this->createProfile($candidate);

        $years = new Attribute('Years of experience', $this->category('Domain Knowledge'), AttributeDataType::NUMERIC);
        $english = new Attribute('English', $this->category('Soft Skills'), AttributeDataType::STRING);
        $this->em->persist($years);
        $this->em->persist($english);

        $value = new CandidateAttributeValue($profile, $years);
        $value->setValueNumeric('5');
        $profile->getAttributeValues()->add($value);
        $this->em->persist($value);

        $this->position = new Position('Senior Backend', 'PHP + PostgreSQL');
        $this->position->setPublic(false);
        $this->position->getAccessRules()->add(new PositionAccessRule($this->position, $years, AccessRuleOperator::GREATER_THAN_OR_EQUAL, '3'));
        $this->position->getTemplateAttributes()->add(new PositionTemplateAttribute($this->position, $years, true, 1));
        $this->position->getTemplateAttributes()->add(new PositionTemplateAttribute($this->position, $english, true, 2));
        $this->em->persist($this->position);

        $this->cv = new Cv($candidate, $this->position);
        $this->em->persist($this->cv);
        $this->em->flush();

        $this->englishId = $english->getId();
    }

    private int $englishId;

    public function testCandidateCanApplyToAccessiblePosition(): void
    {
        $this->client->loginUser($this->cv->getCandidate());
        $this->client->request('GET', '/positions/' . $this->position->getId() . '/apply');

        self::assertResponseRedirects('/cvs/' . $this->cv->getId());
    }

    public function testGetOnAttributeEndpointReturnsNormalized405Json(): void
    {
        // /api/cv/{id}/attribute is a WRITE-ONLY endpoint (POST). A GET — e.g.
        // typing the URL in the browser or probing with curl/Postman — must
        // yield a normalized JSON 405 via ApiExceptionListener, never an HTML
        // error page that would break JSON.parse on the frontend.
        $candidate = $this->cv->getCandidate();
        $this->client->loginUser($candidate);

        $this->client->request('GET', '/api/cv/' . $this->cv->getId() . '/attribute', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(405);
        $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($data['success']);
        self::assertArrayHasKey('message', $data);
        self::assertSame(405, $data['code']);
    }

    public function testInPlaceAttributeEditUpdatesMasterValue(): void
    {
        $candidate = $this->cv->getCandidate();
        $this->client->loginUser($candidate);

        $response = $this->jsonRequest($this->client, 'POST', '/api/cv/' . $this->cv->getId() . '/attribute', [
            'attributeId' => $this->englishId,
            'value' => 'C1',
            'version' => null,
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($this->englishId, $data['attributeId']);
        self::assertSame(1, $data['version']);
        self::assertFalse($data['isEmpty']);

        // Master profile value must have been written (no local CV copies).
        $this->em->clear();
        $freshValue = $this->em->getRepository(CandidateAttributeValue::class)->findOneBy([
            'attribute' => $this->englishId,
        ]);
        self::assertNotNull($freshValue);
        self::assertSame('C1', $freshValue->getValueString());
    }

    public function testPublishIncompleteCvReturns422WithMissingAttributes(): void
    {
        $this->client->loginUser($this->cv->getCandidate());

        $response = $this->jsonRequest($this->client, 'POST', '/api/cv/' . $this->cv->getId() . '/publish', []);

        self::assertResponseStatusCodeSame(422);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('incomplete', $data['error']);
        self::assertNotEmpty($data['missingAttributes']);
    }

    public function testPublishSucceedsAfterFillingRequiredAttributes(): void
    {
        $candidate = $this->cv->getCandidate();
        $this->client->loginUser($candidate);

        $this->jsonRequest($this->client, 'POST', '/api/cv/' . $this->cv->getId() . '/attribute', [
            'attributeId' => $this->englishId,
            'value' => 'C1',
            'version' => null,
        ]);

        $response = $this->jsonRequest($this->client, 'POST', '/api/cv/' . $this->cv->getId() . '/publish', []);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('PUBLISHED', $data['status']);
    }

    public function testCandidateCannotLikeCv(): void
    {
        $this->client->loginUser($this->cv->getCandidate());

        $this->jsonRequest($this->client, 'POST', '/api/cv/' . $this->cv->getId() . '/like', []);

        self::assertResponseStatusCodeSame(403);
    }

    public function testRecruiterTogglesLike(): void
    {
        $recruiter = $this->createUser('recruiter@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);

        $first = $this->jsonRequest($this->client, 'POST', '/api/cv/' . $this->cv->getId() . '/like', []);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['likesCount' => 1, 'liked' => true], json_decode($first->getContent(), true, flags: JSON_THROW_ON_ERROR));

        $second = $this->jsonRequest($this->client, 'POST', '/api/cv/' . $this->cv->getId() . '/like', []);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(['likesCount' => 0, 'liked' => false], json_decode($second->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testCandidateLikeReturnsJsonErrorNotHtml(): void
    {
        $candidate = $this->cv->getCandidate();
        $this->client->loginUser($candidate);

        $response = $this->jsonRequest($this->client, 'POST', '/api/cv/' . $this->cv->getId() . '/like', []);

        self::assertResponseStatusCodeSame(403);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($data['success']);
        self::assertSame('access_denied', $data['error']);
    }

    public function testApiReturnsJsonErrorForBadValue(): void
    {
        $candidate = $this->cv->getCandidate();
        $this->client->loginUser($candidate);

        // Fresh NUMERIC attribute with no value yet → parse error must flow
        // through the API as JSON 400, not an HTML debug page.
        $gpa = new Attribute('GPA', $this->category('Domain Knowledge'), AttributeDataType::NUMERIC);
        $this->em->persist($gpa);
        $this->em->flush();

        $response = $this->jsonRequest($this->client, 'POST', '/api/cv/' . $this->cv->getId() . '/attribute', [
            'attributeId' => $gpa->getId(),
            'value' => 'abc',
        ]);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($data['success']);
        self::assertSame('bad_request', $data['error']);
    }

    public function testBooleanFalseAndOptionAreFilledAndRenderLabel(): void
    {
        $candidate = $this->createUser('option-candidate@example.com', UserRole::ROLE_CANDIDATE);
        $this->createProfile($candidate);

        $python = new Attribute('Python', $this->category('Domain Knowledge'), AttributeDataType::BOOLEAN);
        $presentation = new Attribute('Presentation Skills', $this->category('Soft Skills'), AttributeDataType::ONE_OF_MANY);
        $this->em->persist($python);
        $this->em->persist($presentation);
        $pro = new AttributeOption($presentation, 'Pro', 'pro', 1);
        $presentation->getOptions()->add($pro);
        $this->em->persist($pro);

        $position = new Position('Public role', 'Open');
        $position->setPublic(true);
        $this->em->persist($position);
        $position->getTemplateAttributes()->add(new PositionTemplateAttribute($position, $python, true, 1));
        $position->getTemplateAttributes()->add(new PositionTemplateAttribute($position, $presentation, true, 2));

        $cv = new Cv($candidate, $position);
        $this->em->persist($cv);
        $this->em->flush();

        $this->client->loginUser($candidate);

        // Boolean "false" is a real value — must NOT be treated as empty.
        $r1 = $this->jsonRequest($this->client, 'POST', '/api/cv/' . $cv->getId() . '/attribute', [
            'attributeId' => $python->getId(),
            'value' => false,
        ]);
        self::assertResponseStatusCodeSame(200);
        self::assertFalse(json_decode($r1->getContent(), true, flags: JSON_THROW_ON_ERROR)['isEmpty']);

        // ONE_OF_MANY stores the option id and renders its label.
        $r2 = $this->jsonRequest($this->client, 'POST', '/api/cv/' . $cv->getId() . '/attribute', [
            'attributeId' => $presentation->getId(),
            'value' => (string) $pro->getId(),
        ]);
        self::assertResponseStatusCodeSame(200);
        self::assertFalse(json_decode($r2->getContent(), true, flags: JSON_THROW_ON_ERROR)['isEmpty']);

        $this->client->request('GET', '/cvs/' . $cv->getId());
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('Pro', (string) $html, 'option label must be rendered, not its raw id');
    }

    private function yearsId(): int
    {
        $years = $this->em->getRepository(Attribute::class)->findOneBy(['name' => 'Years of experience']);
        self::assertNotNull($years);

        return $years->getId();
    }
}
