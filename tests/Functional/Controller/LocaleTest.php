<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Attribute;
use App\Entity\Position;
use App\Entity\PositionTemplateAttribute;

use App\Enum\AttributeDataType;

final class LocaleTest extends AbstractFunctionalTestCase
{
    public function testSwitchingToRussianPersistsInSession(): void
    {
        $this->client->request('GET', '/locale/ru');
        self::assertResponseRedirects();

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'CV Платформа'); // messages.ru.yaml home.title
    }

    public function testDefaultLocaleIsEnglish(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'CV Platform');
    }

    public function testSwitchingBackToEnglish(): void
    {
        $this->client->request('GET', '/locale/ru');
        $this->client->request('GET', '/locale/en');
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'CV Platform');
    }

    public function testPositionPageShowsTranslatedAttributeTypesInRussian(): void
    {
        $attr = new Attribute('Remote', $this->category('Personal Information'), AttributeDataType::BOOLEAN);
        $this->em->persist($attr);
        $position = new Position('Test position', 'Description');
        $position->setPublic(true);
        $position->getTemplateAttributes()->add(new PositionTemplateAttribute($position, $attr, true, 1));
        $this->em->persist($position);
        $this->em->flush();

        $this->client->request('GET', '/locale/ru');
        $this->client->request('GET', '/positions/' . $position->getId());

        self::assertResponseIsSuccessful();
        // Table headers and the enum type label must be localized, not raw values.
        self::assertSelectorTextContains('table thead', 'Атрибут');
        self::assertSelectorTextContains('table thead', 'Тип');
        self::assertSelectorTextContains('table tbody', 'Флажок');
        self::assertSelectorTextNotContains('table tbody', 'BOOLEAN');
        // Placeholder interpolation: "4 макс. проектов", never "%4% макс. проектов".
        self::assertStringNotContainsString('%4%', $this->client->getResponse()->getContent());
        self::assertSelectorTextContains('div.text-muted', '4 макс. проектов');
    }
}
