<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Attribute;
use App\Entity\AttributeCategory;
use App\Entity\AttributeOption;
use App\Entity\CandidateAttributeValue;
use App\Entity\CandidateProfile;
use App\Entity\Cv;
use App\Entity\CvLike;
use App\Entity\Discussion;
use App\Entity\Position;
use App\Entity\PositionAccessRule;
use App\Entity\PositionTemplateAttribute;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\AccessRuleOperator;
use App\Enum\AttributeDataType;
use App\Enum\UserRole;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Enum\Format;
use App\Enum\Level;
/**
 * Demo data following the exam scenario: Acme Corp, Anna, CAP,
 * Junior Data Engineer. Load with:
 *   php bin/console doctrine:fixtures:load --no-interaction
 *
 * Depends on AttributeCategoryFixtures so every Attribute can reference a
 * persisted category row (categories are a lookup table now, not an enum).
 */
final class AppFixtures extends Fixture implements DependentFixtureInterface
{
    private const PASSWORD = 'password123';

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function getDependencies(): array
    {
        return [AttributeCategoryFixtures::class];
    }

    public function load(ObjectManager $em): void
    {
        // ------------------------------------------------------------------ Users
        $admin = $this->user('admin@platform.local', UserRole::ROLE_ADMIN);
        $sarah = $this->user('recruiter@acme.com', UserRole::ROLE_RECRUITER);
        $anna = $this->user('candidate.anna@mail.com', UserRole::ROLE_CANDIDATE);
        $john = $this->user('candidate.john@mail.com', UserRole::ROLE_CANDIDATE);
        foreach ([$admin, $sarah, $anna, $john] as $user) {
            $em->persist($user);
        }

        // ------------------------------------------------------------------ Attribute library
        $cap = $this->attribute($em, 'CAP', AttributeCategoryFixtures::REF_CERTIFICATION, AttributeDataType::ONE_OF_MANY, ['None', 'Essentials', 'Pro', 'Expert']);
        $english = $this->attribute($em, 'English Level', AttributeCategoryFixtures::REF_SOFT_SKILLS, AttributeDataType::ONE_OF_MANY, ['A1', 'A2', 'B1', 'B2', 'C1', 'C2']);
        $gpa = $this->attribute($em, 'GPA', AttributeCategoryFixtures::REF_DOMAIN_KNOWLEDGE, AttributeDataType::NUMERIC);
        $python = $this->attribute($em, 'Python', AttributeCategoryFixtures::REF_DOMAIN_KNOWLEDGE, AttributeDataType::BOOLEAN);
        $hadoop = $this->attribute($em, 'Apache Hadoop', AttributeCategoryFixtures::REF_DOMAIN_KNOWLEDGE, AttributeDataType::BOOLEAN);
        $remote = $this->attribute($em, 'Remote Work', AttributeCategoryFixtures::REF_PERSONAL_INFO, AttributeDataType::BOOLEAN);
        $presentation = $this->attribute($em, 'Presentation Skills', AttributeCategoryFixtures::REF_SOFT_SKILLS, AttributeDataType::ONE_OF_MANY, ['Basic', 'Intermediate', 'Advanced']);
        $ielts = $this->attribute($em, 'IELTS Score', AttributeCategoryFixtures::REF_CERTIFICATION, AttributeDataType::NUMERIC);

        // ------------------------------------------------------------------ Tags
        $sql = $this->tag($em, 'SQL');
        $r = $this->tag($em, 'R');
        $pythonTag = $this->tag($em, 'Python');
 


        // ------------------------------------------------------------------ Position 1: Junior Data Engineer @ Acme Corp (key scenario)
        $jde = new Position('Junior Data Engineer @ Acme Corp', 'Join the Acme Corp data team: build and maintain data pipelines, reports and analytics tooling.');
        $jde->setCompanyName('Acme Corp');
        $jde->setLevel(Level::JUNIOR);
        $jde->setPublic(false);
        $jde->setMaxProjects(4);
        $jde->setFormat(Format::Hybrid);
        foreach ([$sql, $r, $pythonTag] as $tag) {
            $jde->addTag($tag);
        }
        $em->persist($jde);

        $this->template($jde, $cap, true, 1);
        $this->template($jde, $english, true, 2);
        $this->template($jde, $gpa, true, 3);
        $this->template($jde, $python, true, 4);
        $this->template($jde, $hadoop, true, 5);

        $em->persist(new PositionAccessRule($jde, $gpa, AccessRuleOperator::GREATER_THAN_OR_EQUAL, '3.0'));
        $em->persist(new PositionAccessRule($jde, $english, AccessRuleOperator::EQUALS, 'C1'));
        $em->persist(new PositionAccessRule($jde, $python, AccessRuleOperator::IS_CHECKED, ''));

        // Discussion on the JDE position
        $em->persist(new Discussion($jde, $sarah, 'Welcome! We are looking for a junior teammate to own the ETL layer.'));
        $em->persist(new Discussion($jde, $anna, 'Hi! I have built a Python + Airflow pipeline recently — happy to share details.'));
        $em->persist(new Discussion($jde, $sarah, 'Great, please apply and fill in the required attributes — good luck!'));

        // ------------------------------------------------------------------ Position 2: Senior Data Scientist @ AI Labs
        $sds = new Position('Senior Data Scientist @ AI Labs', 'Advanced ML modeling and productionization with PyTorch.');
        $sds->setCompanyName('AI Labs');
        $sds->setLevel(Level::SENIOR);
        $sds->setFormat(Format::Remote);
        $sds->setPublic(true);
        foreach ([$pythonTag, $ml, $pytorch] as $tag) {
            $sds->addTag($tag);
        }
        $em->persist($sds);
        $this->template($sds, $python, true, 1);
        $this->template($sds, $presentation, false, 2);

        // ------------------------------------------------------------------ Anna's profile (master values)
        $annaProfile = new CandidateProfile($anna, 'Anna', 'Smirnova');
        $annaProfile->setLocation('Warsaw');
        $annaProfile->setAvatarUrl('https://i.pravatar.cc/150?u=anna');
        $em->persist($annaProfile);

        $this->profileValue($em, $annaProfile, $english, option: $this->optionOf($english, 'C1'));
        $this->profileValue($em, $annaProfile, $gpa, numeric: '3.8');
        $this->profileValue($em, $annaProfile, $python, boolean: true);
        $this->profileValue($em, $annaProfile, $hadoop, boolean: true);
        $this->profileValue($em, $annaProfile, $remote, boolean: true);
        // CAP is intentionally NOT filled in — the demo shows the red highlight.

        // Anna's projects (the third one shares no tags with the JDE position)
        $project1 = new Project($annaProfile, 'E-Commerce Data Pipeline', new DateTimeImmutable('2024-01-15'), 'ETL from the shop DB into the warehouse with Airflow and dbt.');
        $project1->addTag($sql);
        $project1->addTag($pythonTag);
        $project1->addTag($airflow);
        $project1->setEndDate(new DateTimeImmutable('2025-03-01'));
        $em->persist($project1);

        $project2 = new Project($annaProfile, 'Customer Churn R Model', new DateTimeImmutable('2023-02-01'), 'Logistic regression and survival analysis in R.');
        $project2->addTag($r);
        $project2->addTag($statistics);
        $project2->setEndDate(new DateTimeImmutable('2024-01-15'));
        $em->persist($project2);

        $project3 = new Project($annaProfile, 'Wordpress Blog Site', new DateTimeImmutable('2022-06-01'), 'Company blog built with PHP and custom CSS.');
        $project3->addTag($php);
        $project3->addTag($css);
        $project3->setEndDate(new DateTimeImmutable('2023-01-15'));
        $em->persist($project3);

        // Anna's CV for JDE — DRAFT, so CAP shows empty/red and Publish is disabled
        $em->persist(new Cv($anna, $jde));

        // ------------------------------------------------------------------ John's profile + published CV with a like from Sarah
        $johnProfile = new CandidateProfile($john, 'John', 'Doe');
        $johnProfile->setLocation('Berlin');
        $em->persist($johnProfile);

        $this->profileValue($em, $johnProfile, $english, option: $this->optionOf($english, 'C2'));
        $this->profileValue($em, $johnProfile, $gpa, numeric: '3.6');
        $this->profileValue($em, $johnProfile, $python, boolean: true);
        $this->profileValue($em, $johnProfile, $presentation, option: $this->optionOf($presentation, 'Advanced'));
        $this->profileValue($em, $johnProfile, $ielts, numeric: '8.0');

        $johnProject = new Project($johnProfile, 'NLP Chatbot', new DateTimeImmutable('2024-05-01'), 'Transformer-based chatbot fine-tuned with PyTorch.');
        $johnProject->addTag($pythonTag);
        $johnProject->addTag($pytorch);
        $johnProject->addTag($ml);
        $em->persist($johnProject);

        $johnCv = new Cv($john, $sds);
        $johnCv->publish();
        $em->persist($johnCv);
        $em->persist(new CvLike($johnCv, $sarah));
        $johnCv->incrementLikes();

        $em->flush();
    }

