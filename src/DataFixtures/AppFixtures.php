<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Animal\Species;
use App\Entity\AdoptionQuestion;
use App\Entity\AnimalListing;
use App\Entity\User;
use App\Enum\CedantStatus;
use App\Enum\HousingType;
use App\Enum\ListingStatus;
use App\Enum\UserType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Clock\Clock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $now = Clock::get()->now();

        $admin = $this->createUser(
            'admin@lebonfoyer.local',
            'Admin',
            'LeBonFoyer',
            'Admin12345678!',
            UserType::Admin,
            null,
            'Paris',
            '75001',
        );
        $admin->setIsVerified(true);

        $particulier = $this->createUser(
            'cedant@lebonfoyer.local',
            'Camille',
            'Durand',
            'Cedant12345678!',
            UserType::Cedant,
            CedantStatus::Particulier,
            'Lyon',
            '69003',
        );
        $particulier->setIsVerified(true)->setPhone('06 12 34 56 78');

        $association = $this->createUser(
            'association@lebonfoyer.local',
            'Sofia',
            'Martin',
            'Asso12345678!',
            UserType::Cedant,
            CedantStatus::Association,
            'Nantes',
            '44000',
        );
        $association
            ->setIsVerified(true)
            ->setOrganizationName('Refuge des Pattes')
            ->setRna('W442000123')
            ->setSiren('123456789');

        $adoptant = $this->createUser(
            'adoptant@lebonfoyer.local',
            'Lucas',
            'Bernard',
            'Adoptant12345678!',
            UserType::Adoptant,
            null,
            'Villeurbanne',
            '69100',
        );
        $adoptant
            ->setIsVerified(true)
            ->setHousingType(HousingType::Appartement)
            ->setHasGarden(false)
            ->setHasTerrace(true)
            ->setHasChildren(false);

        $manager->persist($admin);
        $manager->persist($particulier);
        $manager->persist($association);
        $manager->persist($adoptant);

        $cat = (new AnimalListing())
            ->setCedant($particulier)
            ->setSpecies(Species::Cat)
            ->setName('Mochi')
            ->setBirthDate($now->modify('-5 months'))
            ->setIdentificationNumber('250269812345678')
            ->setDescription('Mochi est une chatte curieuse et très affectueuse. Elle aime dormir près de son humain et jouer avec des plumeaux. Elle est habituée à la vie en appartement, propre et sociable. Elle cherche une famille patiente qui pourra lui offrir un foyer stable.')
            ->setCity('Lyon')
            ->setPostalCode('69003')
            ->setStatus(ListingStatus::Published)
            ->setPublishedAt($now->modify('-3 days'));

        $cat->addQuestion((new AdoptionQuestion())->setLabel('Avez-vous déjà eu un chat à la maison ?')->setPosition(0));
        $cat->addQuestion((new AdoptionQuestion())->setLabel('Combien de temps votre logement reste-t-il vide dans la journée ?')->setPosition(1));
        $cat->addQuestion((new AdoptionQuestion())->setLabel('Que ferez-vous pendant vos vacances ?')->setPosition(2));

        $dog = (new AnimalListing())
            ->setCedant($association)
            ->setSpecies(Species::Dog)
            ->setName('Oscar')
            ->setBirthDate($now->modify('-14 months'))
            ->setIdentificationNumber('250269876543210')
            ->setDescription("Oscar est un jeune chien plein d'énergie, sociable avec les humains et les autres chiens. Il a besoin de promenades quotidiennes et d'un jardin ou de longues sorties. Le refuge vous accompagnera pour une adoption en douceur.")
            ->setCity('Nantes')
            ->setPostalCode('44000')
            ->setStatus(ListingStatus::Published)
            ->setPublishedAt($now->modify('-1 day'));

        $draft = (new AnimalListing())
            ->setCedant($particulier)
            ->setSpecies(Species::Cat)
            ->setName('Plume')
            ->setBirthDate($now->modify('-10 months'))
            ->setIdentificationNumber('250269800011223')
            ->setDescription('Plume est une chatte calme, timide au premier abord mais très câline une fois en confiance. Elle recherche un foyer tranquille, sans jeunes enfants, où elle pourra prendre ses marques à son rythme.')
            ->setCity('Lyon')
            ->setPostalCode('69003')
            ->setStatus(ListingStatus::Draft);

        $manager->persist($cat);
        $manager->persist($dog);
        $manager->persist($draft);

        $manager->flush();
    }

    private function createUser(
        string $email,
        string $firstName,
        string $lastName,
        string $password,
        UserType $type,
        ?CedantStatus $cedantStatus,
        string $city,
        string $postalCode,
    ): User {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setType($type)
            ->setCedantStatus($cedantStatus)
            ->setCity($city)
            ->setPostalCode($postalCode);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        return $user;
    }
}
