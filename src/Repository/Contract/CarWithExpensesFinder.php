<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Car;

interface CarWithExpensesFinder
{
    /** Машина з витратами (date DESC, id DESC) одним запитом. */
    public function findOneBySlugWithExpenses(string $slug): ?Car;
}
