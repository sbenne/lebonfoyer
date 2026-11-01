<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function save(Conversation $conversation, bool $flush = true): void
    {
        $this->getEntityManager()->persist($conversation);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return list<Conversation> */
    public function findByParticipant(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->andWhere('p.id = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBetween(User $a, User $b, ?int $listingId = null): ?Conversation
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'pa')
            ->innerJoin('c.participants', 'pb')
            ->andWhere('pa.id = :a')
            ->andWhere('pb.id = :b')
            ->setParameter('a', $a->getId())
            ->setParameter('b', $b->getId())
            ->setMaxResults(1);

        if (null !== $listingId) {
            $qb->andWhere('c.listing = :listing')->setParameter('listing', $listingId);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function countUnreadFor(User $user): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(\App\Entity\Message::class, 'm')
            ->join('m.conversation', 'c')
            ->join('c.participants', 'p')
            ->andWhere('p.id = :userId')
            ->andWhere('m.sender != :userId')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
