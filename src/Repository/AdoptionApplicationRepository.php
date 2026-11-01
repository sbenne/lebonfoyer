<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdoptionApplication;
use App\Enum\ApplicationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdoptionApplication>
 */
class AdoptionApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdoptionApplication::class);
    }

    public function save(AdoptionApplication $application, bool $flush = true): void
    {
        $this->getEntityManager()->persist($application);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return list<AdoptionApplication> */
    public function findByAdoptant(int $adoptantId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.adoptant = :adoptant')
            ->setParameter('adoptant', $adoptantId)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<AdoptionApplication> */
    public function findByCedant(int $cedantId): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.listing', 'l')
            ->andWhere('l.cedant = :cedant')
            ->setParameter('cedant', $cedantId)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findExisting(int $listingId, int $adoptantId): ?AdoptionApplication
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.listing = :listing')
            ->andWhere('a.adoptant = :adoptant')
            ->andWhere('a.status IN (:statuses)')
            ->setParameter('listing', $listingId)
            ->setParameter('adoptant', $adoptantId)
            ->setParameter('statuses', [ApplicationStatus::Pending, ApplicationStatus::Approved])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countPendingForCedant(int $cedantId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.listing', 'l')
            ->andWhere('l.cedant = :cedant')
            ->andWhere('a.status = :status')
            ->setParameter('cedant', $cedantId)
            ->setParameter('status', ApplicationStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.status = :status')
            ->setParameter('status', ApplicationStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
