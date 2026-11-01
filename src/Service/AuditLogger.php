<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function log(string $action, ?object $subject = null, array $context = []): void
    {
        $actor = $this->security->getUser();

        $entry = (new AuditLog())
            ->setAction($action)
            ->setActor($actor instanceof User ? $actor : null)
            ->setContext($context);

        if (null !== $subject) {
            $entry->setSubjectType($subject::class);
            if (method_exists($subject, 'getId')) {
                /** @var int|null $id */
                $id = $subject->getId();
                $entry->setSubjectId($id);
            }
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }
}
