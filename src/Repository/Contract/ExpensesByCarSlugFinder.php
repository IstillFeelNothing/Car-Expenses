<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Expense;

interface ExpensesByCarSlugFinder
{
    /** @return list<Expense> date DESC, id DESC */
    public function findByCarSlug(string $slug): array;
}
