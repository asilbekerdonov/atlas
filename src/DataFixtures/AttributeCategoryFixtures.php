<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AttributeCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds the attribute_category lookup table with the same 6 canonical rows
 * as the migration (ON CONFLICT DO NOTHING there). Loaded BEFORE any fixture
 * that creates Attribute rows — AppFixtures depends on this class.
 */
final class AttributeCategoryFixtures extends Fixture
{
    public const REF_CERTIFICATION = 'attribute_category.certification';
    public const REF_DOMAIN_KNOWLEDGE = 'attribute_category.domain_knowledge';
    public const REF_PERSONAL_INFO = 'attribute_category.personal_info';
    public const REF_SOFT_SKILLS = 'attribute_category.soft_skills';
    public const REF_TECHNICAL_SKILLS = 'attribute_category.technical_skills';
    public const REF_LANGUAGES = 'attribute_category.languages';

    /** Canonical seed set — keep in sync with the migration data block. */
    public const SEED = [
        self::REF_CERTIFICATION => 'Certification',
        self::REF_DOMAIN_KNOWLEDGE => 'Domain Knowledge',
        self::REF_PERSONAL_INFO => 'Personal Information',
        self::REF_SOFT_SKILLS => 'Soft Skills',
        self::REF_TECHNICAL_SKILLS => 'Technical Skills',
        self::REF_LANGUAGES => 'Languages',
    ];

    public function load(ObjectManager $em): void
    {
        foreach (self::SEED as $reference => $name) {
            $category = $em->getRepository(AttributeCategory::class)->findOneBy(['name' => $name]);
            if ($category === null) {
                $category = new AttributeCategory($name);
                $em->persist($category);
            }
            $this->addReference($reference, $category);
        }
        $em->flush();
    }
}
