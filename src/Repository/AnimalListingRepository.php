<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AnimalListing;
use App\Enum\ListingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnimalListing>
 */
class AnimalListingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnimalListing::class);
    }

    public function save(AnimalListing $listing, bool $flush = true): void
    {
        $this->getEntityManager()->persist($listing);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AnimalListing $listing, bool $flush = true): void
    {
        $this->getEntityManager()->remove($listing);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return list<AnimalListing> */
    public function findVisible(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('statuses', [ListingStatus::Published, ListingStatus::Reserved])
            ->orderBy('l.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function search(?string $species, ?string $city, ?string $query, string $sort = 'recent'): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('statuses', [ListingStatus::Published, ListingStatus::Reserved]);

        if (null !== $species && '' !== $species) {
            $qb->andWhere('l.species = :species')->setParameter('species', $species);
        }

        if (null !== $city && '' !== $city) {
            $qb->andWhere('LOWER(l.city) LIKE :city')->setParameter('city', '%'.mb_strtolower($city).'%');
        }

        if (null !== $query && '' !== $query) {
            $qb->andWhere('LOWER(l.name) LIKE :query OR LOWER(l.description) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($query).'%');
        }

        return match ($sort) {
            'oldest' => $qb->orderBy('l.publishedAt', 'ASC'),
            'name' => $qb->orderBy('l.name', 'ASC'),
            default => $qb->orderBy('l.publishedAt', 'DESC'),
        };
    }

    /** @return list<AnimalListing> */
    public function findByCedant(int $cedantId): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.cedant = :cedant')
            ->setParameter('cedant', $cedantId)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par rayon (en km) autour d'un point, via ST_Distance_Sphere de MariaDB.
     *
     * @return list<array{0: AnimalListing, distance: float}>
     */
    public function findNearby(float $latitude, float $longitude, float $radiusKm): array
    {
        $rsm = new ResultSetMappingBuilder($this->getEntityManager());
        $rsm->addRootEntityFromClassMetadata(AnimalListing::class, 'l');
        $rsm->addScalarResult('distance', 'distance', 'float');

        $sql = 'SELECT l.*, ST_Distance_Sphere(POINT(l.longitude, l.latitude), POINT(:lng, :lat)) / 1000 AS distance
            FROM animal_listing l
            WHERE l.status IN (:statuses)
              AND l.latitude IS NOT NULL
              AND l.longitude IS NOT NULL
            HAVING distance <= :radius
            ORDER BY distance ASC';

        $query = $this->getEntityManager()->createNativeQuery($sql, $rsm);
        $query->setParameter('statuses', [ListingStatus::Published->value, ListingStatus::Reserved->value]);
        $query->setParameter('lat', $latitude);
        $query->setParameter('lng', $longitude);
        $query->setParameter('radius', $radiusKm);

        /** @var list<array{0: AnimalListing, distance: float}> $result */
        $result = $query->getResult();

        return $result;
    }

    public function countVisible(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('statuses', [ListingStatus::Published, ListingStatus::Reserved])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
