<?php

declare(strict_types=1);

namespace App\Repository\Contract;

interface CarSlugExistence
{
    public function existsBySlug(string $slug): bool;
}
