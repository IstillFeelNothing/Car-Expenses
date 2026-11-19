<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Car;
use App\Entity\Expense;
use App\ValueObject\Money;

final class CarExpenseStats
{
    public function __construct(private readonly UkrainianCollator $collator)
    {
    }

    public function total(Car $car): string
    {
        return $this->sumOf($car->getExpenses())->toString();
    }

    public function totalForPeriod(Car $car, \DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        if ($from >= $to) {
            return Money::zero()->toString();
        }

        $inPeriod = [];
        foreach ($car->getExpenses() as $expense) {
            $date = $expense->getDate();
            if (null !== $date && $date >= $from && $date < $to) {
                $inPeriod[] = $expense;
            }
        }

        return $this->sumOf($inPeriod)->toString();
    }

    /**
     * @return array<string,string>
     */
    public function totalsByCategory(Car $car): array
    {
        /** @var array<string,list<Expense>> $groups */
        $groups = [];
        foreach ($car->getExpenses() as $expense) {
            $groups[mb_strtolower(trim((string) $expense->getCategory()))][] = $expense;
        }

        /** @var array<string,Money> $totals */
        $totals = [];
        foreach ($groups as $key => $expenses) {
            $totals[(string) $key] = $this->sumOf($expenses);
        }

        $keys = array_map('strval', array_keys($totals));
        usort($keys, function (string $a, string $b) use ($totals): int {
            return $totals[$b]->compareTo($totals[$a]) ?: $this->collator->compare($a, $b);
        });

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $totals[$key]->toString();
        }

        return $result;
    }

    /**
     * Єдине місце підсумовування. null amount → нуль.
     *
     * @param iterable<Expense> $expenses
     *
     * @throws \InvalidArgumentException на некоректну суму
     */
    private function sumOf(iterable $expenses): Money
    {
        $sum = Money::zero();
        foreach ($expenses as $expense) {
            $amount = $expense->getAmount();
            if (null !== $amount) {
                $sum = $sum->add(Money::fromString(trim($amount)));
            }
        }

        return $sum;
    }
}
