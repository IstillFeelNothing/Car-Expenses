<?php

declare(strict_types=1);

namespace App\ValueObject;

/**
 * Невід'ємна грошова сума в копійках (int). Без float і bcmath.
 */
final readonly class Money implements \Stringable
{
    /** Найбільша кількість цифр цілої частини, що гарантовано вміщується в int копійок. */
    private const MAX_INTEGER_DIGITS = 16;

    private function __construct(private int $cents)
    {
    }

    /**
     * "1250" → 1250.00, "10.5" → 10.50.
     *
     * @throws \InvalidArgumentException
     */
    public static function fromString(string $amount): self
    {
        if (1 !== preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $amount, $m)) {
            throw new \InvalidArgumentException(sprintf('Invalid amount "%s".', $amount));
        }
        if (strlen($m[1]) > self::MAX_INTEGER_DIGITS) {
            throw new \InvalidArgumentException(sprintf('Amount "%s" is too large.', $amount));
        }

        return new self((int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0'));
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
        return intdiv($this->cents, 100).'.'.str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
