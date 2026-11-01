<?php

declare(strict_types=1);

namespace App\Entity;

use App\Animal\Species;
use App\Enum\ListingStatus;
use App\Repository\AnimalListingRepository;
use App\Validator\ListingRules;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AnimalListingRepository::class)]
#[ORM\Table(name: 'animal_listing')]
#[ORM\Index(name: 'IDX_LISTING_SEARCH', columns: ['species', 'status', 'city'])]
#[ORM\Index(name: 'IDX_LISTING_BIRTH_DATE', columns: ['birth_date'])]
#[ListingRules]
class AnimalListing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'listings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $cedant = null;

    #[ORM\Column(enumType: Species::class)]
    private Species $species = Species::Cat;

    #[ORM\Column(length: 120)]
    private string $name = '';

    #[ORM\Column(type: 'date_immutable')]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column]
    private bool $birthDateEstimated = false;

    #[ORM\Column(length: 40)]
    private string $identificationNumber = '';

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(length: 120)]
    private string $city = '';

    #[ORM\Column(length: 10)]
    private string $postalCode = '';

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $price = null;

    #[ORM\Column(enumType: ListingStatus::class)]
    private ListingStatus $status = ListingStatus::Draft;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, AdoptionQuestion> */
    #[ORM\OneToMany(mappedBy: 'listing', targetEntity: AdoptionQuestion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $questions;

    /** @var Collection<int, AnimalPhoto> */
    #[ORM\OneToMany(mappedBy: 'listing', targetEntity: AnimalPhoto::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['isCover' => 'DESC', 'id' => 'ASC'])]
    private Collection $photos;

    /** @var Collection<int, AdoptionApplication> */
    #[ORM\OneToMany(mappedBy: 'listing', targetEntity: AdoptionApplication::class)]
    private Collection $applications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->questions = new ArrayCollection();
        $this->photos = new ArrayCollection();
        $this->applications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCedant(): ?User
    {
        return $this->cedant;
    }

    public function setCedant(User $cedant): self
    {
        $this->cedant = $cedant;

        return $this;
    }

    public function getSpecies(): Species
    {
        return $this->species;
    }

    public function setSpecies(Species $species): self
    {
        $this->species = $species;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(\DateTimeImmutable $birthDate): self
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function isBirthDateEstimated(): bool
    {
        return $this->birthDateEstimated;
    }

    public function setBirthDateEstimated(bool $birthDateEstimated): self
    {
        $this->birthDateEstimated = $birthDateEstimated;

        return $this;
    }

    public function getIdentificationNumber(): string
    {
        return $this->identificationNumber;
    }

    public function setIdentificationNumber(string $identificationNumber): self
    {
        $this->identificationNumber = trim($identificationNumber);

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = trim($description);

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = trim($city);

        return $this;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = trim($postalCode);

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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getStatus(): ListingStatus
    {
        return $this->status;
    }

    public function setStatus(ListingStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isPublished(): bool
    {
        return ListingStatus::Published === $this->status;
    }

    public function isReserved(): bool
    {
        return ListingStatus::Reserved === $this->status;
    }

    public function isVisible(): bool
    {
        return $this->status->isVisible();
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): self
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return Collection<int, AdoptionQuestion> */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(AdoptionQuestion $question): self
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setListing($this);
            $question->setPosition($this->questions->count() - 1);
        }

        return $this;
    }

    public function removeQuestion(AdoptionQuestion $question): self
    {
        if ($this->questions->removeElement($question) && $question->getListing() === $this) {
            $question->setListing(null);
        }

        return $this;
    }

    /** @return Collection<int, AnimalPhoto> */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addPhoto(AnimalPhoto $photo): self
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
            $photo->setListing($this);
        }

        return $this;
    }

    public function removePhoto(AnimalPhoto $photo): self
    {
        if ($this->photos->removeElement($photo) && $photo->getListing() === $this) {
            $photo->setListing(null);
        }

        return $this;
    }

    public function getCoverPhoto(): ?AnimalPhoto
    {
        return $this->photos->first() ?: null;
    }

    /** @return Collection<int, AdoptionApplication> */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function isOwnedBy(?User $user): bool
    {
        return null !== $user && $this->cedant?->getId() === $user->getId();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
