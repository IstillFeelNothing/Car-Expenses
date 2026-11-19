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

        if (!is_scalar($value) && !$value instanceof \Stringable) {
            $this->context->buildViolation($constraint->invalidMessage)->addViolation();

            return;
        }

        $amount = (string) $value;
        if (Money::isValid($amount) || Money::isTooLarge($amount)) {
            return;
        }

        // decimalPlaces() === null — це не невід'ємна десяткова сума; інакше причина — забагато знаків.
        $message = null === Money::decimalPlaces($amount) ? $constraint->invalidMessage : $constraint->message;
        $this->context->buildViolation($message)->addViolation();
    }
}
