<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\User;

interface UserByEmailFinder
{
    /** Точний збіг email (нормалізацію до нижнього регістру виконує викликач). */
    public function findOneByEmail(string $email): ?User;
}
