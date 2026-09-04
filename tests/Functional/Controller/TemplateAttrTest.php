<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Attribute;

use App\Enum\AttributeDataType;
use App\Enum\UserRole;

final class TemplateAttrTest extends AbstractFunctionalTestCase
{
    public function testCreatePositionPersistsTemplateAttributesAndRules(): void
    {
        $recruiter = $this->createUser('ta-recruiter@example.com', UserRole::ROLE_RECRUITER);
        $this->client->loginUser($recruiter);

        $python = new Attribute('Python', $this->category('Domain Knowledge'), AttributeDataType::BOOLEAN);
        $english = new Attribute('English', $this->category('Soft Skills'), AttributeDataType::STRING);
        $this->em->persist($python);
        $this->em->persist($english);
        $this->em->flush();

        $response = $this->jsonRequest($this->client, 'POST', '/positions/new', [
            'title' => 'Template role',
            'shortDescription' => 'desc',
            'isPublic' => true,
            'maxProjects' => 4,
            'templateAttributes' => [
                ['attributeId' => $python->getId(), 'isRequired' => true, 'sortOrder' => 0],
                ['attributeId' => $english->getId(), 'isRequired' => false, 'sortOrder' => 1],
            ],
            'accessRules' => [
                ['attributeId' => $english->getId(), 'operator' => 'EQUALS', 'ruleValue' => 'C1'],
            ],
            'tags' => [],
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);

        $position = $this->em->getRepository(\App\Entity\Position::class)->find($data['id']);
        self::assertNotNull($position);
        self::assertCount(2, $position->getTemplateAttributes(), 'template attributes must be persisted');
        self::assertCount(1, $position->getAccessRules(), 'access rules must be persisted');

        // The position detail page must render the template attributes tab.
        $this->client->request('GET', '/positions/' . $position->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#tab-attributes', 'Python');
        self::assertSelectorTextContains('#tab-attributes', 'English');
        self::assertSelectorTextContains('#tab-attributes', 'required');

        // The edit form must pre-check the saved attributes.
        $this->client->request('GET', '/positions/' . $position->getId() . '/edit');
        self::assertResponseIsSuccessful();
        $editHtml = (string) $this->client->getResponse()->getContent();
        self::assertMatchesRegularExpression('/data-attr-id="' . $python->getId() . '"[^>]*checked/', $editHtml);
        self::assertMatchesRegularExpression('/data-attr-id="' . $english->getId() . '"[^>]*checked/', $editHtml);
    }
}
