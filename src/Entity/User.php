<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CedantStatus;
use App\Enum\HousingType;
use App\Enum\UserType;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'UNIQ_APP_USER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cet email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 80)]
    private string $firstName = '';

    #[ORM\Column(length: 80)]
    private string $lastName = '';

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(enumType: UserType::class)]
    private UserType $type = UserType::Adoptant;

    #[ORM\Column(enumType: CedantStatus::class, nullable: true)]
    private ?CedantStatus $cedantStatus = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $organizationName = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $siren = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $rna = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $breedingNumber = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(enumType: HousingType::class, nullable: true)]
    private ?HousingType $housingType = null;

    #[ORM\Column]
    private bool $hasGarden = false;

    #[ORM\Column]
    private bool $hasTerrace = false;

    #[ORM\Column]
    private bool $hasOtherAnimals = false;

    #[ORM\Column]
    private bool $hasChildren = false;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** @var Collection<int, AnimalListing> */
    #[ORM\OneToMany(mappedBy: 'cedant', targetEntity: AnimalListing::class)]
    private Collection $listings;

    /** @var Collection<int, AdoptionApplication> */
    #[ORM\OneToMany(mappedBy: 'adoptant', targetEntity: AdoptionApplication::class)]
    private Collection $applications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->listings = new ArrayCollection();
        $this->applications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = $this->type->role();
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName.' '.$this->lastName);
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getType(): UserType
    {
        return $this->type;
    }

    public function setType(UserType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function isCedant(): bool
    {
        return UserType::Cedant === $this->type;
    }

    public function isAdoptant(): bool
    {
        return UserType::Adoptant === $this->type;
    }

    public function isAdmin(): bool
    {
        return UserType::Admin === $this->type;
    }

    public function getCedantStatus(): ?CedantStatus
    {
        return $this->cedantStatus;
    }

    public function setCedantStatus(?CedantStatus $cedantStatus): self
    {
        $this->cedantStatus = $cedantStatus;

        return $this;
    }

    public function getOrganizationName(): ?string
    {
        return $this->organizationName;
    }

    public function setOrganizationName(?string $organizationName): self
    {
        $this->organizationName = $organizationName;

        return $this;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): self
    {
        $this->siren = $siren;

        return $this;
    }

    public function getRna(): ?string
    {
        return $this->rna;
    }

    public function setRna(?string $rna): self
    {
        $this->rna = $rna;

        return $this;
    }

    public function getBreedingNumber(): ?string
    {
        return $this->breedingNumber;
    }

    public function setBreedingNumber(?string $breedingNumber): self
    {
        $this->breedingNumber = $breedingNumber;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getHousingType(): ?HousingType
    {
        return $this->housingType;
    }

    public function setHousingType(?HousingType $housingType): self
    {
        $this->housingType = $housingType;

        return $this;
    }

    public function hasGarden(): bool
    {
        return $this->hasGarden;
    }

    public function setHasGarden(bool $hasGarden): self
    {
        $this->hasGarden = $hasGarden;

        return $this;
    }

    public function hasTerrace(): bool
    {
        return $this->hasTerrace;
    }

    public function setHasTerrace(bool $hasTerrace): self
    {
        $this->hasTerrace = $hasTerrace;

        return $this;
    }

    public function hasOtherAnimals(): bool
    {
        return $this->hasOtherAnimals;
    }

    public function setHasOtherAnimals(bool $hasOtherAnimals): self
    {
        $this->hasOtherAnimals = $hasOtherAnimals;

        return $this;
    }

    public function hasChildren(): bool
    {
        return $this->hasChildren;
    }

    public function setHasChildren(bool $hasChildren): self
    {
        $this->hasChildren = $hasChildren;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /** @return Collection<int, AnimalListing> */
    public function getListings(): Collection
    {
        return $this->listings;
    }

    /** @return Collection<int, AdoptionApplication> */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function __toString(): string
    {
        return $this->getFullName() ?: $this->email;
    }
}
