<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Car;
use App\Entity\Expense;
use App\ValueObject\Money;

final class CarExpenseStats
{
    private const UKRAINIAN_ALPHABET = 'абвгґдеєжзиіїйклмнопрстуфхцчшщьюя';

    /** @var array<string,int>|null */
    private static ?array $charOrder = null;

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
            return $totals[$b]->compareTo($totals[$a]) ?: $this->compareUkrainian($a, $b);
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

    private function compareUkrainian(string $a, string $b): int
    {
        $order = self::$charOrder ??= array_flip(mb_str_split(self::UKRAINIAN_ALPHABET));

        $ca = mb_str_split($a);
        $cb = mb_str_split($b);
        $len = min(count($ca), count($cb));
        for ($i = 0; $i < $len; ++$i) {
            $ka = $order[$ca[$i]] ?? mb_ord($ca[$i]) + 1000;
            $kb = $order[$cb[$i]] ?? mb_ord($cb[$i]) + 1000;
            if ($ka !== $kb) {
                return $ka <=> $kb;
            }
        }

        return count($ca) <=> count($cb);
    }
}
