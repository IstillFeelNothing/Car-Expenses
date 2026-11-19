<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Car;

interface CarBySlugFinder
{
    public function findOneBySlug(string $slug): ?Car;
}
