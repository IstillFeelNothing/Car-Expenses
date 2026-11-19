<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Expense;
use App\Repository\Contract\ExpensesByCarSlugFinder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Expense> */
class ExpenseRepository extends ServiceEntityRepository implements ExpensesByCarSlugFinder
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * Витрати машини: date DESC, id DESC. Одним запитом, машина підтягується join'ом (без N+1).
     *
     * @return list<Expense>
     */
    public function findByCarSlug(string $slug): array
    {
        /** @var list<Expense> */
        return $this->createQueryBuilder('e')
            ->addSelect('c')
            ->innerJoin('e.car', 'c')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->orderBy('e.date', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
