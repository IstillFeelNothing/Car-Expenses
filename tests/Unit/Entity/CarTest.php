<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Car;
use App\Entity\Expense;
use PHPUnit\Framework\TestCase;

final class CarTest extends TestCase
{
    private function makeExpense(): Expense
    {
        return (new Expense())
            ->setAmount('10.50')
            ->setCategory('пальне')
            ->setDate(new \DateTimeImmutable('2026-01-01'));
    }

    public function testAddExpenseSetsInverseSide(): void
    {
        $car = new Car();
        $expense = $this->makeExpense();

        $car->addExpense($expense);

        self::assertCount(1, $car->getExpenses());
        self::assertSame($car, $expense->getCar());
    }

    public function testAddExpenseTwiceDoesNotDuplicate(): void
    {
        $car = new Car();
        $expense = $this->makeExpense();

        $car->addExpense($expense)->addExpense($expense);

        self::assertCount(1, $car->getExpenses());
    }

    public function testRemoveExpenseClearsInverseSide(): void
    {
        $car = new Car();
        $expense = $this->makeExpense();
        $car->addExpense($expense);

        $car->removeExpense($expense);

        self::assertCount(0, $car->getExpenses());
        self::assertNull($expense->getCar());
    }

    public function testToStringReturnsName(): void
    {
        $car = (new Car())->setSlug('toyota')->setName('Toyota')->setCountry('Japan');

        self::assertSame('Toyota', (string) $car);
    }

    public function testToStringOfEmptyCarIsEmptyString(): void
    {
        self::assertSame('', (string) new Car());
    }
}
