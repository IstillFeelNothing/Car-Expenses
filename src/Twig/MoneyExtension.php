<?php

declare(strict_types=1);

namespace App\Twig;

use App\ValueObject\Money;
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
        $notAmount = static fn (mixed $v): \InvalidArgumentException => new \InvalidArgumentException(sprintf(
            'Фільтр money: "%s" не є сумою (очікується int або рядок на кшталт "1250.50").',
            is_scalar($v) ? (string) $v : get_debug_type($v),
        ));
        if (!is_string($amount)) {
            throw $notAmount($amount);
        }

        // Money невід'ємний: знак обробляємо тут.
        $sign = str_starts_with($amount, '-') ? '-' : '';
        $unsigned = '' === $sign ? $amount : substr($amount, 1);

        if ((Money::decimalPlaces($unsigned) ?? 0) > 2) {
            throw new \InvalidArgumentException(sprintf(
                'Фільтр money: у сумі "%s" більше 2 знаків після крапки.',
                $amount,
            ));
        }
        if (Money::isTooLarge($unsigned)) {
            throw new \InvalidArgumentException(sprintf(
                'Фільтр money: сума "%s" завелика (більше 16 цифр у цілій частині).',
                $amount,
            ));
        }
        if (!Money::isValid($unsigned)) {
            throw $notAmount($amount);
        }

        return $sign.Money::fromString($unsigned)->formatGrouped();
    }
}
