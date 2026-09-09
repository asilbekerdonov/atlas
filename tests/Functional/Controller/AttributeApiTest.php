<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Attribute;
use App\Entity\AttributeOption;
use App\Entity\AttributeCategory;
use App\Enum\AttributeDataType;
use App\Enum\UserRole;

final class AttributeApiTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $recruiter = $this->createUser('attr@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);
    }

    public function testCreateOneOfManyWithOptionsReturns201(): void
    {
        $response = $this->jsonRequest($this->client, 'POST', '/attributes/new', [
            'name' => 'GitHub Stars Level',
            'category' => 'Soft Skills',
            'dataType' => 'ONE_OF_MANY',
            'options' => ['Bronze', 'Silver', 'Gold'],
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);

        $attribute = $this->em->find(Attribute::class, $data['id']);
        self::assertNotNull($attribute);
        self::assertCount(3, $attribute->getOptions());
        self::assertSame('Gold', $attribute->getOptions()->last()->getLabel());
    }

    public function testCreateDuplicateNameReturns409(): void
    {
        $this->jsonRequest($this->client, 'POST', '/attributes/new', [
            'name' => 'Unique Name',
            'category' => 'Soft Skills',
            'dataType' => 'STRING',
        ]);
        self::assertResponseStatusCodeSame(201);

        $response = $this->jsonRequest($this->client, 'POST', '/attributes/new', [
            'name' => 'Unique Name',
            'category' => 'Soft Skills',
            'dataType' => 'STRING',
        ]);

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('name_exists', $data['error']);
    }

    public function testCreateNameInAnotherCaseReturns409(): void
    {
        // First creation in lowercase wins.
        $first = $this->jsonRequest($this->client, 'POST', '/attributes/new', [
            'name' => 'python',
            'category' => 'Technical Skills',
            'dataType' => 'STRING',
        ]);
        self::assertResponseStatusCodeSame(201);

        // Same name in another case must conflict (case-insensitive unique).
        $response = $this->jsonRequest($this->client, 'POST', '/attributes/new', [
            'name' => 'PYTHON',
            'category' => 'Technical Skills',
            'dataType' => 'STRING',
        ]);

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('name_exists', $data['error']);
    }

    public function testPureCaseChangeOfOwnNameStaysAllowed(): void
    {
        // Renaming "python" → "Python" touches only this attribute, so the
        // case-insensitive check must NOT report a conflict with itself.
        $create = $this->jsonRequest($this->client, 'POST', '/attributes/new', [
            'name' => 'python',
            'category' => 'Technical Skills',
            'dataType' => 'STRING',
        ]);
        $created = json_decode($create->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertResponseStatusCodeSame(201);

        $response = $this->jsonRequest($this->client, 'POST', '/attributes/' . $created['id'] . '/edit', [
            'name' => 'Python',
            'category' => 'Technical Skills',
            'dataType' => 'STRING',
        ]);

        self::assertResponseStatusCodeSame(200);
    }

    public function testOneOfManyWithoutOptionsReturns422(): void
    {
        $response = $this->jsonRequest($this->client, 'POST', '/attributes/new', [
            'name' => 'Empty Dropdown',
            'category' => 'Soft Skills',
            'dataType' => 'ONE_OF_MANY',
            'options' => [],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testEditUpdatesOptionsAndRenames(): void
    {
        $create = $this->jsonRequest($this->client, 'POST', '/attributes/new', [
            'name' => 'Team Size',
            'category' => 'Personal Information',
            'dataType' => 'ONE_OF_MANY',
            'options' => ['Small', 'Big'],
        ]);
        $id = json_decode($create->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $response = $this->jsonRequest($this->client, 'POST', '/attributes/' . $id . '/edit', [
            'name' => 'Team Scale',
            'category' => 'Personal Information',
            'dataType' => 'ONE_OF_MANY',
            'options' => ['Solo', 'Duo', 'Trio'],
        ]);

        self::assertResponseStatusCodeSame(200);
        $attribute = $this->em->find(Attribute::class, $id);
        self::assertSame('Team Scale', $attribute->getName());
        self::assertCount(3, $attribute->getOptions(), 'options must be replaced');
    }

    public function testEditToDuplicateNameReturns409(): void
    {
        $this->jsonRequest($this->client, 'POST', '/attributes/new', [
            'name' => 'First', 'category' => 'Soft Skills', 'dataType' => 'STRING',
        ]);
        $second = $this->jsonRequest($this->client, 'POST', '/attributes/new', [
            'name' => 'Second', 'category' => 'Soft Skills', 'dataType' => 'STRING',
        ]);
        $secondId = json_decode($second->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $response = $this->jsonRequest($this->client, 'POST', '/attributes/' . $secondId . '/edit', [
            'name' => 'First', 'category' => 'Soft Skills', 'dataType' => 'STRING',
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testShowJsonReturnsFullPayload(): void
    {
        $option = null;
        $attribute = new Attribute('Level', $this->category('Soft Skills'), AttributeDataType::ONE_OF_MANY);
        $this->em->persist($attribute);
        $option = new AttributeOption($attribute, 'Pro', 'Pro', 1);
        $attribute->getOptions()->add($option);
        $this->em->persist($option);
        $this->em->flush();

        $this->client->request('GET', '/api/attributes/' . $attribute->getId(), [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Level', $data['name']);
        self::assertSame('ONE_OF_MANY', $data['dataType']);
        self::assertSame('Pro', $data['options'][0]['label']);
    }

    public function testIndexCategoryLabelTranslatesKnownAndFallsBackRawForUnknown(): void
    {
        // Known category → translation key attribute.category.soft_skills exists.
        $known = $this->category('Soft Skills');
        // Arbitrary category with no translation key → raw-name fallback.
        $unknown = $this->category('Quantum Physics');

        $this->em->persist(new Attribute('Known attr', $known, AttributeDataType::STRING));
        $this->em->persist(new Attribute('Unknown attr', $unknown, AttributeDataType::STRING));
        $this->em->flush();

        $this->client->request('GET', '/attributes');
        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();

        // Option value stays the raw name (API contract), the label is translated.
        self::assertStringContainsString('<option value="Soft Skills">Soft skills</option>', $html);
        self::assertStringContainsString('Quantum Physics', $html, 'unknown category must render raw, without error');
        self::assertStringNotContainsString('attribute.category.quantum_physics', $html, 'no dangling translation key may leak');
    }

    /**
     * Regression: search used to 500 when the result contained an attribute
     * WITHOUT options (e.g. "Python", BOOLEAN) because attributePayload()
     * called the non-existent Attribute::getFormat(). Prefixes matching
     * nothing returned 200 only because the payload mapper never ran.
     */
    public function testSearchReturns200ForBooleanAttributeWithoutOptions(): void
    {
        $this->em->persist(new Attribute('Python', $this->category('Technical Skills'), AttributeDataType::BOOLEAN));
        $this->em->flush();

        $this->client->request('GET', '/api/attributes/search?q=python&limit=8', [], [], ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($data, 'the search must find the Python attribute');
        self::assertSame('Python', $data[0]['name']);
        self::assertSame([], $data[0]['options'], 'an attribute without options must serialise an empty list');
    }
}
