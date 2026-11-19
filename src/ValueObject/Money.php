<?php

declare(strict_types=1);

namespace App\ValueObject;

/**
 * Невід'ємна грошова сума в копійках (int). Без float і bcmath.
 *
 * Єдине місце, де розбирається, перевіряється і форматується сума.
 */
final readonly class Money implements \Stringable
{
    /** Найбільша кількість цифр цілої частини, що гарантовано вміщується в int копійок. */
    private const MAX_INTEGER_DIGITS = 16;

    /** Найбільша кількість знаків після крапки. */
    private const MAX_DECIMALS = 2;

    /** Ціла частина і необов'язкова дробова (будь-якої довжини; ліміти перевіряє parse()). */
    private const PATTERN = '/^(\d+)(?:\.(\d+))?$/D';

    private const INVALID = 'invalid';
    private const TOO_LARGE = 'too_large';

    private function __construct(private int $cents)
    {
    }

    /**
     * Єдина точка перевірки: "1250", "10.5", "10.05" — так; "-1", "1.234", "1,5", "abc", "" — ні.
     */
    public static function isValid(string $amount): bool
    {
        return is_int(self::parse($amount));
    }

    /**
     * Формат коректний (до 2 знаків після крапки), але значущих цифр цілої частини більше 16.
     */
    public static function isTooLarge(string $amount): bool
    {
        return self::TOO_LARGE === self::parse($amount);
    }

    /**
     * Кількість знаків після крапки для рядка виду `\d+(\.\d+)?` (0, якщо крапки нема);
     * null, якщо це не невід'ємна десяткова сума. Дозволяє відрізнити «забагато знаків» від «не сума».
     */
    public static function decimalPlaces(string $amount): ?int
    {
        if (1 !== preg_match(self::PATTERN, $amount, $m)) {
            return null;
        }

        return strlen($m[2] ?? '');
    }

    /**
     * "1250" → 1250.00, "10.5" → 10.50.
     *
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $amount): self
    {
        $cents = self::parse($amount);
        if (self::TOO_LARGE === $cents) {
            throw new \InvalidArgumentException(sprintf('Amount "%s" is too large.', $amount));
        }
        if (!is_int($cents)) {
            throw new \InvalidArgumentException(sprintf('Invalid amount "%s".', $amount));
        }

        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function add(self $other): self
    {
        $sum = $this->cents + $other->cents;
        if (!is_int($sum)) {
            throw new \OverflowException('Money overflow.');
        }

        return new self($sum);
    }

    public function compareTo(self $other): int
    {
        return $this->cents <=> $other->cents;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function toString(): string
    {
        return intdiv($this->cents, 100).'.'.$this->centsPart();
    }

    /**
     * "1 250,50 грн": нерозривні пробіли (U+00A0) між тисячами і перед "грн".
     */
    public function formatGrouped(): string
    {
        $nbsp = "\u{00A0}";
        $int = (string) preg_replace('/\B(?=(\d{3})+$)/', $nbsp, (string) intdiv($this->cents, 100));

        return $int.','.$this->centsPart().$nbsp.'грн';
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private function centsPart(): string
    {
        return str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** @return int|self::INVALID|self::TOO_LARGE копійки або причина відмови */
    private static function parse(string $amount): int|string
    {
        if (1 !== preg_match(self::PATTERN, $amount, $m) || strlen($m[2] ?? '') > self::MAX_DECIMALS) {
            return self::INVALID;
        }
        // Ведучі нулі не рахуються: "0000000000000000005" — це 5.
        $integer = ltrim($m[1], '0');
        if (strlen($integer) > self::MAX_INTEGER_DIGITS) {
            return self::TOO_LARGE;
        }

        return (int) $integer * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }
}
