<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MoneyExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('money', $this->formatMoney(...))];
    }

    /**
     * "1250.5" -> "1 250,50 грн" (нерозривні пробіли). Без float.
     *
     * Приймає int або рядок формату `-?\d+(\.\d{1,2})?`. Усе інше (null, "abc", "1e3", "1,5", більше
     * 2 знаків після крапки) — помилка, а не мовчазні «0,00 грн»: amount у БД це decimal(10,2).
     *
     * @throws \InvalidArgumentException
     */
    public function formatMoney(mixed $amount): string
    {
        if (null === $amount) {
            throw new \InvalidArgumentException('Фільтр money: отримано null замість суми.');
        }
        if (is_int($amount)) {
            $amount = (string) $amount;
        }
        if (!is_string($amount) || 1 !== preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $amount, $m)) {
            throw new \InvalidArgumentException(sprintf(
                'Фільтр money: "%s" не є сумою (очікується int або рядок на кшталт "1250.50").',
                is_scalar($amount) ? (string) $amount : get_debug_type($amount),
            ));
        }
        if (strlen($m[3] ?? '') > 2) {
            throw new \InvalidArgumentException(sprintf(
                'Фільтр money: у сумі "%s" більше 2 знаків після крапки.',
                $amount,
            ));
        }

        $nbsp = "\u{00A0}";
        $int = (string) preg_replace('/\B(?=(\d{3})+$)/', $nbsp, ltrim($m[2], '0') ?: '0');
        $frac = str_pad($m[3] ?? '', 2, '0');

        return $m[1].$int.','.$frac.$nbsp.'грн';
    }
}
