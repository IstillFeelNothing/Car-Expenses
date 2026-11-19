<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Car;

interface AllCarsOrderedByNameFinder
{
    /**
     * Усі машини без витрат, за назвою (name ASC, id ASC).
     *
     * @return list<Car>
     */
    public function findAllOrderedByName(): array;
}
