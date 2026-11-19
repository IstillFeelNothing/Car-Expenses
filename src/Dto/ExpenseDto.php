<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Expense;

final readonly class ExpenseDto implements \JsonSerializable
{
    public function __construct(
        public int $id,
        public string $car,
        public string $amount,
        public string $category,
        public string $date,
        public ?string $description,
    ) {
    }

    public static function fromEntity(Expense $expense): self
    {
        $car = $expense->getCar() ?? throw new \LogicException('Витрата без машини не може бути серіалізована.');
        $id = $expense->getId() ?? throw new \LogicException('Витрата без id не може бути серіалізована.');
        $date = $expense->getDate() ?? throw new \LogicException('Витрата без дати не може бути серіалізована.');

        return new self(
            $id,
            (string) $car->getSlug(),
            self::formatAmount((string) $expense->getAmount()),
            (string) $expense->getCategory(),
            $date->format('Y-m-d'),
            $expense->getDescription(),
        );
    }

    /** Нормалізує суму до рядка з 2 знаками без float: "1250" → "1250.00", "10.5" → "10.50". */
    private static function formatAmount(string $amount): string
    {
        if (1 !== preg_match('/\A(\d+)(?:\.(\d{1,2}))?\z/', $amount, $m)) {
            throw new \LogicException(sprintf('Некоректна сума витрати: "%s".', $amount));
        }

        return $m[1].'.'.str_pad($m[2] ?? '', 2, '0');
    }

    /** @return array{id: int, car: string, amount: string, category: string, date: string, description: ?string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'car' => $this->car,
            'amount' => $this->amount,
            'category' => $this->category,
            'date' => $this->date,
            'description' => $this->description,
        ];
    }
}
