<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ApplicationStatus;
use App\Repository\AdoptionApplicationRepository;
use App\Validator\ApplicationRules;
use App\Validator\WelfareHandover;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdoptionApplicationRepository::class)]
#[ORM\Table(name: 'adoption_application')]
#[WelfareHandover]
#[ApplicationRules]
class AdoptionApplication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'applications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AnimalListing $listing = null;

    #[ORM\ManyToOne(inversedBy: 'applications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $adoptant = null;

    #[ORM\Column(enumType: ApplicationStatus::class)]
    private ApplicationStatus $status = ApplicationStatus::Pending;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull(message: 'Choisissez une date de remise.')]
    private ?\DateTimeImmutable $handoverDate = null;

    #[ORM\Column]
    private bool $warningConfirmed = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $warningConfirmedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $handedOverAt = null;

    /** @var Collection<int, ApplicationAnswer> */
    #[ORM\OneToMany(mappedBy: 'application', targetEntity: ApplicationAnswer::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $answers;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->answers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getListing(): ?AnimalListing
    {
        return $this->listing;
    }

    public function setListing(AnimalListing $listing): self
    {
        $this->listing = $listing;

        return $this;
    }

    public function getAdoptant(): ?User
    {
        return $this->adoptant;
    }

    public function setAdoptant(User $adoptant): self
    {
        $this->adoptant = $adoptant;

        return $this;
    }

    public function getStatus(): ApplicationStatus
    {
        return $this->status;
    }

    public function setStatus(ApplicationStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isPending(): bool
    {
        return ApplicationStatus::Pending === $this->status;
    }

    public function isApproved(): bool
    {
        return ApplicationStatus::Approved === $this->status;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getHandoverDate(): ?\DateTimeImmutable
    {
        return $this->handoverDate;
    }

    public function setHandoverDate(\DateTimeImmutable $handoverDate): self
    {
        $this->handoverDate = $handoverDate;

        return $this;
    }

    public function isWarningConfirmed(): bool
    {
        return $this->warningConfirmed;
    }

    public function setWarningConfirmed(bool $warningConfirmed): self
    {
        $this->warningConfirmed = $warningConfirmed;

        return $this;
    }

    public function getWarningConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->warningConfirmedAt;
    }

    public function setWarningConfirmedAt(?\DateTimeImmutable $warningConfirmedAt): self
    {
        $this->warningConfirmedAt = $warningConfirmedAt;

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

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function setDecidedAt(?\DateTimeImmutable $decidedAt): self
    {
        $this->decidedAt = $decidedAt;

        return $this;
    }

    public function getHandedOverAt(): ?\DateTimeImmutable
    {
        return $this->handedOverAt;
    }

    public function setHandedOverAt(?\DateTimeImmutable $handedOverAt): self
    {
        $this->handedOverAt = $handedOverAt;

        return $this;
    }

    /** @return Collection<int, ApplicationAnswer> */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    public function addAnswer(ApplicationAnswer $answer): self
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setApplication($this);
        }

        return $this;
    }

    public function getAnswerFor(AdoptionQuestion $question): ?ApplicationAnswer
    {
        foreach ($this->answers as $answer) {
            if ($answer->getQuestion()?->getId() === $question->getId()) {
                return $answer;
            }
        }

        return null;
    }
}
