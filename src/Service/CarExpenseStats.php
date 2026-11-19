<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Car;

final class CarExpenseStats
{
    private const UKRAINIAN_ALPHABET = 'абвгґдеєжзиіїйклмнопрстуфхцчшщьюя';

    /** @var array<string,int>|null */
    private static ?array $charOrder = null;

    public function total(Car $car): string
    {
        $cents = 0;
        foreach ($car->getExpenses() as $expense) {
            $cents += $this->toCents($expense->getAmount());
        }

        return $this->format($cents);
    }

    public function totalForPeriod(Car $car, \DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        if ($from >= $to) {
            return '0.00';
        }

        $cents = 0;
        foreach ($car->getExpenses() as $expense) {
            $date = $expense->getDate();
            if (null === $date || $date < $from || $date >= $to) {
                continue;
            }
            $cents += $this->toCents($expense->getAmount());
        }

        return $this->format($cents);
    }

    /**
     * @return array<string,string>
     */
    public function totalsByCategory(Car $car): array
    {
        /** @var array<string,int> $totals */
        $totals = [];
        foreach ($car->getExpenses() as $expense) {
            $key = mb_strtolower(trim((string) $expense->getCategory()));
            $totals[$key] = ($totals[$key] ?? 0) + $this->toCents($expense->getAmount());
        }

        $keys = array_map('strval', array_keys($totals));
        usort($keys, function (string $a, string $b) use ($totals): int {
            return [$totals[$b]] <=> [$totals[$a]] ?: $this->compareUkrainian($a, $b);
        });

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->format($totals[$key]);
        }

        return $result;
    }

    private function toCents(?string $amount): int
    {
        if (null === $amount) {
            return 0;
        }
        if (1 !== preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($amount), $m)) {
            throw new \InvalidArgumentException(sprintf('Invalid amount "%s".', $amount));
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function format(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
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
