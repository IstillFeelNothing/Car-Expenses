<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Car;
use App\Entity\Expense;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ExpenseValidationTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
    }

    private function makeExpense(string $amount = '10.00', ?string $category = 'пальне', string $date = '-1 day'): Expense
    {
        $car = (new Car())->setSlug('toyota')->setName('Toyota')->setCountry('Japan');

        return (new Expense())
            ->setCar($car)
            ->setAmount($amount)
            ->setCategory($category ?? '')
            ->setDate(new \DateTimeImmutable($date));
    }

    #[DataProvider('validAmounts')]
    public function testAcceptsValidAmount(string $amount): void
    {
        self::assertCount(0, $this->validator->validateProperty($this->makeExpense($amount), 'amount'));
    }

    /** @return iterable<string, array{string}> */
    public static function validAmounts(): iterable
    {
        yield 'min' => ['0.01'];
        yield 'integer' => ['1250'];
        yield 'one decimal' => ['5.5'];
        yield 'two decimals' => ['1250.00'];
        yield 'max' => ['1000000'];
        yield 'max with decimals' => ['1000000.00'];
    }

    #[DataProvider('invalidAmounts')]
    public function testRejectsInvalidAmount(string $amount): void
    {
        self::assertGreaterThan(0, count($this->validator->validateProperty($this->makeExpense($amount), 'amount')));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidAmounts(): iterable
    {
        yield 'zero' => ['0'];
        yield 'zero decimals' => ['0.00'];
        yield 'negative' => ['-1'];
        yield 'too big' => ['1000000.01'];
        yield 'three decimals' => ['1.234'];
        yield 'not a number' => ['abc'];
        yield 'empty' => [''];
    }

    public function testRejectsFutureDate(): void
    {
        $expense = $this->makeExpense(date: '+2 days');

        self::assertCount(1, $this->validator->validateProperty($expense, 'date'));
    }

    public function testAcceptsTodayAndPastDate(): void
    {
        self::assertCount(0, $this->validator->validateProperty($this->makeExpense(date: 'today'), 'date'));
        self::assertCount(0, $this->validator->validateProperty($this->makeExpense(date: '-1 year'), 'date'));
    }

    public function testRejectsTooLongCategory(): void
    {
        $expense = $this->makeExpense(category: str_repeat('a', 51));

        self::assertCount(1, $this->validator->validateProperty($expense, 'category'));
    }

    public function testAcceptsCategoryOfMaxLength(): void
    {
        $expense = $this->makeExpense(category: str_repeat('я', 50));

        self::assertCount(0, $this->validator->validateProperty($expense, 'category'));
    }

    public function testRejectsBlankCategory(): void
    {
        self::assertGreaterThan(0, count($this->validator->validateProperty($this->makeExpense(category: ''), 'category')));
    }

    public function testValidExpenseHasNoViolations(): void
    {
        self::assertCount(0, $this->validator->validate($this->makeExpense()));
    }

    public function testCarIsRequired(): void
    {
        $expense = $this->makeExpense()->setCar(null);

        self::assertCount(1, $this->validator->validateProperty($expense, 'car'));
    }
}
