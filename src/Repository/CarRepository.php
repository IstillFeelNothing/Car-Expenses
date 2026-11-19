<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Car;
use App\Repository\Contract\AllCarsOrderedByNameFinder;
use App\Repository\Contract\AllCarsWithExpensesFinder;
use App\Repository\Contract\CarBySlugFinder;
use App\Repository\Contract\CarSlugExistence;
use App\Repository\Contract\CarWithExpensesFinder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Car> */
class CarRepository extends ServiceEntityRepository implements CarBySlugFinder, CarSlugExistence, CarWithExpensesFinder, AllCarsWithExpensesFinder, AllCarsOrderedByNameFinder
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Car::class);
    }

    public function findOneBySlug(string $slug): ?Car
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /** Легкий запит без гідрації сутності. */
    public function existsBySlug(string $slug): bool
    {
        return null !== $this->createQueryBuilder('c')
            ->select('c.id')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Усі машини з витратами одним запитом (без N+1). Суми рахує CarExpenseStats
     * точно, у копійках, без float (SQL SUM на SQLite дає float).
     *
     * @return list<Car>
     */
    public function findAllWithExpenses(): array
    {
        /** @var list<Car> */
        return $this->createQueryBuilder('c')
            ->addSelect('e')
            ->leftJoin('c.expenses', 'e')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('e.date', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Car> */
    public function findAllOrderedByName(): array
    {
        /** @var list<Car> */
        return $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneBySlugWithExpenses(string $slug): ?Car
    {
        return $this->createQueryBuilder('c')
            ->addSelect('e')
            ->leftJoin('c.expenses', 'e')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->orderBy('e.date', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