    private function user(string $email, UserRole $role): User
    {
        $user = new User($email, [$role->value]);
        $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));

        return $user;
    }

    /** @param list<string> $options */
    private function attribute(ObjectManager $em, string $name, string $categoryReference, AttributeDataType $type, array $options = []): Attribute
    {
        $category = $this->getReference($categoryReference, AttributeCategory::class);
        $attribute = new Attribute($name, $category, $type);
        $em->persist($attribute);
        foreach ($options as $sort => $optionValue) {
            $option = new AttributeOption($attribute, $optionValue, $optionValue, $sort + 1);
            $attribute->getOptions()->add($option);
            $em->persist($option);
        }

        return $attribute;
    }

    private function tag(ObjectManager $em, string $name): Tag
    {
        $tag = new Tag($name);
        $em->persist($tag);

        return $tag;
    }

    private function template(Position $position, Attribute $attribute, bool $required, int $sortOrder): void
    {
        $position->getTemplateAttributes()->add(new PositionTemplateAttribute($position, $attribute, $required, $sortOrder));
    }

    private function optionOf(Attribute $attribute, string $value): AttributeOption
    {
        $option = $attribute->getOptions()->filter(static fn (AttributeOption $o): bool => $o->getValue() === $value)->first();

        return $option instanceof AttributeOption ? $option : throw new \LogicException(sprintf('Option "%s" not found.', $value));
    }

    private function profileValue(
        ObjectManager $em,
        CandidateProfile $profile,
        Attribute $attribute,
        ?AttributeOption $option = null,
        ?string $numeric = null,
        ?bool $boolean = null,
    ): void {
        $value = new CandidateAttributeValue($profile, $attribute);
        $value->setValueOption($option);
        $value->setValueNumeric($numeric);
        $value->setValueBoolean($boolean);
        $profile->getAttributeValues()->add($value);
        $em->persist($value);
    }
}
