<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'adoption_question')]
class AdoptionQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?AnimalListing $listing = null;

    #[ORM\Column(length: 300)]
    #[Assert\NotBlank(message: 'La question ne peut pas être vide.')]
    #[Assert\Length(max: 300, maxMessage: 'La question est limitée à {{ limit }} caractères.')]
    private string $label = '';

    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getListing(): ?AnimalListing
    {
        return $this->listing;
    }

    public function setListing(?AnimalListing $listing): self
    {
        $this->listing = $listing;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = trim($label);

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function __toString(): string
    {
        return $this->label;
    }
}
