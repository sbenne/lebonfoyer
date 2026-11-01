<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'application_answer')]
class ApplicationAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AdoptionApplication $application = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?AdoptionQuestion $question = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank(message: 'Répondez à chaque question.')]
    #[Assert\Length(max: 2000, maxMessage: 'La réponse est limitée à {{ limit }} caractères.')]
    private string $answer = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApplication(): ?AdoptionApplication
    {
        return $this->application;
    }

    public function setApplication(?AdoptionApplication $application): self
    {
        $this->application = $application;

        return $this;
    }

    public function getQuestion(): ?AdoptionQuestion
    {
        return $this->question;
    }

    public function setQuestion(AdoptionQuestion $question): self
    {
        $this->question = $question;

        return $this;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): self
    {
        $this->answer = trim($answer);

        return $this;
    }
}
