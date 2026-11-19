<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Car;

interface AllCarsWithExpensesFinder
{
    /**
     * Усі машини з витратами одним запитом (без N+1), за назвою; витрати date DESC, id DESC.
     *
     * @return list<Car>
     */
    public function findAllWithExpenses(): array;
}
