<?php

declare(strict_types=1);

namespace App\Validator;

use App\ValueObject\Money;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidMoneyValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidMoney) {
            throw new UnexpectedTypeException($constraint, ValidMoney::class);
        }
        // Порожнє — справа NotBlank (як і в Regex).
        if (null === $value || '' === $value) {
            return;
        }

        $isMoney = (is_scalar($value) || $value instanceof \Stringable)
            && (Money::isValid((string) $value) || Money::isTooLarge((string) $value));

        if (!$isMoney) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
