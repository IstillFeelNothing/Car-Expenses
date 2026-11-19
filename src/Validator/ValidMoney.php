<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Рядок є сумою, яку приймає App\ValueObject\Money (до 2 знаків після коми, невід'ємна).
 * Завеликі суми пропускає: розмір перевіряє LessThanOrEqual.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ValidMoney extends Constraint
{
    /** Формат суми правильний, але знаків після коми більше 2. */
    public string $message = 'Не більше 2 знаків після коми.';

    /** Не сума взагалі: літери, мінус, кома замість крапки тощо. */
    public string $invalidMessage = 'Некоректна сума: очікується число, наприклад 1250.50.';
}
